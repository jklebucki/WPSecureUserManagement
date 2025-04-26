<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue styles and scripts
function wpum_enqueue_register_styles() {
    wp_enqueue_style('wpum-register-form', plugin_dir_url(__FILE__) . 'register-form.css');
    wp_enqueue_script('wpum-password-strength', plugin_dir_url(__FILE__) . 'password-strength.js', ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'wpum_enqueue_register_styles');

// Display registration form
function wpum_display_registration_form()
{
    ob_start(); ?>
    <div class="wpum-registration-container">
        <form id="wpum-registration-form" method="post">
            <h2><?php _e('User Registration', 'wp-user-management-plugin'); ?></h2>

            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <label for="wpum-username"><?php _e('Username', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="wpum_username" id="wpum-username" required>
                </div>
                <div class="wpum-form-group">
                    <label for="wpum-email"><?php _e('Email', 'wp-user-management-plugin'); ?> *</label>
                    <input type="email" name="wpum_email" id="wpum-email" required>
                </div>
            </div>

            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <label for="wpum-firstname"><?php _e('First Name', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="wpum_firstname" id="wpum-firstname" required>
                </div>
                <div class="wpum-form-group">
                    <label for="wpum-lastname"><?php _e('Last Name', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="wpum_lastname" id="wpum-lastname" required>
                </div>
            </div>

            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <label for="wpum-password"><?php _e('Password', 'wp-user-management-plugin'); ?> *</label>
                    <input type="password" name="wpum_password" id="wpum-password" required>
                    <div id="password-strength-meter"></div>
                </div>
                <div class="wpum-form-group">
                    <label for="wpum-confirm-password"><?php _e('Confirm Password', 'wp-user-management-plugin'); ?> *</label>
                    <input type="password" name="wpum_confirm_password" id="wpum-confirm-password" required>
                </div>
            </div>

            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <label for="wpum-captcha"><?php _e('Enter the code', 'wp-user-management-plugin'); ?> *</label>
                    <div class="wpum-captcha-container">
                        <?php 
                        $captcha = wpum_generate_captcha();
                        echo '<div class="wpum-captcha-code">' . esc_html($captcha['code']) . '</div>';
                        ?>
                        <input type="text" name="wpum_captcha" id="wpum-captcha" required>
                        <input type="hidden" name="wpum_captcha_token" value="<?php echo esc_attr($captcha['token']); ?>">
                        <input type="hidden" name="wpum_register_nonce" value="<?php echo wp_create_nonce('wpum_register_nonce'); ?>">
                    </div>
                </div>
            </div>
            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <button type="submit"><?php _e('Register', 'wp-user-management-plugin'); ?></button>
                </div>
            </div>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Handle user registration
function wpum_process_registration()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_register_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_register_nonce'], 'wpum_register_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $username = sanitize_user($_POST['wpum_username']);
        $email = sanitize_email($_POST['wpum_email']);
        $firstname = sanitize_text_field($_POST['wpum_firstname']);
        $lastname = sanitize_text_field($_POST['wpum_lastname']);
        $password = $_POST['wpum_password'];
        $confirm_password = $_POST['wpum_confirm_password'];
        $captcha = sanitize_text_field($_POST['wpum_captcha']);

        // Check required fields
        if (empty($username) || empty($email) || empty($firstname) || empty($lastname) || empty($password) || empty($confirm_password) || empty($captcha)) {
            wp_die(__('All fields are required.', 'wp-user-management-plugin'));
        }

        // Validate email address
        if (!is_email($email)) {
            wp_die(__('Invalid email format.', 'wp-user-management-plugin'));
        }

        // Check if user already exists
        if (username_exists($username) || email_exists($email)) {
            wp_die(__('Username or email already taken.', 'wp-user-management-plugin'));
        }

        // Validate passwords
        if ($password !== $confirm_password) {
            wp_die(__('Passwords do not match.', 'wp-user-management-plugin'));
        }

        // Validate password strength
        if (!wpum_validate_password_strength($password)) {
            wp_die(__('Password does not meet the strength requirements.', 'wp-user-management-plugin'));
        }

        // Check CAPTCHA
        if (!wpum_check_captcha($captcha)) {
            wp_die(__('Invalid CAPTCHA.', 'wp-user-management-plugin'));
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_die(__('Registration failed. Please try again.', 'wp-user-management-plugin'));
        }

        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $firstname,
            'last_name' => $lastname
        ]);

        // Redirect to login page
        wp_redirect(home_url('/user-login'));
        exit;
    }
}
add_action('init', 'wpum_process_registration');
?>