<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display password reset form
function wpum_display_password_reset_form() {
    // Enqueue the CSS file
    wp_enqueue_style('wpum-password-reset-form', plugins_url('password-reset-form.css', __FILE__));
    ob_start(); ?>
    <div class="wpum-password-reset-container">
        <form id="wpum-password-reset-form" method="post">
            <h2><?php _e('Password Reset', 'wp-user-management-plugin'); ?></h2>
            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <label for="wpum-email"><?php _e('Email Address', 'wp-user-management-plugin'); ?> *</label>
                    <input type="email" name="wpum_email" id="wpum-email" required>
                    <input type="hidden" name="wpum_password_reset_nonce" value="<?php echo wp_create_nonce('wpum_password_reset_nonce'); ?>">
                </div>
            </div>
            <div class="wpum-form-row">
                <div class="wpum-form-group">
                    <button type="submit"><?php _e('Reset Password', 'wp-user-management-plugin'); ?></button>
                </div>
            </div>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Process password reset
function wpum_process_password_reset() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_password_reset_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_password_reset_nonce'], 'wpum_password_reset_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $email = sanitize_email($_POST['wpum_email']);

        // Check if email exists
        if (!email_exists($email)) {
            wp_die(__('No user found with that email address.', 'wp-user-management-plugin'));
        }

        // Generate reset key
        $key = get_password_reset_key(get_user_by('email', $email));
        if (is_wp_error($key)) {
            wp_die(__('Error generating password reset key. Please try again.', 'wp-user-management-plugin'));
        }

        // Send reset email
        $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode(get_user_by('email', $email)->user_login), 'login');
        $message = sprintf(__('Someone has requested a password reset for the following account: %s', 'wp-user-management-plugin'), network_home_url('/')) . "\r\n\r\n";
        $message .= sprintf(__('If this was a mistake, just ignore this email and nothing will happen.', 'wp-user-management-plugin')) . "\r\n\r\n";
        $message .= sprintf(__('To reset your password, visit the following address: %s', 'wp-user-management-plugin'), $reset_link) . "\r\n\r\n";
        $message .= sprintf(__('If you don\'t wish to change your password, just ignore this email and nothing will happen.', 'wp-user-management-plugin')) . "\r\n\r\n";
        $message .= sprintf(__('This password reset request will expire in 24 hours.', 'wp-user-management-plugin')) . "\r\n\r\n";
        $message .= sprintf(__('Thanks, %s', 'wp-user-management-plugin'), get_bloginfo('name')) . "\r\n\r\n";
        $message .= network_home_url('/') . "\r\n";

        $title = sprintf(__('[%s] Password Reset', 'wp-user-management-plugin'), get_bloginfo('name'));
        if (wp_mail($email, $title, $message)) {
            wp_die(__('Password reset instructions have been sent to your email address.', 'wp-user-management-plugin'));
        } else {
            wp_die(__('Error sending password reset email. Please try again.', 'wp-user-management-plugin'));
        }
    }
}
add_action('init', 'wpum_process_password_reset');

// Register shortcode
function wpum_register_password_reset_shortcode() {
    add_shortcode('wpum_password_reset', 'wpum_display_password_reset_form');
}
add_action('init', 'wpum_register_password_reset_shortcode');
?>