<?php
// User Functions for User Management Plugin

// Retrieve user data by user ID
function sum_get_user_data($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    return [
        'ID' => $user->ID,
        'user_login' => $user->user_login, // native field
        'user_email' => $user->user_email, // native field
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
    ];
}

// Update user data
function sum_update_user_data($user_id, $data) {
    $user_data = [
        'ID' => $user_id,
        'user_email' => sanitize_email($data['email']),
        'first_name' => sanitize_text_field($data['first_name']),
        'last_name' => sanitize_text_field($data['last_name']),
    ];

    // Update user
    return wp_update_user($user_data);
}

// Change user password
function sum_change_user_password($user_id, $new_password) {
    return wp_set_password($new_password, $user_id);
}

// Delete user account
function sum_delete_user_account($user_id) {
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    return wp_delete_user($user_id);
}

// Check if username or email exists
function sum_user_exists($username_or_email) {
    if (username_exists($username_or_email)) {
        return true;
    }
    if (email_exists($username_or_email)) {
        return true;
    }
    return false;
}

// Validate password strength
function sum_validate_password_strength($password) {
    $min_length = get_option('wp_user_management_password_length', 8);
    if (strlen($password) < $min_length) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[\W_]/', $password)) {
        return false;
    }
    return true;
}

if (!function_exists('wpum_create_tables')) {
    function wpum_create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
        $charset_collate = $wpdb->get_charset_collate();

        // Sprawdź czy tabela już istnieje
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                credential_type varchar(50) NOT NULL,
                credential_number varchar(100) NOT NULL,
                file_path varchar(255) NOT NULL,
                uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id)
            ) $charset_collate;";
            
            dbDelta($sql);
            
            // Sprawdź czy tabela została utworzona
            if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wpum_log("Błąd podczas tworzenia tabeli: " . $wpdb->last_error);
                return false;
            }
            wpum_log("Tabela $table_name została utworzona pomyślnie");
        }
        return true;
    }
}

// Funkcja pomocnicza do sprawdzania istnienia tabeli
if (!function_exists('wpum_table_exists')) {
    function wpum_table_exists($table_name) {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }
}

// Dodaj na początku pliku, po sprawdzeniu ABSPATH
if (!function_exists('wpum_log')) {
    function wpum_log($message) {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}
?>