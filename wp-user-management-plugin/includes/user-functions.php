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
        'role' => $user->roles[0]
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

// Validate password strength
function wpum_validate_password_strength($password) {
    // Minimum 8 znaków
    if (strlen($password) < 8) {
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