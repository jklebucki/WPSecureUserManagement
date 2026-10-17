<?php
/**
 * Plugin Name: WP User Management Plugin
 * Description: A plugin for user registration, login, and profile editing with enhanced security and a modern interface.
 * Version: 1.4.1
 * Author: Jarosław Kłębucki
 * Text Domain: wp-user-management-plugin
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('WPUM_VERSION', '1.4.1');
define('WPUM_DB_VERSION', '1.2'); // Wersja struktury bazy danych
define('WPUM_PLUGIN_FILE', __FILE__);
define('WPUM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPUM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Funkcja debugowania (tylko gdy WP_DEBUG i WP_DEBUG_LOG są włączone). Nie przekazuj tu danych osobowych.
if (!function_exists('wpum_log')) {
    function wpum_log($message) {
        if (!(defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)) {
            return;
        }
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

// Include necessary files
require_once WPUM_PLUGIN_DIR . 'includes/user-functions.php';
require_once WPUM_PLUGIN_DIR . 'includes/helpers.php';
require_once WPUM_PLUGIN_DIR . 'includes/notices.php';
require_once WPUM_PLUGIN_DIR . 'includes/rate-limit.php';
require_once WPUM_PLUGIN_DIR . 'includes/turnstile.php';
require_once WPUM_PLUGIN_DIR . 'includes/shooting-credentials.php';
require_once WPUM_PLUGIN_DIR . 'includes/privacy.php';
require_once WPUM_PLUGIN_DIR . 'includes/registration-confirmation.php';
require_once WPUM_PLUGIN_DIR . 'includes/register-form.php';
require_once WPUM_PLUGIN_DIR . 'includes/login-form.php';
require_once WPUM_PLUGIN_DIR . 'includes/profile-edit-form.php';
require_once WPUM_PLUGIN_DIR . 'includes/password-reset-form.php';
require_once WPUM_PLUGIN_DIR . 'includes/admin-menu.php';
require_once WPUM_PLUGIN_DIR . 'includes/admin-turnstile.php';
require_once WPUM_PLUGIN_DIR . 'includes/my-account.php';
require_once WPUM_PLUGIN_DIR . 'includes/login-override.php';
require_once WPUM_PLUGIN_DIR . 'includes/db-migrations.php';

// Load text domain for translations
function wp_user_management_load_textdomain() {
    load_plugin_textdomain(
        'wp-user-management-plugin',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wp_user_management_load_textdomain');

// Register shortcodes
function wpum_register_shortcodes() {
    add_shortcode('wpum_user_registration', 'wpum_display_registration_form');
    add_shortcode('wpum_user_login', 'wpum_display_login_form');
    add_shortcode('wpum_user_profile_edit', 'wpum_display_profile_edit_form');
    add_shortcode('wpum_password_reset', 'wpum_display_password_reset_form');
    add_shortcode('wpum_my_account_content', 'wpum_my_account_shortcode');
}
add_action('init', 'wpum_register_shortcodes');

// Sprawdza wersję wtyczki/bazy i wykonuje migracje, gdy są potrzebne
function wpum_check_version() {
    try {
        $current_version = get_option('wpum_version', '0');
        $current_db_version = get_option('wpum_db_version', '0');

        if (version_compare($current_version, WPUM_VERSION, '<') ||
            version_compare($current_db_version, WPUM_DB_VERSION, '<')) {

            wpum_log("Wykryto nową wersję pluginu lub bazy danych - rozpoczynam aktualizację");

            if (!wpum_update_db_schema()) {
                throw new Exception("Nie udało się zaktualizować schematu bazy danych");
            }

            update_option('wpum_version', WPUM_VERSION);
            update_option('wpum_db_version', WPUM_DB_VERSION);

            wpum_log("Aktualizacja pluginu zakończona pomyślnie");
        }

        return true;
    } catch (Exception $e) {
        wpum_log("Błąd podczas aktualizacji pluginu: " . $e->getMessage());
        return false;
    }
}
add_action('plugins_loaded', 'wpum_check_version', 20);

// Wykonuje migracje bazy danych; zwraca true tylko gdy wszystkie się powiodły
function wpum_update_db_schema() {
    return wpum_run_migrations();
}

// Aktywacja wtyczki
function wp_user_management_activate() {
    if (!wpum_create_tables()) {
        wp_die(esc_html__('Could not create the required database tables. Check database permissions and the error log.', 'wp-user-management-plugin'));
    }

    // Prywatny katalog na dokumenty musi istnieć przed pierwszym uploadem
    wpum_ensure_private_dir();

    // Zadanie WP-Cron usuwające niepotwierdzone rejestracje
    wpum_schedule_registration_cleanup();

    update_option('wpum_version', WPUM_VERSION);
    update_option('wpum_db_version', WPUM_DB_VERSION);
}
register_activation_hook(__FILE__, 'wp_user_management_activate');

// Dezaktywacja wtyczki
function wp_user_management_deactivate() {
    wpum_unschedule_registration_cleanup();
    wpum_log('Plugin został deaktywowany');
}
register_deactivation_hook(__FILE__, 'wp_user_management_deactivate');
