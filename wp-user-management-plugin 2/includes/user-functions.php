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
?>