<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display password reset form
function wpum_display_password_reset_form() {
    wpum_enqueue_frontend_assets();

    $flash = wpum_flash_get('reset');
    $sent  = $flash && 'success' === $flash['type'];

    ob_start(); ?>
    <div class="wpum-ui wpum-ui--reset">
        <div class="wpum-card">
            <h2 class="wpum-title"><?php esc_html_e('Password Reset', 'wp-user-management-plugin'); ?></h2>

            <?php wpum_flash_render('reset'); ?>

            <?php if (!$sent) : ?>
                <form id="wpum-password-reset-form" method="post">
                    <?php wpum_form_fields('reset'); ?>

                    <?php
                    wpum_render_field(array(
                        'id'           => 'wpum-reset-email',
                        'name'         => 'wpum_email',
                        'type'         => 'email',
                        'label'        => __('Email Address', 'wp-user-management-plugin'),
                        'value'        => wpum_flash_old('reset', 'email'),
                        'required'     => true,
                        'autocomplete' => 'email',
                    ));
                    ?>

                    <?php wpum_turnstile_render('reset'); ?>

                    <button type="submit" class="wpum-btn wpum-btn--primary wpum-btn--block"><?php esc_html_e('Reset Password', 'wp-user-management-plugin'); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php
    return ob_get_clean();
}

// Handle the password reset request (previously done inside the shortcode renderer).
function wpum_process_password_reset() {
    if (!wpum_is_form_post('reset')) {
        return;
    }

    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('reset')) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'invalid_nonce');
    }

    $ip = wpum_get_client_ip();
    if (wpum_rate_limit_is_blocked('reset_ip', $ip)) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'rate_limited');
    }
    wpum_rate_limit_hit('reset_ip', $ip);

    $email = sanitize_email(wpum_post_text('wpum_email'));
    $old   = array('email' => $email);

    $turnstile = wpum_turnstile_verify('reset');
    if (is_wp_error($turnstile)) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', $turnstile->get_error_code(), $old);
    }

    if (!is_email($email)) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'invalid_email', $old);
    }

    // From here on the answer is always the same, whether or not the account exists (no user enumeration),
    // and repeated requests for one address are silently dropped (no email bombing).
    if (!wpum_rate_limit_is_blocked('reset_email', $email)) {
        wpum_rate_limit_hit('reset_email', $email);

        $user = get_user_by('email', $email);
        if ($user && apply_filters('allow_password_reset', true, $user->ID)) {
            wpum_send_password_reset_email($user);
        }
    }

    wpum_redirect_with_flash($return_url, 'reset', 'success', 'reset_sent');
}
add_action('template_redirect', 'wpum_process_password_reset');

// Build and send the reset link (same link and wording as WordPress core).
function wpum_send_password_reset_email($user) {
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        wpum_log('Błąd generowania klucza resetu hasła: ' . $key->get_error_message());
        return false;
    }

    $reset_link = network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');

    $message_body  = sprintf(__('Someone has requested a password reset for the following account: %s', 'wp-user-management-plugin'), network_home_url('/')) . "\r\n\r\n";
    $message_body .= __('If this was a mistake, just ignore this email and nothing will happen.', 'wp-user-management-plugin') . "\r\n\r\n";
    $message_body .= sprintf(__('To reset your password, visit the following address: %s', 'wp-user-management-plugin'), $reset_link) . "\r\n\r\n";
    $message_body .= __('This password reset request will expire in 24 hours.', 'wp-user-management-plugin') . "\r\n\r\n";
    $message_body .= sprintf(__('Thanks, %s', 'wp-user-management-plugin'), get_bloginfo('name')) . "\r\n\r\n";
    $message_body .= network_home_url('/') . "\r\n";

    $title = sprintf(__('[%s] Password Reset', 'wp-user-management-plugin'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));

    return wp_mail($user->user_email, $title, $message_body);
}
