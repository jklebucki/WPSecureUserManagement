<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create tables on plugin activation
function wpum_create_shooting_credentials_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_shooting_credentials (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        credential_type varchar(50) NOT NULL,
        credential_number varchar(100) NOT NULL,
        file_path varchar(255) NOT NULL,
        uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register shooting credential types
function wpum_get_shooting_credential_types() {
    return array(
        'shooting_patent' => __('Shooting Patent Number', 'wp-user-management-plugin'),
        'instructor_license' => __('Shooting Instructor License Number', 'wp-user-management-plugin')
    );
}

// Handle file upload
function wpum_handle_credential_file_upload($file, $user_id, $credential_type) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wpum_log("Błąd przesyłania pliku: " . $file['error']);
        return new WP_Error('upload_error', __('File upload failed', 'wp-user-management-plugin'));
    }

    // Verify file type
    $file_type = wp_check_filetype($file['name'], array('pdf' => 'application/pdf'));
    if ($file_type['type'] !== 'application/pdf') {
        wpum_log("Nieprawidłowy typ pliku: " . $file_type['type']);
        return new WP_Error('invalid_type', __('Only PDF files are allowed', 'wp-user-management-plugin'));
    }

    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $credential_dir = $upload_dir['basedir'] . '/shooting-credentials/' . $user_id;
    if (!file_exists($credential_dir)) {
        wp_mkdir_p($credential_dir);
    }

    // Generate unique filename
    $filename = sanitize_file_name($credential_type . '-' . time() . '.pdf');
    $file_path = $credential_dir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        wpum_log("Nie udało się przenieść pliku do: " . $file_path);
        return new WP_Error('move_error', __('Failed to save file', 'wp-user-management-plugin'));
    }

    return str_replace($upload_dir['basedir'], '', $file_path);
}

// Save credential data
function wpum_save_shooting_credential($user_id, $credential_type, $credential_number, $file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    
    // Delete existing credential of the same type
    $wpdb->delete(
        $table_name,
        array(
            'user_id' => $user_id,
            'credential_type' => $credential_type
        ),
        array('%d', '%s')
    );
    
    // Insert new credential
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'credential_type' => $credential_type,
            'credential_number' => $credential_number,
            'file_path' => $file_path
        ),
        array('%d', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        wpum_log("Błąd podczas zapisywania uprawnienia: " . $wpdb->last_error);
        return false;
    }
    
    return true;
}

// Get user credentials
function wpum_get_user_credentials($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    
    // Sprawdź czy tabela istnieje
    if (!wpum_table_exists($table_name)) {
        wpum_log("Tabela $table_name nie istnieje - próba utworzenia");
        if (!function_exists('wpum_create_tables')) {
            wpum_log("Funkcja wpum_create_tables nie jest dostępna");
            return array();
        }
        if (!wpum_create_tables()) {
            wpum_log("Nie można utworzyć tabeli $table_name");
            return array();
        }
    }
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));
    
    return $results ?: array();
}

// Obsługa formularza uprawnień
function wpum_process_shooting_credentials() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_shooting_credentials_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_shooting_credentials_nonce'], 'sum_shooting_credentials_nonce')) {
            wpum_log("Błąd weryfikacji nonce");
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $user_id = get_current_user_id();
        $credential_types = wpum_get_shooting_credential_types();

        foreach ($credential_types as $type => $label) {
            $number_field = $type . '_number';
            $file_field = $type . '_file';

            if (!empty($_POST[$number_field])) {
                $credential_number = sanitize_text_field($_POST[$number_field]);
                
                if (!empty($_FILES[$file_field]['name'])) {
                    $upload_result = wpum_handle_credential_file_upload($_FILES[$file_field], $user_id, $type);
                    
                    if (is_wp_error($upload_result)) {
                        wpum_log("Błąd przesyłania pliku: " . $upload_result->get_error_message());
                        wp_die($upload_result->get_error_message());
                    }
                    
                    wpum_save_shooting_credential($user_id, $type, $credential_number, $upload_result);
                } else {
                    // Aktualizuj tylko numer uprawnienia, jeśli nie dodano nowego pliku
                    $existing_credentials = wpum_get_user_credentials($user_id);
                    foreach ($existing_credentials as $cred) {
                        if ($cred->credential_type === $type) {
                            wpum_save_shooting_credential($user_id, $type, $credential_number, $cred->file_path);
                            break;
                        }
                    }
                }
            }
        }

        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
}
add_action('init', 'wpum_process_shooting_credentials'); 