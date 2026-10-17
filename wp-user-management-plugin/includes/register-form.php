<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display registration form
function wpum_display_registration_form()
{
    wpum_enqueue_frontend_assets();

    $min_length = wpum_get_min_password_length();

    // After a successful submit we only show the "check your email" notice, not the form again.
    $flash = wpum_flash_get('register');
    if ($flash && 'success' === $flash['type']) {
        ob_start(); ?>
        <div class="wpum-ui wpum-ui--register">
            <div class="wpum-card">
                <h2 class="wpum-title"><?php esc_html_e('Check your email', 'wp-user-management-plugin'); ?></h2>
                <?php wpum_flash_render('register'); ?>
                <p class="wpum-help">
                    <?php
                    $days = wpum_registration_days();
                    echo esc_html(sprintf(
                        /* translators: %d: number of days */
                        _n('Unconfirmed registrations are deleted automatically after %d day.', 'Unconfirmed registrations are deleted automatically after %d days.', $days, 'wp-user-management-plugin'),
                        $days
                    ));
                    ?>
                    <?php esc_html_e('Nothing in your inbox? Look in the spam folder, or register again after a few minutes to get a new link.', 'wp-user-management-plugin'); ?>
                </p>
                <p class="wpum-links">
                    <a href="<?php echo esc_url(wpum_get_page_url('register')); ?>"><?php esc_html_e('Register again', 'wp-user-management-plugin'); ?></a>
                    <span aria-hidden="true">&middot;</span>
                    <a href="<?php echo esc_url(wpum_get_page_url('account')); ?>"><?php esc_html_e('Back to login', 'wp-user-management-plugin'); ?></a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start(); ?>
    <div class="wpum-ui wpum-ui--register">
        <form id="wpum-registration-form" class="wpum-card" method="post">
            <h2 class="wpum-title"><?php esc_html_e('User Registration', 'wp-user-management-plugin'); ?></h2>

            <?php wpum_flash_render('register'); ?>
            <?php wpum_form_fields('register'); ?>

            <?php
            wpum_render_field(array(
                'id'           => 'wpum-username',
                'name'         => 'wpum_username',
                'label'        => __('Username', 'wp-user-management-plugin'),
                'value'        => wpum_flash_old('register', 'username'),
                'required'     => true,
                'autocomplete' => 'username',
            ));
            wpum_render_field(array(
                'id'           => 'wpum-email',
                'name'         => 'wpum_email',
                'type'         => 'email',
                'label'        => __('Email', 'wp-user-management-plugin'),
                'value'        => wpum_flash_old('register', 'email'),
                'required'     => true,
                'autocomplete' => 'email',
            ));
            wpum_render_field(array(
                'id'           => 'wpum-firstname',
                'name'         => 'wpum_firstname',
                'label'        => __('First Name', 'wp-user-management-plugin'),
                'value'        => wpum_flash_old('register', 'firstname'),
                'required'     => true,
                'autocomplete' => 'given-name',
            ));
            wpum_render_field(array(
                'id'           => 'wpum-lastname',
                'name'         => 'wpum_lastname',
                'label'        => __('Last Name', 'wp-user-management-plugin'),
                'value'        => wpum_flash_old('register', 'lastname'),
                'required'     => true,
                'autocomplete' => 'family-name',
            ));
            wpum_render_field(array(
                'id'           => 'wpum-password',
                'name'         => 'wpum_password',
                'type'         => 'password',
                'label'        => __('Password', 'wp-user-management-plugin'),
                'required'     => true,
                'autocomplete' => 'new-password',
                'attrs'        => array('minlength' => $min_length),
                'help'         => sprintf(
                    /* translators: %d: minimum password length */
                    __('At least %d characters, with upper and lower case letters, a digit and a special character.', 'wp-user-management-plugin'),
                    $min_length
                ),
            ));
            wpum_render_strength_meter();
            wpum_render_field(array(
                'id'           => 'wpum-confirm-password',
                'name'         => 'wpum_confirm_password',
                'type'         => 'password',
                'label'        => __('Confirm Password', 'wp-user-management-plugin'),
                'required'     => true,
                'autocomplete' => 'new-password',
            ));
            wpum_render_select(array(
                'id'       => 'wpum-language',
                'name'     => 'wpum_language',
                'label'    => __('Preferred language', 'wp-user-management-plugin'),
                'options'  => wpum_get_available_locales(),
                'selected' => wpum_flash_old('register', 'locale', wpum_default_locale()),
                'help'     => __('The confirmation email is sent in this language.', 'wp-user-management-plugin'),
            ));
            ?>

            <?php wpum_turnstile_render('register'); ?>

            <button type="submit" class="wpum-btn wpum-btn--primary wpum-btn--block"><?php esc_html_e('Register', 'wp-user-management-plugin'); ?></button>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Handle user registration: validate, park the sign-up and email the confirmation link.
function wpum_process_registration()
{
    if (!wpum_is_form_post('register')) {
        return;
    }

    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('register')) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'invalid_nonce');
    }

    $ip = wpum_get_client_ip();
    if (wpum_rate_limit_is_blocked('register_ip', $ip)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'rate_limited');
    }
    wpum_rate_limit_hit('register_ip', $ip);

    $username  = sanitize_user(wpum_post_text('wpum_username'), true);
    $email     = sanitize_email(wpum_post_text('wpum_email'));
    $firstname = wpum_post_text('wpum_firstname');
    $lastname  = wpum_post_text('wpum_lastname');
    $password  = wpum_post_raw('wpum_password');
    $confirm   = wpum_post_raw('wpum_confirm_password');
    $locale    = wpum_sanitize_locale(wpum_post_text('wpum_language'));

    // Values we may show again after an error (never the passwords).
    $old = array(
        'username'  => $username,
        'email'     => $email,
        'firstname' => $firstname,
        'lastname'  => $lastname,
        'locale'    => $locale,
    );

    $turnstile = wpum_turnstile_verify('register');
    if (is_wp_error($turnstile)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', $turnstile->get_error_code(), $old);
    }

    if ('' === $username || '' === $email || '' === $firstname || '' === $lastname || '' === $password || '' === $confirm) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'fields_required', $old);
    }

    if (!is_email($email)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'invalid_email', $old);
    }

    if (!validate_username($username)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'invalid_username', $old);
    }

    if (username_exists($username) || email_exists($email)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'user_exists', $old);
    }

    if ($password !== $confirm) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'password_mismatch', $old);
    }

    if (!wpum_validate_password_strength($password)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'password_weak', $old);
    }

    // Protects the mailbox of whoever's address was typed in (also keeps the form from being a mail cannon).
    if (wpum_rate_limit_is_blocked('register_email', $email)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'rate_limited', $old);
    }
    wpum_rate_limit_hit('register_email', $email);

    $data = array(
        'login'      => $username,
        'email'      => $email,
        'first_name' => $firstname,
        'last_name'  => $lastname,
        'locale'     => $locale,
        'password'   => $password,
    );
    $pending = wpum_create_pending_registration($data);
    if (!$pending) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'register_failed', $old);
    }

    $data['expires'] = $pending['expires'];
    if (!wpum_send_activation_email($data, $pending['key'])) {
        wpum_delete_pending_registration($pending['key']);
        wpum_redirect_with_flash($return_url, 'register', 'error', 'mail_failed', $old);
    }

    wpum_redirect_with_flash($return_url, 'register', 'success', 'activation_sent', array('email' => $email));
}
add_action('template_redirect', 'wpum_process_registration');

// Native wp-login.php registration is handled by core; we only add Turnstile there (see turnstile.php).
