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
            ?>

            <?php wpum_turnstile_render('register'); ?>

            <button type="submit" class="wpum-btn wpum-btn--primary wpum-btn--block"><?php esc_html_e('Register', 'wp-user-management-plugin'); ?></button>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Handle user registration
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

    // Values we may show again after an error (never the passwords).
    $old = array(
        'username'  => $username,
        'email'     => $email,
        'firstname' => $firstname,
        'lastname'  => $lastname,
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

    // wp_create_user()/wp_update_user() unslash their input (WordPress passes slashed request data to them),
    // so the password has to be slashed or a backslash in it would be silently dropped.
    $user_id = wp_create_user($username, wp_slash($password), $email);
    if (is_wp_error($user_id)) {
        wpum_redirect_with_flash($return_url, 'register', 'error', 'register_failed', $old);
    }

    wp_update_user(wp_slash(array(
        'ID'         => $user_id,
        'first_name' => $firstname,
        'last_name'  => $lastname,
    )));

    // Send the visitor to the login page with a confirmation.
    wpum_redirect_with_flash(wpum_get_page_url('account'), 'login', 'success', 'registered');
}
add_action('template_redirect', 'wpum_process_registration');

// Native wp-login.php registration is handled by core; we only add Turnstile there (see turnstile.php).
