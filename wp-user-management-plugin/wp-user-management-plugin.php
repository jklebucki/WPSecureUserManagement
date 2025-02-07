<?php
/**
 * Plugin Name: WP User Management Plugin
 * Description: A plugin for user registration, login, and profile editing with enhanced security and a modern interface.
 * Version: 1.0
 * Author: Your Name
 * Text Domain: wp-user-management-plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/captcha.php';
require_once plugin_dir_path(__FILE__) . 'includes/register-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/login-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/profile-edit-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/password-reset-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/user-functions.php';

// Load text domain for translations
function wp_user_management_load_textdomain() {
    load_plugin_textdomain('wp-user-management-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'wp_user_management_load_textdomain');

// Register activation hook
function wp_user_management_activate() {
    // Code to run on plugin activation
}
register_activation_hook(__FILE__, 'wp_user_management_activate');

// Register deactivation hook
function wp_user_management_deactivate() {
    // Code to run on plugin deactivation
}
register_deactivation_hook(__FILE__, 'wp_user_management_deactivate');
?>