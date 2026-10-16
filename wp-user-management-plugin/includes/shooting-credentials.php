<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shooting credentials: numbers + PDF documents.
 *
 * Documents live in uploads/wpum-private/{user_id}/{random}.pdf (denied for direct web access)
 * and are served only through wpum_handle_credential_download() to the owner or an administrator.
 * `file_path` in the DB is relative to the private directory ('' = no document).
 */

// Create the credentials table (idempotent).
function wpum_create_shooting_credentials_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        credential_type varchar(50) NOT NULL,
        credential_number varchar(100) NOT NULL,
        file_path varchar(255) NOT NULL DEFAULT '',
        uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (!wpum_table_exists($table_name)) {
        wpum_log("Nie udało się utworzyć tabeli $table_name");
        return false;
    }

    return true;
}

// Make sure the table exists (checked at most once per request).
function wpum_ensure_credentials_table() {
    static $checked = false;
    global $wpdb;

    if ($checked) {
        return true;
    }
    $checked = true;

    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    if (wpum_table_exists($table_name)) {
        return true;
    }

    return wpum_create_shooting_credentials_table();
}

// Register shooting credential types
function wpum_get_shooting_credential_types() {
    return array(
        'shooting_patent' => __('Shooting Patent Number', 'wp-user-management-plugin'),
        'instructor_license' => __('Shooting Instructor License Number', 'wp-user-management-plugin')
    );
}

/* ------------------------------------------------------------------ */
/* Private storage                                                     */
/* ------------------------------------------------------------------ */

function wpum_get_private_dir() {
    $upload_dir = wp_upload_dir();
    return untrailingslashit($upload_dir['basedir']) . '/wpum-private';
}

// Deny direct access (Apache) and block directory listing everywhere.
function wpum_protect_directory($dir) {
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        $rules  = "# Private files - served only through the plugin.\n";
        $rules .= "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
        $rules .= "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
        @file_put_contents($htaccess, $rules);
    }

    $index = $dir . '/index.php';
    if (!file_exists($index)) {
        @file_put_contents($index, "<?php\n// Silence is golden.\n");
    }
}

// Create (and protect) the private directory. Returns its path or false.
function wpum_ensure_private_dir() {
    $dir = wpum_get_private_dir();

    if (!file_exists($dir) && !wp_mkdir_p($dir)) {
        wpum_log('Nie można utworzyć katalogu prywatnego: ' . $dir);
        return false;
    }

    wpum_protect_directory($dir);

    return is_writable($dir) ? $dir : false;
}

function wpum_generate_private_filename() {
    return strtolower(wp_generate_password(32, false, false)) . '.pdf';
}

// Resolve a DB-relative path to an absolute file inside the private dir; false if outside or missing.
function wpum_resolve_private_file($relative_path) {
    $relative_path = ltrim((string) $relative_path, '/\\');
    if ('' === $relative_path) {
        return false;
    }

    $base = realpath(wpum_get_private_dir());
    $file = realpath(wpum_get_private_dir() . '/' . $relative_path);

    if (!$base || !$file || 0 !== strpos($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
        return false;
    }

    return $file;
}

function wpum_delete_credential_file($relative_path) {
    $file = wpum_resolve_private_file($relative_path);
    if ($file) {
        @unlink($file);
    }
}

/* ------------------------------------------------------------------ */
/* Upload                                                              */
/* ------------------------------------------------------------------ */

// Validate and store an uploaded PDF. Returns the DB-relative path or WP_Error.
function wpum_handle_credential_file_upload($file, $user_id, $credential_type) {
    $user_id = (int) $user_id;

    if (!isset($file['error']) || UPLOAD_ERR_OK !== (int) $file['error']) {
        wpum_log('Błąd przesyłania pliku, kod: ' . (isset($file['error']) ? $file['error'] : 'brak'));
        return new WP_Error('upload_error', __('File upload failed', 'wp-user-management-plugin'));
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('upload_error', __('File upload failed', 'wp-user-management-plugin'));
    }

    $max_size = (int) apply_filters('wpum_credential_max_upload_size', 5 * MB_IN_BYTES);
    $size     = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > $max_size) {
        return new WP_Error('invalid_size', sprintf(
            /* translators: %s: maximum file size */
            __('The file is too large. Maximum size is %s.', 'wp-user-management-plugin'),
            size_format($max_size)
        ));
    }

    // Extension check...
    $file_type = wp_check_filetype(isset($file['name']) ? $file['name'] : '', array('pdf' => 'application/pdf'));
    if ('application/pdf' !== $file_type['type']) {
        return new WP_Error('invalid_type', __('Only PDF files are allowed', 'wp-user-management-plugin'));
    }

    // ...and content check: magic bytes + (when available) libmagic.
    $handle = @fopen($file['tmp_name'], 'rb');
    $magic  = $handle ? fread($handle, 5) : '';
    if ($handle) {
        fclose($handle);
    }
    if ('%PDF-' !== $magic) {
        return new WP_Error('invalid_type', __('Only PDF files are allowed', 'wp-user-management-plugin'));
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if ($mime && 'application/pdf' !== $mime) {
            return new WP_Error('invalid_type', __('Only PDF files are allowed', 'wp-user-management-plugin'));
        }
    }

    $private_dir = wpum_ensure_private_dir();
    if (!$private_dir) {
        return new WP_Error('directory_error', __('Cannot create upload directory', 'wp-user-management-plugin'));
    }

    $user_dir = $private_dir . '/' . $user_id;
    if (!file_exists($user_dir) && !wp_mkdir_p($user_dir)) {
        return new WP_Error('directory_error', __('Cannot create upload directory', 'wp-user-management-plugin'));
    }
    wpum_protect_directory($user_dir);

    $filename = wpum_generate_private_filename();
    if (!move_uploaded_file($file['tmp_name'], $user_dir . '/' . $filename)) {
        wpum_log('Nie udało się przenieść pliku dla użytkownika ' . $user_id);
        return new WP_Error('move_error', __('Failed to save file', 'wp-user-management-plugin'));
    }

    return $user_id . '/' . $filename;
}

/* ------------------------------------------------------------------ */
/* Database access                                                     */
/* ------------------------------------------------------------------ */

// Save (insert or update) a credential. Replaces and deletes the previous document when a new one is given.
function wpum_save_shooting_credential($user_id, $credential_type, $credential_number, $file_path = null) {
    global $wpdb;

    $types = wpum_get_shooting_credential_types();
    if (!isset($types[$credential_type]) || !wpum_ensure_credentials_table()) {
        return false;
    }

    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    $existing   = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND credential_type = %s ORDER BY id DESC LIMIT 1",
        $user_id,
        $credential_type
    ));

    if ($existing) {
        $new_path = (null === $file_path) ? $existing->file_path : (string) $file_path;
        $result   = $wpdb->update(
            $table_name,
            array(
                'credential_number' => $credential_number,
                'file_path'         => $new_path,
                'uploaded_at'       => current_time('mysql'),
            ),
            array('id' => (int) $existing->id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        if (false === $result) {
            wpum_log('Błąd aktualizacji uprawnienia: ' . $wpdb->last_error);
            return false;
        }
        if ($new_path !== $existing->file_path && '' !== $existing->file_path) {
            wpum_delete_credential_file($existing->file_path);
        }
        return true;
    }

    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id'           => $user_id,
            'credential_type'   => $credential_type,
            'credential_number' => $credential_number,
            'file_path'         => (null === $file_path) ? '' : (string) $file_path,
        ),
        array('%d', '%s', '%s', '%s')
    );
    if (false === $result) {
        wpum_log('Błąd zapisu uprawnienia: ' . $wpdb->last_error);
        return false;
    }

    return true;
}

// Get user credentials
function wpum_get_user_credentials($user_id) {
    global $wpdb;

    if (!wpum_ensure_credentials_table()) {
        return array();
    }

    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    $results    = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    return $results ? $results : array();
}

// One query for many users: [user_id][credential_type] => row. Used by the admin list.
function wpum_get_credentials_for_users($user_ids) {
    global $wpdb;

    $user_ids = array_values(array_filter(array_map('absint', (array) $user_ids)));
    if (empty($user_ids) || !wpum_ensure_credentials_table()) {
        return array();
    }

    $table_name   = $wpdb->prefix . 'wpum_shooting_credentials';
    $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
    $rows         = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id IN ($placeholders) ORDER BY id ASC",
        $user_ids
    ));

    $map = array();
    foreach ((array) $rows as $row) {
        $map[(int) $row->user_id][$row->credential_type] = $row;
    }

    return $map;
}

function wpum_get_credential_by_id($id) {
    global $wpdb;

    if (!wpum_ensure_credentials_table()) {
        return null;
    }

    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

// Remove every credential row + file of a user (account deletion, GDPR erasure). Returns number of removed rows.
function wpum_delete_user_credentials($user_id) {
    global $wpdb;

    $user_id = (int) $user_id;
    if ($user_id <= 0 || !wpum_ensure_credentials_table()) {
        return 0;
    }

    $rows = wpum_get_user_credentials($user_id);
    foreach ($rows as $row) {
        if ('' !== $row->file_path) {
            wpum_delete_credential_file($row->file_path);
        }
    }

    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    $wpdb->delete($table_name, array('user_id' => $user_id), array('%d'));

    // Drop the user's (now empty) directory.
    $user_dir = wpum_get_private_dir() . '/' . $user_id;
    if (is_dir($user_dir)) {
        @unlink($user_dir . '/index.php');
        @unlink($user_dir . '/.htaccess');
        @rmdir($user_dir);
    }

    return count($rows);
}

/* ------------------------------------------------------------------ */
/* Authenticated download                                              */
/* ------------------------------------------------------------------ */

function wpum_credential_download_url($credential_id) {
    $credential_id = (int) $credential_id;

    return wp_nonce_url(
        add_query_arg(
            array('action' => 'wpum_download_credential', 'credential_id' => $credential_id),
            admin_url('admin-post.php')
        ),
        'wpum_download_credential_' . $credential_id
    );
}

function wpum_handle_credential_download() {
    $credential_id = isset($_GET['credential_id']) ? absint($_GET['credential_id']) : 0;
    check_admin_referer('wpum_download_credential_' . $credential_id);

    $row = $credential_id ? wpum_get_credential_by_id($credential_id) : null;
    if (!$row || '' === $row->file_path) {
        wp_die(esc_html__('File not found.', 'wp-user-management-plugin'), '', array('response' => 404));
    }

    if (!current_user_can('manage_options') && (int) $row->user_id !== get_current_user_id()) {
        wp_die(esc_html__('You are not allowed to view this file.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    $file = wpum_resolve_private_file($row->file_path);
    if (!$file) {
        wp_die(esc_html__('File not found.', 'wp-user-management-plugin'), '', array('response' => 404));
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: inline; filename="' . sanitize_file_name($row->credential_type . '.pdf') . '"');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store, max-age=0');
    readfile($file);
    exit;
}
add_action('admin_post_wpum_download_credential', 'wpum_handle_credential_download');

/* ------------------------------------------------------------------ */
/* AJAX save (profile page)                                            */
/* ------------------------------------------------------------------ */

add_action('wp_ajax_wpum_save_credentials', 'wpum_ajax_save_credentials');

function wpum_ajax_save_credentials() {
    if (!check_ajax_referer('wpum_shooting_credentials', 'wpum_nonce', false)) {
        wp_send_json_error(array(
            'message' => __('Security check failed.', 'wp-user-management-plugin')
        ));
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(array(
            'message' => __('You must be logged in to perform this action.', 'wp-user-management-plugin')
        ));
    }

    $input = isset($_POST['wpum_credentials']) && is_array($_POST['wpum_credentials']) ? wp_unslash($_POST['wpum_credentials']) : array();

    $messages = array();

    // Only known credential types are processed - never trust keys sent by the browser.
    foreach (wpum_get_shooting_credential_types() as $type => $label) {
        $number = '';
        if (isset($input[$type]['number']) && is_string($input[$type]['number'])) {
            $number = mb_substr(sanitize_text_field($input[$type]['number']), 0, 100);
        }

        $has_file = isset($_FILES['wpum_credentials']['name'][$type]['file'])
            && '' !== $_FILES['wpum_credentials']['name'][$type]['file'];

        if ('' === $number) {
            if ($has_file) {
                /* translators: %s: credential label */
                $messages[] = sprintf(__('Enter the number for "%s" before uploading a document.', 'wp-user-management-plugin'), $label);
            }
            continue;
        }

        $file_path = null;
        if ($has_file) {
            $file_data = array(
                'name'     => $_FILES['wpum_credentials']['name'][$type]['file'],
                'type'     => $_FILES['wpum_credentials']['type'][$type]['file'],
                'tmp_name' => $_FILES['wpum_credentials']['tmp_name'][$type]['file'],
                'error'    => $_FILES['wpum_credentials']['error'][$type]['file'],
                'size'     => $_FILES['wpum_credentials']['size'][$type]['file'],
            );

            $upload_result = wpum_handle_credential_file_upload($file_data, $user_id, $type);
            if (is_wp_error($upload_result)) {
                /* translators: 1: credential label, 2: error message */
                $messages[] = sprintf(__('Error uploading file for %1$s: %2$s', 'wp-user-management-plugin'), $label, $upload_result->get_error_message());
                continue;
            }
            $file_path = $upload_result;
        }

        if (!wpum_save_shooting_credential($user_id, $type, $number, $file_path)) {
            // Don't leave an orphaned upload behind.
            if (null !== $file_path) {
                wpum_delete_credential_file($file_path);
            }
            /* translators: %s: credential label */
            $messages[] = sprintf(__('Failed to save %s credential data.', 'wp-user-management-plugin'), $label);
        }
    }

    if (empty($messages)) {
        wp_send_json_success(array(
            'message' => __('Credentials saved successfully.', 'wp-user-management-plugin'),
            'reload'  => true
        ));
    }

    // Plain text, one message per line; the browser renders it with .text().
    wp_send_json_error(array(
        'message' => implode("\n", $messages)
    ));
}
