<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Display login form
function wpum_display_login_form()
{
    wpum_enqueue_frontend_assets();

    ob_start(); ?>
    <div class="wpum-ui wpum-ui--login">
        <form id="wpum-login-form" class="wpum-card" method="post">
            <h2 class="wpum-title"><?php esc_html_e('User Login', 'wp-user-management-plugin'); ?></h2>

            <?php wpum_flash_render('login'); ?>
            <?php wpum_form_fields('login'); ?>

            <?php
            wpum_render_field(array(
                'id'           => 'wpum-username-email',
                'name'         => 'wpum_username_email',
                'label'        => __('Username or Email', 'wp-user-management-plugin'),
                'value'        => wpum_flash_old('login', 'login'),
                'required'     => true,
                'autocomplete' => 'username',
            ));
            wpum_render_field(array(
                'id'           => 'wpum-login-password',
                'name'         => 'wpum_password',
                'type'         => 'password',
                'label'        => __('Password', 'wp-user-management-plugin'),
                'required'     => true,
                'autocomplete' => 'current-password',
            ));
            ?>

            <label class="wpum-check">
                <input type="checkbox" name="wpum_remember" value="1" checked>
                <span><?php esc_html_e('Remember me', 'wp-user-management-plugin'); ?></span>
            </label>

            <?php wpum_turnstile_render('login'); ?>

            <button type="submit" class="wpum-btn wpum-btn--primary wpum-btn--block"><?php esc_html_e('Login', 'wp-user-management-plugin'); ?></button>

            <p class="wpum-links">
                <a href="<?php echo esc_url(wpum_get_page_url('register')); ?>"><?php esc_html_e('Register', 'wp-user-management-plugin'); ?></a>
                <span aria-hidden="true">&middot;</span>
                <a href="<?php echo esc_url(wpum_get_page_url('reset')); ?>"><?php esc_html_e('Forgot Password?', 'wp-user-management-plugin'); ?></a>
            </p>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Handle user login
function wpum_process_login()
{
    if (!wpum_is_form_post('login')) {
        return;
    }

    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('login')) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'invalid_nonce');
    }

    $login    = wpum_post_text('wpum_username_email');
    $password = wpum_post_raw('wpum_password');
    $ip       = wpum_get_client_ip();
    $old      = array('login' => $login);

    if (wpum_rate_limit_is_blocked('login_ip', $ip) || wpum_rate_limit_is_blocked('login', $ip . '|' . $login)) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'rate_limited', $old);
    }

    $turnstile = wpum_turnstile_verify('login');
    if (is_wp_error($turnstile)) {
        wpum_redirect_with_flash($return_url, 'login', 'error', $turnstile->get_error_code(), $old);
    }

    // One generic message for every failure: never reveal whether the account exists.
    if ('' === $login || '' === $password) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'login_failed', $old);
    }

    $user = wp_signon(array(
        'user_login'    => $login,
        'user_password' => $password,
        'remember'      => !empty($_POST['wpum_remember']),
    ));

    if (is_wp_error($user)) {
        wpum_rate_limit_hit('login', $ip . '|' . $login);
        wpum_rate_limit_hit('login_ip', $ip);
        wpum_redirect_with_flash($return_url, 'login', 'error', 'login_failed', $old);
    }

    wpum_rate_limit_clear('login', $ip . '|' . $login);

    // Redirect to profile page
    wp_safe_redirect(wpum_get_page_url('account'));
    exit;
}
add_action('template_redirect', 'wpum_process_login');
