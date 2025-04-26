<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display password reset form
function wpum_display_password_reset_form() {
    // Enqueue the CSS file
    wp_enqueue_style('wpum-password-reset-form', plugins_url('password-reset-form.css', __FILE__));
    
    // Check if form was submitted and processed
    $message = '';
    $message_type = '';
    
    if (isset($_POST['wpum_password_reset_nonce']) && wp_verify_nonce($_POST['wpum_password_reset_nonce'], 'wpum_password_reset_nonce')) {
        $email = sanitize_email($_POST['wpum_email']);
        
        // Check if email exists
        if (!email_exists($email)) {
            $message = __('No user found with that email address.', 'wp-user-management-plugin');
            $message_type = 'error';
        } else {
            // Generate reset key
            $key = get_password_reset_key(get_user_by('email', $email));
            if (is_wp_error($key)) {
                $message = __('Error generating password reset key. Please try again.', 'wp-user-management-plugin');
                $message_type = 'error';
            } else {
                // Send reset email
                $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode(get_user_by('email', $email)->user_login), 'login');
                $message_body = sprintf(__('Someone has requested a password reset for the following account: %s', 'wp-user-management-plugin'), network_home_url('/')) . "\r\n\r\n";
                $message_body .= __('If this was a mistake, just ignore this email and nothing will happen.', 'wp-user-management-plugin') . "\r\n\r\n";
                $message_body .= sprintf(__('To reset your password, visit the following address: %s', 'wp-user-management-plugin'), $reset_link) . "\r\n\r\n";
                $message_body .= __('If you don\'t wish to change your password, just ignore this email and nothing will happen.', 'wp-user-management-plugin') . "\r\n\r\n";
                $message_body .= __('This password reset request will expire in 24 hours.', 'wp-user-management-plugin') . "\r\n\r\n";
                $message_body .= sprintf(__('Thanks, %s', 'wp-user-management-plugin'), get_bloginfo('name')) . "\r\n\r\n";
                $message_body .= network_home_url('/') . "\r\n";

                $title = sprintf(__('[%s] Password Reset', 'wp-user-management-plugin'), get_bloginfo('name'));
                if (wp_mail($email, $title, $message_body)) {
                    $message = __('Password reset instructions have been sent to your email address.', 'wp-user-management-plugin');
                    $message_type = 'success';
                } else {
                    $message = __('Error sending password reset email. Please try again.', 'wp-user-management-plugin');
                    $message_type = 'error';
                }
            }
        }
    }
    
    ob_start(); ?>
    <div class="wpum-password-reset-container">
        <h2><?php _e('Password Reset', 'wp-user-management-plugin'); ?></h2>
        
        <?php if (!empty($message)): ?>
            <div class="wpum-message wpum-message-<?php echo esc_attr($message_type); ?>">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($message) || $message_type === 'error'): ?>
            <form id="wpum-password-reset-form" method="post">
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
        <?php endif; ?>
    </div>
<?php
    return ob_get_clean();
}

// Remove the old process function since we've integrated it into the display function
// function wpum_process_password_reset() { ... }
// add_action('init', 'wpum_process_password_reset');
?>