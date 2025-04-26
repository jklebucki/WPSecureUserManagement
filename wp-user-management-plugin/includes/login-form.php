<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Include CAPTCHA functions
require_once plugin_dir_path(__FILE__) . 'captcha.php';

// Enqueue styles
function wpum_enqueue_login_styles()
{
    wp_enqueue_style('wpum-login-form', plugin_dir_url(__FILE__) . 'login-form.css');
}
add_action('wp_enqueue_scripts', 'wpum_enqueue_login_styles');

// Display login form
function wpum_display_login_form()
{
    ob_start(); ?>
    <div class="wpum-login-container">
        <form id="wpum-login-form" method="post">
            <h2><?php _e('User Login', 'wp-user-management-plugin'); ?></h2>
            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <label for="wpum-username-email"><?php _e('Username or Email', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="wpum_username_email" id="wpum-username-email" required>
                </div>
                <div class="wpum-form-group">
                    <label for="wpum-password"><?php _e('Password', 'wp-user-management-plugin'); ?> *</label>
                    <input type="password" name="wpum_password" id="wpum-password" required>
                    <input type="hidden" name="wpum_login_nonce" value="<?php echo wp_create_nonce('wpum_login_nonce'); ?>">
                </div>
            </div>
            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <button type="submit"><?php _e('Login', 'wp-user-management-plugin'); ?></button>
                </div>
                <div class="wpum-form-group">
                    <a href="<?php echo esc_url(home_url('/user-register')); ?>"><?php _e('Register', 'wp-user-management-plugin'); ?></a>
                </div>
                <div class="wpum-form-group">
                    <a href="<?php echo esc_url(home_url('/password-reset')); ?>"><?php _e('Forgot Password?', 'wp-user-management-plugin'); ?></a>
                </div>
            </div>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Handle user login
function wpum_process_login()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_login_nonce'])) {
        if (! wp_verify_nonce($_POST['wpum_login_nonce'], 'wpum_login_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $username_email = sanitize_text_field($_POST['wpum_username_email']);
        $password       = $_POST['wpum_password'];

        // Check if username or email exists
        if (!wpum_user_exists($username_email)) {
            wp_die(__('Invalid username or email.', 'wp-user-management-plugin'));
        }

        // Attempt to login
        $user = wp_signon(array(
            'user_login' => $username_email,
            'user_password' => $password,
            'remember' => true
        ));

        if (is_wp_error($user)) {
            wp_die(__('Invalid password.', 'wp-user-management-plugin'));
        }

        // Redirect to profile page
        wp_redirect(home_url('/user-account'));
        exit;
    }
}
add_action('init', 'wpum_process_login');
?>