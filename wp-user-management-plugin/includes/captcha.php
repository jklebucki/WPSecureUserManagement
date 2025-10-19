<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Custom CAPTCHA implementation for user registration, login, and password reset

// Ensure WordPress environment is loaded
if (!function_exists('plugin_dir_path')) {
    exit('WordPress environment not loaded.');
}

// Generuj CAPTCHA używając transients zamiast sesji
function wpum_generate_captcha() {
    $random_string = substr(str_shuffle("23456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 6);
    $captcha_token = wp_generate_password(32, false);
    
    // Zapisz kod CAPTCHA w transients z 5-minutowym czasem życia
    set_transient('wpum_captcha_' . $captcha_token, $random_string, 5 * MINUTE_IN_SECONDS);
    
    return array(
        'token' => $captcha_token,
        'code' => $random_string
    );
}

// Weryfikuj CAPTCHA
function wpum_verify_captcha($token, $user_input) {
    if (empty($token) || empty($user_input)) {
        return false;
    }
    
    $stored_code = get_transient('wpum_captcha_' . $token);
    if (!$stored_code) {
        return false;
    }
    
    // Usuń transient po sprawdzeniu
    delete_transient('wpum_captcha_' . $token);
    
    return strtoupper($user_input) === strtoupper($stored_code);
}

// Wyświetl CAPTCHA
function wpum_display_captcha() {
    $captcha = wpum_generate_captcha();
    ?>
    <div class="wpum-captcha-container">
        <input type="text" 
               name="wpum_captcha_code" 
               id="wpum-captcha-input" 
               placeholder="<?php _e('Enter CAPTCHA code', 'wp-user-management-plugin'); ?>" 
               required>
        <input type="hidden" 
               name="wpum_captcha_token" 
               value="<?php echo esc_attr($captcha['token']); ?>">
        <div class="wpum-captcha-code">
            <?php echo esc_html($captcha['code']); ?>
        </div>
    </div>
    <?php
}

// Sprawdź CAPTCHA w formularzu
function wpum_validate_captcha_submission() {
    if (!isset($_POST['wpum_captcha_code']) || !isset($_POST['wpum_captcha_token'])) {
        return false;
    }
    
    return wpum_verify_captcha(
        sanitize_text_field($_POST['wpum_captcha_token']),
        sanitize_text_field($_POST['wpum_captcha_code'])
    );
}

// Funkcja do sprawdzania CAPTCHA w formularzu rejestracji
function wpum_check_captcha($user_input) {
    if (!isset($_POST['wpum_captcha_token'])) {
        return false;
    }
    
    return wpum_verify_captcha(
        sanitize_text_field($_POST['wpum_captcha_token']),
        sanitize_text_field($user_input)
    );
}

// AJAX endpoint do generowania nowego CAPTCHA
function wpum_ajax_refresh_captcha() {
    $captcha = wpum_generate_captcha();
    wp_send_json_success(array(
        'token' => $captcha['token'],
        'code' => $captcha['code']
    ));
}
add_action('wp_ajax_wpum_refresh_captcha', 'wpum_ajax_refresh_captcha');
add_action('wp_ajax_nopriv_wpum_refresh_captcha', 'wpum_ajax_refresh_captcha');

// AJAX endpoint do weryfikacji CAPTCHA
function wpum_ajax_verify_captcha() {
    // Sprawdź nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpum_captcha_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed', 'wp-user-management-plugin')));
        return;
    }
    
    // Pobierz dane
    $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
    $user_input = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    
    // Weryfikuj CAPTCHA
    if (empty($token) || empty($user_input)) {
        wp_send_json_error(array('message' => __('Please enter the code', 'wp-user-management-plugin')));
        return;
    }
    
    $stored_code = get_transient('wpum_captcha_' . $token);
    if (!$stored_code) {
        wp_send_json_error(array('message' => __('Code expired. Please refresh', 'wp-user-management-plugin')));
        return;
    }
    
    // Sprawdź poprawność (bez usuwania transient - zostanie usunięty przy finalnej walidacji)
    if (strtoupper($user_input) === strtoupper($stored_code)) {
        wp_send_json_success(array('message' => __('Code is correct', 'wp-user-management-plugin')));
    } else {
        wp_send_json_error(array('message' => __('Incorrect code', 'wp-user-management-plugin')));
    }
}
add_action('wp_ajax_wpum_verify_captcha', 'wpum_ajax_verify_captcha');
add_action('wp_ajax_nopriv_wpum_verify_captcha', 'wpum_ajax_verify_captcha');
?>