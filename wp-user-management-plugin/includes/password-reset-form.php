<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Password reset on the plugin's own page ([wpum_password_reset]):
 *  1. the visitor asks for a link (email address),
 *  2. an HTML email in the account's language carries a link to this page (?wpum_reset=KEY&login=LOGIN),
 *  3. the link is checked, the key moves into a short-lived cookie and out of the URL (as WordPress does),
 *  4. the same page shows the "choose a new password" form.
 */

/* ------------------------------------------------------------------ */
/* Reset session (key in a cookie, not in the URL)                     */
/* ------------------------------------------------------------------ */

function wpum_reset_cookie_name() {
    return 'wpum-resetpass-' . COOKIEHASH;
}

function wpum_reset_cookie_path() {
    $path = wp_parse_url(wpum_get_page_url('reset'), PHP_URL_PATH);

    return $path ? $path : '/';
}

function wpum_clear_reset_cookie() {
    setcookie(wpum_reset_cookie_name(), ' ', time() - YEAR_IN_SECONDS, wpum_reset_cookie_path(), COOKIE_DOMAIN, is_ssl(), true);
    unset($_COOKIE[wpum_reset_cookie_name()]);
}

/**
 * The account whose reset link was opened in this browser: array( user, key ), or null when there is none, it expired
 * or it was already used.
 */
function wpum_get_reset_session() {
    $name = wpum_reset_cookie_name();
    if (empty($_COOKIE[$name]) || !is_string($_COOKIE[$name])) {
        return null;
    }

    $parts = explode(':', wp_unslash($_COOKIE[$name]), 2);
    if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
        return null;
    }

    $user = check_password_reset_key($parts[1], $parts[0]);
    if (is_wp_error($user)) {
        return null;
    }

    return array('user' => $user, 'key' => $parts[1]);
}

// Opening the emailed link: validate it, keep the key in a cookie and drop it from the address bar.
function wpum_process_reset_link() {
    if (!isset($_GET['wpum_reset']) || !is_string($_GET['wpum_reset'])) {
        return;
    }

    $reset_url = wpum_get_page_url('reset');
    $ip        = wpum_get_rate_limit_ip();

    if (wpum_rate_limit_is_blocked('reset_link', $ip)) {
        wpum_redirect_with_flash($reset_url, 'reset', 'error', 'rate_limited');
    }
    wpum_rate_limit_hit('reset_link', $ip);

    $key   = preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['wpum_reset']));
    $login = isset($_GET['login']) && is_string($_GET['login']) ? wp_unslash($_GET['login']) : '';
    $user  = ('' !== $key && '' !== $login) ? check_password_reset_key($key, $login) : new WP_Error('invalid_key');

    if (is_wp_error($user)) {
        wpum_clear_reset_cookie();
        wpum_redirect_with_flash($reset_url, 'reset', 'error', 'reset_link_invalid');
    }

    setcookie(wpum_reset_cookie_name(), $login . ':' . $key, 0, wpum_reset_cookie_path(), COOKIE_DOMAIN, is_ssl(), true);
    wp_safe_redirect($reset_url);
    exit;
}
add_action('template_redirect', 'wpum_process_reset_link', 5);

/* ------------------------------------------------------------------ */
/* The page                                                            */
/* ------------------------------------------------------------------ */

// Display the password reset page: the "new password" form when a reset link was opened, otherwise the request form.
function wpum_display_password_reset_form() {
    wpum_enqueue_frontend_assets();

    $session = wpum_get_reset_session();
    if ($session) {
        // The page depends on a cookie: page caches must not store it.
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        return wpum_render_new_password_form($session['user']);
    }

    $flash = wpum_flash_get('reset');
    $sent  = $flash && 'success' === $flash['type'] && 'reset_sent' === $flash['code'];

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

// The "choose a new password" form (shown after the emailed link was opened).
function wpum_render_new_password_form($user) {
    $min_length = wpum_get_min_password_length();

    ob_start(); ?>
    <div class="wpum-ui wpum-ui--reset">
        <form id="wpum-new-password-form" class="wpum-card" method="post">
            <h2 class="wpum-title"><?php esc_html_e('Choose a new password', 'wp-user-management-plugin'); ?></h2>

            <?php wpum_flash_render('reset'); ?>
            <?php wpum_form_fields('newpass'); ?>

            <p class="wpum-help">
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: username */
                    __('Setting a new password for %s.', 'wp-user-management-plugin'),
                    $user->user_login
                ));
                ?>
            </p>

            <?php
            wpum_render_field(array(
                'id'           => 'wpum-password',
                'name'         => 'wpum_password',
                'type'         => 'password',
                'label'        => __('New Password', 'wp-user-management-plugin'),
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
                'label'        => __('Confirm New Password', 'wp-user-management-plugin'),
                'required'     => true,
                'autocomplete' => 'new-password',
            ));
            ?>
            <div class="wpum-match" aria-live="polite"></div>

            <button type="submit" class="wpum-btn wpum-btn--primary wpum-btn--block" data-wpum-match disabled><?php esc_html_e('Save password', 'wp-user-management-plugin'); ?></button>
        </form>
    </div>
<?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Handlers                                                            */
/* ------------------------------------------------------------------ */

// Handle the password reset request.
function wpum_process_password_reset() {
    if (!wpum_is_form_post('reset')) {
        return;
    }

    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('reset')) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'invalid_nonce');
    }

    $ip = wpum_get_rate_limit_ip();
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

// Handle the "choose a new password" form.
function wpum_process_new_password() {
    if (!wpum_is_form_post('newpass')) {
        return;
    }

    $return_url = wpum_get_page_url('reset');

    if (!wpum_verify_form_nonce('newpass')) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'invalid_nonce');
    }

    $ip = wpum_get_rate_limit_ip();
    if (wpum_rate_limit_is_blocked('reset_link', $ip)) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'rate_limited');
    }
    wpum_rate_limit_hit('reset_link', $ip);

    $session = wpum_get_reset_session();
    if (!$session) {
        wpum_clear_reset_cookie();
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'reset_link_invalid');
    }

    $password = wpum_post_raw('wpum_password');
    $confirm  = wpum_post_raw('wpum_confirm_password');

    if ('' === $password || '' === $confirm) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'fields_required');
    }
    if ($password !== $confirm) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'password_mismatch');
    }
    if (!wpum_validate_password_strength($password)) {
        wpum_redirect_with_flash($return_url, 'reset', 'error', 'password_weak');
    }

    // reset_password() sets the password (and fires the same hooks as wp-login.php, e.g. the admin notification).
    // The slashed form is what WordPress hashes and compares everywhere else.
    reset_password($session['user'], wp_slash($password));

    // The old password may have been compromised: end every session of the account.
    WP_Session_Tokens::get_instance($session['user']->ID)->destroy_all();
    wpum_clear_reset_cookie();

    wpum_redirect_with_flash(wpum_get_page_url('account'), 'login', 'success', 'password_reset_done');
}
add_action('template_redirect', 'wpum_process_new_password');

/* ------------------------------------------------------------------ */
/* The email                                                           */
/* ------------------------------------------------------------------ */

// Link to the plugin's reset page, or WordPress's own screen when the page is not usable.
function wpum_password_reset_url($user, $key) {
    if (wpum_override_page_ready('reset')) {
        return add_query_arg(
            array('wpum_reset' => rawurlencode($key), 'login' => rawurlencode($user->user_login)),
            wpum_get_page_url('reset')
        );
    }

    return network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');
}

/**
 * Build the reset email in the account's language.
 *
 * @return array subject, html, text
 */
function wpum_build_password_reset_email($user, $url) {
    $locale = wpum_locale_for_user($user);
    $site   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $first  = (string) get_user_meta($user->ID, 'first_name', true);
    $name   = '' !== $first ? $first : $user->user_login;
    $hours  = max(1, (int) round((int) apply_filters('password_reset_expiration', DAY_IN_SECONDS) / HOUR_IN_SECONDS));

    $s = array(
        'lang'      => str_replace('_', '-', $locale),
        'site'      => $site,
        'site_url'  => home_url('/'),
        'url'       => $url,
        'preheader' => wpum_t('Choose a new password for your account.', $locale),
        'title'     => wpum_t('Reset your password', $locale),
        /* translators: %s: first name or username */
        'greeting'  => sprintf(wpum_t('Hi %s,', $locale), $name),
        /* translators: 1: username, 2: site name */
        'intro'     => sprintf(wpum_t('Someone asked to reset the password for the account "%1$s" at %2$s. Click the button to choose a new password.', $locale), $user->user_login, $site),
        'button'    => wpum_t('Choose a new password', $locale),
        'fallback'  => wpum_t('If the button does not work, copy and paste this link into your browser:', $locale),
        /* translators: %s: username */
        'username'  => sprintf(wpum_t('Your username: %s', $locale), $user->user_login),
        /* translators: %d: number of hours */
        'expiry'    => sprintf(wpum_tn('This link is valid for %d hour.', 'This link is valid for %d hours.', $hours, $locale), $hours),
        'ignore'    => wpum_t('If you do not want to change your password, just ignore this email. Your password stays unchanged.', $locale),
        /* translators: %s: site name */
        'footer'    => sprintf(wpum_t('This message was sent by %s. Please do not reply to it.', $locale), $site),
    );

    $s            = (array) apply_filters('wpum_password_reset_email_strings', $s, $user);
    $s['details'] = array($s['username'], $s['expiry']);

    return array(
        /* translators: %s: site name */
        'subject' => sprintf(wpum_t('[%s] Reset your password', $locale), $site),
        'html'    => wpum_render_email_template('email', $s),
        'text'    => wpum_compose_email_text($s),
    );
}

// Create a reset key and email the link.
function wpum_send_password_reset_email($user) {
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        wpum_log('Błąd generowania klucza resetu hasła: ' . $key->get_error_message());
        return false;
    }

    $mail = wpum_build_password_reset_email($user, wpum_password_reset_url($user, $key));

    return wpum_send_html_mail($user->user_email, $mail['subject'], $mail['html'], $mail['text']);
}
