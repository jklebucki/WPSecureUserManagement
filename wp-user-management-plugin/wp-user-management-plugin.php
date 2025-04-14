<?php
/**
 * Plugin Name: WP User Management Plugin
 * Description: A plugin for user registration, login, and profile editing with enhanced security and a modern interface.
 * Version: 1.1.2
 * Author: Jarosław Kłębucki
 * Text Domain: wp-user-management-plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('MY_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Funkcja debugowania
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

// Inicjalizacja sesji
function wpum_init_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}
add_action('init', 'wpum_init_session', 1);

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/user-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/captcha.php';
require_once plugin_dir_path(__FILE__) . 'includes/register-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/login-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/profile-edit-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/password-reset-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/my-account.php';
require_once plugin_dir_path(__FILE__) . 'includes/shooting-credentials.php';

// Load text domain for translations
function wp_user_management_load_textdomain() {
    load_plugin_textdomain(
        'wp-user-management-plugin',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wp_user_management_load_textdomain');

// Register shortcodes for all forms
function wpum_register_shortcodes() {
    add_shortcode('wpum_user_registration', 'wpum_display_registration_form');
    add_shortcode('wpum_user_login', 'sum_display_login_form');
    add_shortcode('wpum_user_profile_edit', 'sum_display_profile_edit_form');
    add_shortcode('wpum_password_reset', 'sum_display_password_reset_form');
    add_shortcode('wpum_my_account_content', 'wpum_my_account_shortcode');
}
add_action('init', 'wpum_register_shortcodes');

// Register activation hook
function wp_user_management_activate() {
    if (!wpum_create_tables()) {
        wp_die('Nie udało się utworzyć wymaganych tabel w bazie danych. Sprawdź uprawnienia bazy danych i logi błędów.');
    }
}
register_activation_hook(__FILE__, 'wp_user_management_activate');

// Register deactivation hook
function wp_user_management_deactivate() {
    // Kod do wykonania podczas deaktywacji pluginu
    wpum_log('Plugin został deaktywowany');
}
register_deactivation_hook(__FILE__, 'wp_user_management_deactivate');
?>