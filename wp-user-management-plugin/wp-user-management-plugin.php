<?php
/**
 * Plugin Name: WP User Management Plugin
 * Description: A plugin for user registration, login, and profile editing with enhanced security and a modern interface.
 * Version: 1.1.3
 * Author: Jarosław Kłębucki
 * Text Domain: wp-user-management-plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('MY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPUM_VERSION', '1.1.2');
define('WPUM_DB_VERSION', '1.0'); // Wersja struktury bazy danych

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

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/captcha.php';
require_once plugin_dir_path(__FILE__) . 'includes/register-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/login-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/profile-edit-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/password-reset-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/user-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/my-account.php';
require_once plugin_dir_path(__FILE__) . 'includes/shooting-credentials.php';
require_once plugin_dir_path(__FILE__) . 'includes/db-migrations.php';

// Load text domain for translations
function wp_user_management_load_textdomain() {
    load_plugin_textdomain(
        'wp-user-management-plugin',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wp_user_management_load_textdomain');

// Enqueue scripts and styles
function wpum_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'wpumAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpum_shooting_credentials')
    ));
}
add_action('wp_enqueue_scripts', 'wpum_enqueue_scripts');

// Register shortcodes
function wpum_register_shortcodes() {
    add_shortcode('wpum_user_registration', 'wpum_display_registration_form');
    add_shortcode('wpum_user_login', 'wpum_display_login_form');
    add_shortcode('wpum_user_profile_edit', 'wpum_display_profile_edit_form');
    add_shortcode('wpum_password_reset', 'wpum_display_password_reset_form');
    add_shortcode('wpum_my_account_content', 'wpum_my_account_shortcode');
}
add_action('init', 'wpum_register_shortcodes');

// Dodaj funkcję sprawdzającą i wykonującą aktualizacje
function wpum_check_version() {
    try {
        $current_version = get_option('wpum_version', '0');
        $current_db_version = get_option('wpum_db_version', '0');
        
        wpum_log("Sprawdzanie wersji pluginu - Aktualna: {$current_version}, Nowa: " . WPUM_VERSION);
        wpum_log("Sprawdzanie wersji DB - Aktualna: {$current_db_version}, Nowa: " . WPUM_DB_VERSION);
        
        if (version_compare($current_version, WPUM_VERSION, '<') || 
            version_compare($current_db_version, WPUM_DB_VERSION, '<')) {
            
            wpum_log("Wykryto nową wersję pluginu lub bazy danych - rozpoczynam aktualizację");
            
            // Wykonaj aktualizacje bazy danych
            $update_result = wpum_update_db_schema();
            if (!$update_result) {
                throw new Exception("Nie udało się zaktualizować schematu bazy danych");
            }
            
            // Zaktualizuj wersje w opcjach
            update_option('wpum_version', WPUM_VERSION);
            update_option('wpum_db_version', WPUM_DB_VERSION);
            
            wpum_log("Aktualizacja pluginu zakończona pomyślnie");
        } else {
            wpum_log("Plugin jest aktualny - nie wymaga aktualizacji");
        }
        
        return true;
    } catch (Exception $e) {
        wpum_log("Błąd podczas aktualizacji pluginu: " . $e->getMessage());
        return false;
    }
}
add_action('plugins_loaded', 'wpum_check_version');

// Dodaj funkcję aktualizacji schematu bazy danych
function wpum_update_db_schema() {
    wpum_run_migrations();
}

// Zmodyfikuj funkcję aktywacji
function wp_user_management_activate() {
    if (!wpum_create_tables()) {
        wp_die('Nie udało się utworzyć wymaganych tabel w bazie danych. Sprawdź uprawnienia bazy danych i logi błędów.');
    }
    
    // Ustaw początkowe wersje
    update_option('wpum_version', WPUM_VERSION);
    update_option('wpum_db_version', WPUM_DB_VERSION);
}

// Zarejestruj hook aktywacji pluginu
register_activation_hook(__FILE__, 'wp_user_management_activate');

// Dodaj funkcję dezaktywacji (opcjonalnie)
function wp_user_management_deactivate() {
    // Możesz dodać kod czyszczący, jeśli jest potrzebny
    wpum_log('Plugin został deaktywowany');
}
register_deactivation_hook(__FILE__, 'wp_user_management_deactivate');
?>