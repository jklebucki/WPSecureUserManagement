<?php

/**
 * Plugin Name: Secure User Management
 * Plugin URI: https://ajksoftware.pl/wordpress-plugins/secure-user-management
 * Description: A secure WordPress plugin for user registration, login, profile editing, and admin user management.
 * Version: 1.0.0
 * Author: Jarosław Kłębucki
 * Author URI: https://ajksoftware.pl
 * License: GPL2
 * Text Domain: secure-user-management
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/language-setup.php';
require_once plugin_dir_path(__FILE__) . 'includes/register-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/login-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/profile-editor.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-user-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/security.php';
require_once plugin_dir_path(__FILE__) . 'includes/password-reset.php';
require_once plugin_dir_path(__FILE__) . 'includes/captcha.php';

// Activation hook
function sum_activate()
{
    sum_create_pages();
    sum_create_language_directory();
}
register_activation_hook(__FILE__, 'sum_activate');

// Function to create plugin pages
function sum_create_pages()
{
    $pages = [
        'user-registration' => __('User Registration', 'secure-user-management'),
        'user-login' => __('User Login', 'secure-user-management'),
        'user-profile' => __('User Profile', 'secure-user-management'),
        'password-reset' => __('Password Reset', 'secure-user-management')
    ];

    foreach ($pages as $slug => $title) {
        if (get_page_by_path($slug) === null) {
            wp_insert_post([
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => '[sum_' . str_replace('-', '_', $slug) . ']',
                'post_status' => 'publish',
                'post_type' => 'page'
            ]);
        }
    }
}

// Shortcode registration
function sum_register_shortcodes()
{
    add_shortcode('sum_user_registration', 'sum_display_registration_form_with_captcha');
    add_shortcode('sum_user_login', 'sum_display_login_form');
    add_shortcode('sum_user_profile', 'sum_display_profile_editor');
    add_shortcode('sum_password_reset', 'sum_display_password_reset_form');
}
add_action('init', 'sum_register_shortcodes');

// Admin Menu Registration
function sum_admin_menu()
{
    add_menu_page(
        __('User Management', 'secure-user-management'),
        __('User Management', 'secure-user-management'),
        'manage_options',
        'sum_user_management',
        'sum_admin_user_list',
        'dashicons-admin-users',
        25
    );

    add_submenu_page(
        'sum_user_management',
        __('User List', 'secure-user-management'),
        __('User List', 'secure-user-management'),
        'manage_options',
        'sum_user_management',
        'sum_admin_user_list'
    );

    add_submenu_page(
        'sum_user_management',
        __('Settings', 'secure-user-management'),
        __('Settings', 'secure-user-management'),
        'manage_options',
        'sum_settings',
        'sum_admin_settings'
    );
}
add_action('admin_menu', 'sum_admin_menu');

// Placeholder functions for admin pages
function sum_admin_user_list()
{
    echo '<div class="wrap"><h1>' . __('User List', 'secure-user-management') . '</h1></div>';
}

function sum_admin_settings()
{
    echo '<div class="wrap"><h1>' . __('Settings', 'secure-user-management') . '</h1></div>';
}

// Function to display registration form with captcha
function sum_display_registration_form_with_captcha()
{
    ob_start();
    ?>
    <form method="post" action="">
        <!-- ...existing form fields... -->
        <p>
            <label for="captcha"><?php _e('Captcha', 'secure-user-management'); ?></label>
            <img src="<?php echo plugin_dir_url(__FILE__) . 'includes/captcha.php'; ?>" alt="CAPTCHA">
            <input type="text" name="captcha" id="captcha" required>
        </p>
        <p>
            <input type="submit" name="submit" value="<?php _e('Register', 'secure-user-management'); ?>">
        </p>
    </form>
    <?php
    return ob_get_clean();
}

// Function to validate captcha
function sum_validate_captcha($captcha_input)
{
    if (isset($_SESSION['sum_captcha']) && $_SESSION['sum_captcha'] === $captcha_input) {
        return true;
    }
    return false;
}

// Hook into form submission to validate captcha
function sum_handle_registration_form_submission()
{
    if (isset($_POST['submit'])) {
        if (!sum_validate_captcha($_POST['captcha'])) {
            wp_die(__('Captcha validation failed. Please try again.', 'secure-user-management'));
        }
        // ...existing form handling code...
    }
}
add_action('init', 'sum_handle_registration_form_submission');
