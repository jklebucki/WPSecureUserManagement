<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// User Functions for User Management Plugin

// Retrieve user data by user ID
function wpum_get_user_data($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    return array(
        'ID' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'first_name' => get_user_meta($user->ID, 'first_name', true),
        'last_name' => get_user_meta($user->ID, 'last_name', true),
        'role' => !empty($user->roles) ? reset($user->roles) : ''
    );
}

// Update user data
function wpum_update_user_data($user_id, $data) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    $update_data = array('ID' => $user_id);

    if (isset($data['email'])) {
        $update_data['user_email'] = sanitize_email($data['email']);
    }

    if (isset($data['first_name'])) {
        update_user_meta($user_id, 'first_name', sanitize_text_field($data['first_name']));
    }

    if (isset($data['last_name'])) {
        update_user_meta($user_id, 'last_name', sanitize_text_field($data['last_name']));
    }

    return wp_update_user($update_data);
}

// Change user password
function wpum_change_user_password($user_id, $new_password) {
    return wp_set_password($new_password, $user_id);
}

// Delete user account
function wpum_delete_user_account($user_id) {
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    return wp_delete_user($user_id);
}

// Check if username or email exists
function wpum_user_exists($username_or_email) {
    if (is_email($username_or_email)) {
        return email_exists($username_or_email);
    } else {
        return username_exists($username_or_email);
    }
}

// Minimum password length (Configuration page setting, never below 8).
function wpum_get_min_password_length() {
    return max(8, (int) get_option('wp_user_management_password_length', 8));
}

// Validate password strength
function wpum_validate_password_strength($password) {
    // Minimalna długość z ustawień (domyślnie 8)
    if (strlen($password) < wpum_get_min_password_length()) {
        return false;
    }

    // Musi zawierać co najmniej jedną wielką literę
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // Musi zawierać co najmniej jedną małą literę
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // Musi zawierać co najmniej jedną cyfrę
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    // Musi zawierać co najmniej jeden znak specjalny
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }

    return true;
}

/**
 * User meta keys that must never be edited through the plugin (privilege escalation / session hijack / WP internals).
 */
function wpum_is_forbidden_meta_key($meta_key) {
    global $wpdb;

    $meta_key = (string) $meta_key;
    if ('' === $meta_key) {
        return true;
    }

    // Protected/internal keys.
    if (0 === strpos($meta_key, '_')) {
        return true;
    }

    $exact = array(
        'session_tokens', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl',
        'show_admin_bar_front', 'locale', 'community-events-location', 'dismissed_wp_pointers',
        'default_password_nag', 'user_pass', 'user_activation_key', 'user_status', 'user_level',
    );
    if (in_array($meta_key, $exact, true)) {
        return true;
    }

    // Per-site capability/settings keys: {prefix}capabilities, {prefix}user_level, ...
    $suffixes = array(
        'capabilities', 'user_level', 'user-settings', 'user-settings-time', 'persisted_preferences',
        'dashboard_quick_press_last_post_id',
    );
    $prefixes = array_unique(array($wpdb->base_prefix, $wpdb->get_blog_prefix()));
    foreach ($prefixes as $prefix) {
        foreach ($suffixes as $suffix) {
            if ($meta_key === $prefix . $suffix) {
                return true;
            }
        }
    }

    // Screen options and similar UI state.
    if (preg_match('/^(closedpostboxes_|metaboxhidden_|meta-box-order_|screen_layout_|manage.*columnshidden|managenav)/', $meta_key)) {
        return true;
    }

    return (bool) apply_filters('wpum_forbidden_meta_key', false, $meta_key);
}

// Meta keys the admin chose to expose on the profile page, minus the forbidden ones.
function wpum_get_allowed_user_meta_keys() {
    $configured = get_option('wp_user_management_metadata', array());
    if (!is_array($configured)) {
        return array();
    }

    $allowed = array();
    foreach ($configured as $meta_key) {
        if (is_string($meta_key) && !wpum_is_forbidden_meta_key($meta_key)) {
            $allowed[] = $meta_key;
        }
    }

    return array_values(array_unique($allowed));
}

// True when $user_id is the only remaining administrator.
function wpum_is_last_administrator($user_id) {
    $user = get_userdata($user_id);
    if (!$user || !in_array('administrator', (array) $user->roles, true)) {
        return false;
    }

    $others = get_users(array(
        'role'    => 'administrator',
        'fields'  => 'ID',
        'number'  => 1,
        'exclude' => array((int) $user_id),
    ));

    return empty($others);
}

// Who inherits content when an account is deleted. Default: the oldest other administrator.
function wpum_get_reassign_user_id($user_id) {
    $reassign = apply_filters('wpum_reassign_user_id', null, $user_id);
    if (null !== $reassign) {
        return $reassign ? (int) $reassign : null;
    }

    $admins = get_users(array(
        'role'    => 'administrator',
        'fields'  => 'ID',
        'number'  => 1,
        'orderby' => 'ID',
        'order'   => 'ASC',
        'exclude' => array((int) $user_id),
    ));

    return !empty($admins) ? (int) $admins[0] : null;
}

// Verify the current password of a user, counting failures towards the rate limit.
function wpum_verify_current_password($user_id, $password) {
    if (wpum_rate_limit_is_blocked('password_check', $user_id)) {
        return new WP_Error('rate_limited', 'rate_limited');
    }

    $user = get_userdata($user_id);
    // Passwords are stored hashed in the slashed form WordPress receives them in (see wpum_create_pending_registration).
    if ('' === $password || !$user || !wp_check_password(wp_slash($password), $user->user_pass, $user->ID)) {
        wpum_rate_limit_hit('password_check', $user_id);
        return new WP_Error('wrong_password', 'wrong_password');
    }

    wpum_rate_limit_clear('password_check', $user_id);

    return true;
}

if (!function_exists('wpum_create_tables')) {
    function wpum_create_tables() {
        try {
            // Wywołaj funkcję tworzącą tabelę shooting credentials
            if (function_exists('wpum_create_shooting_credentials_table')) {
                $result = wpum_create_shooting_credentials_table();
                if (!$result) {
                    throw new Exception("Nie udało się utworzyć lub zaktualizować tabeli shooting credentials");
                }
                return true;
            } else {
                throw new Exception("Funkcja wpum_create_shooting_credentials_table nie jest dostępna");
            }
        } catch (Exception $e) {
            wpum_log("Błąd podczas tworzenia tabel: " . $e->getMessage());
            return false;
        }
    }
}

// Funkcja pomocnicza do sprawdzania istnienia tabeli
if (!function_exists('wpum_table_exists')) {
    function wpum_table_exists($table_name) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name))) === $table_name;
    }
}
