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
function generate_captcha() {
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
function verify_captcha($token, $user_input) {
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
function display_captcha() {
    $captcha = generate_captcha();
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
function validate_captcha_submission() {
    if (!isset($_POST['wpum_captcha_code']) || !isset($_POST['wpum_captcha_token'])) {
        return false;
    }
    
    return verify_captcha(
        sanitize_text_field($_POST['wpum_captcha_token']),
        sanitize_text_field($_POST['wpum_captcha_code'])
    );
}
?>