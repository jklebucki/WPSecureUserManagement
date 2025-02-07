<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display password reset form
function sum_display_password_reset_form() {
    // Enqueue the CSS file
    wp_enqueue_style('sum-password-reset-form', plugins_url('password-reset-form.css', __FILE__));
    ob_start(); ?>
    <div class="sum-password-reset-container">
        <form id="sum-password-reset-form" method="post">
            <h2><?php _e('Password Reset', 'secure-user-management'); ?></h2>

            <div class="sum-form-group">
                <label for="sum-email"><?php _e('Email', 'secure-user-management'); ?> *</label>
                <input type="email" name="sum_email" id="sum-email" required>
            </div>

            <input type="hidden" name="sum_password_reset_nonce" value="<?php echo wp_create_nonce('sum_password_reset_nonce'); ?>">
            <button type="submit"><?php _e('Reset Password', 'secure-user-management'); ?></button>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Handle password reset request
function sum_process_password_reset() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_password_reset_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_password_reset_nonce'], 'sum_password_reset_nonce')) {
            wp_die(__('Security check failed!', 'secure-user-management'));
        }

        $email = sanitize_email($_POST['sum_email']);

        // Check if the email exists
        if (!email_exists($email)) {
            wp_die(__('No user found with this email address.', 'secure-user-management'));
        }

        // Generate password reset key
        $user = get_user_by('email', $email);
        $reset_key = get_password_reset_key($user);

        // Send password reset email
        $reset_url = add_query_arg(['key' => $reset_key, 'login' => rawurlencode($user->user_login)], wp_login_url());
        $message = __('Someone has requested a password reset for the following account:') . "\r\n\r\n";
        $message .= network_site_url() . "\r\n\r\n";
        $message .= __('Username:') . " " . $user->user_login . "\r\n\r\n";
        $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
        $message .= $reset_url . "\r\n";

        wp_mail($email, __('Password Reset Request', 'secure-user-management'), $message);

        // Redirect to login page with a message
        wp_redirect(wp_login_url() . '?checkemail=confirm');
        exit;
    }
}
add_action('init', 'sum_process_password_reset');

// Register shortcode
function sum_register_password_reset_shortcode() {
    add_shortcode('sum_password_reset', 'sum_display_password_reset_form');
}
add_action('init', 'sum_register_password_reset_shortcode');
?>