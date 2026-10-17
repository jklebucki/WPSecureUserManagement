<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cloudflare Turnstile: options, widget rendering, server-side verification and wp-login.php integration.
 *
 * Emergency switch (e.g. when keys are wrong): define('WPUM_TURNSTILE_DISABLE', true); in wp-config.php.
 * Keys can be set in wp-config.php too: WPUM_TURNSTILE_SITE_KEY, WPUM_TURNSTILE_SECRET_KEY.
 */

define('WPUM_TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
define('WPUM_TURNSTILE_SCRIPT_URL', 'https://challenges.cloudflare.com/turnstile/v0/api.js');

// Forms Turnstile can protect: key => label.
function wpum_turnstile_form_labels() {
    return array(
        'login'          => __('Login form (shortcode)', 'wp-user-management-plugin'),
        'register'       => __('Registration form (shortcode)', 'wp-user-management-plugin'),
        'reset'          => __('Password reset form (shortcode)', 'wp-user-management-plugin'),
        'wp_login'       => __('WordPress login (wp-login.php)', 'wp-user-management-plugin'),
        'wp_register'    => __('WordPress registration (wp-login.php)', 'wp-user-management-plugin'),
        'wp_lostpassword' => __('WordPress password reset (wp-login.php)', 'wp-user-management-plugin'),
    );
}

function wpum_turnstile_defaults() {
    return array(
        'enabled'           => 0,
        'site_key'          => '',
        'secret_key'        => '',
        'form_login'        => 1,
        'form_register'     => 1,
        'form_reset'        => 1,
        // Native WordPress forms are opt-in: test the keys first so you cannot lock yourself out.
        'form_wp_login'     => 0,
        'form_wp_register'  => 0,
        'form_wp_lostpassword' => 0,
        'theme'             => 'auto',
        'size'              => 'flexible',
        'appearance'        => 'always',
        'language'          => 'auto',
        'fail_open'         => 0,
    );
}

function wpum_turnstile_get_options() {
    $saved = get_option('wpum_turnstile', array());

    return wp_parse_args(is_array($saved) ? $saved : array(), wpum_turnstile_defaults());
}

function wpum_turnstile_site_key() {
    if (defined('WPUM_TURNSTILE_SITE_KEY') && '' !== WPUM_TURNSTILE_SITE_KEY) {
        return (string) WPUM_TURNSTILE_SITE_KEY;
    }
    $options = wpum_turnstile_get_options();

    return (string) $options['site_key'];
}

function wpum_turnstile_secret_key() {
    if (defined('WPUM_TURNSTILE_SECRET_KEY') && '' !== WPUM_TURNSTILE_SECRET_KEY) {
        return (string) WPUM_TURNSTILE_SECRET_KEY;
    }
    $options = wpum_turnstile_get_options();

    return (string) $options['secret_key'];
}

// Is Turnstile enforced for this form? (disabled, missing keys or emergency constant = no)
function wpum_turnstile_is_active($form_key) {
    if (defined('WPUM_TURNSTILE_DISABLE') && WPUM_TURNSTILE_DISABLE) {
        return false;
    }

    $options = wpum_turnstile_get_options();
    if (empty($options['enabled']) || empty($options['form_' . $form_key])) {
        return false;
    }

    return '' !== wpum_turnstile_site_key() && '' !== wpum_turnstile_secret_key();
}

/* ------------------------------------------------------------------ */
/* Widget                                                              */
/* ------------------------------------------------------------------ */

function wpum_turnstile_enqueue_script() {
    // No version argument: Cloudflare asks not to add a query string to api.js.
    wp_enqueue_script('wpum-turnstile', WPUM_TURNSTILE_SCRIPT_URL, array(), null, true);
}

function wpum_turnstile_script_tag($tag, $handle) {
    if ('wpum-turnstile' === $handle && false === strpos($tag, ' async')) {
        $tag = str_replace(' src=', ' async defer src=', $tag);
    }

    return $tag;
}
add_filter('script_loader_tag', 'wpum_turnstile_script_tag', 10, 2);

// Print the widget for a form. Does nothing when Turnstile is not active for it.
function wpum_turnstile_render($form_key) {
    if (!wpum_turnstile_is_active($form_key)) {
        return;
    }

    $options = wpum_turnstile_get_options();
    wpum_turnstile_enqueue_script();

    $attrs = array(
        'class'           => 'wpum-turnstile cf-turnstile',
        'data-sitekey'    => wpum_turnstile_site_key(),
        'data-action'     => $form_key,
        'data-theme'      => $options['theme'],
        'data-size'       => $options['size'],
        'data-appearance' => $options['appearance'],
    );
    if ('auto' !== $options['language'] && '' !== $options['language']) {
        $attrs['data-language'] = $options['language'];
    }

    $html = '';
    foreach ($attrs as $name => $value) {
        $html .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
    }

    echo '<div' . $html . '></div>'; // Attributes are escaped above.
    echo '<noscript><p class="wpum-help">' . esc_html__('Please enable JavaScript to complete the security check.', 'wp-user-management-plugin') . '</p></noscript>';
}

/* ------------------------------------------------------------------ */
/* Verification                                                        */
/* ------------------------------------------------------------------ */

function wpum_turnstile_error_message($code) {
    $map = array(
        'turnstile_missing'     => __('Please complete the security check and try again.', 'wp-user-management-plugin'),
        'turnstile_failed'      => __('The security check failed. Please try again.', 'wp-user-management-plugin'),
        'turnstile_unavailable' => __('The security check is temporarily unavailable. Please try again later.', 'wp-user-management-plugin'),
    );

    return isset($map[$code]) ? $map[$code] : $map['turnstile_failed'];
}

// Hostnames a valid token may have been issued for.
function wpum_turnstile_allowed_hostnames() {
    $hosts = array();
    foreach (array(home_url(), site_url()) as $url) {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if ($host) {
            $hosts[] = strtolower($host);
        }
    }

    return array_values(array_unique((array) apply_filters('wpum_turnstile_allowed_hostnames', $hosts)));
}

// Call Cloudflare siteverify. Returns the decoded body or WP_Error (network / unparsable response).
function wpum_turnstile_request($secret, $token, $ip = '') {
    $body = array(
        'secret'   => $secret,
        'response' => $token,
    );
    if ('' !== $ip) {
        $body['remoteip'] = $ip;
    }

    $response = wp_remote_post(WPUM_TURNSTILE_VERIFY_URL, array(
        'timeout' => 10,
        'body'    => $body,
    ));
    if (is_wp_error($response)) {
        return $response;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) {
        return new WP_Error('turnstile_unavailable', 'Unparsable Turnstile response (HTTP ' . (int) wp_remote_retrieve_response_code($response) . ')');
    }

    return $data;
}

/**
 * Verify the Turnstile token posted with a form.
 *
 * @param string $form_key One of wpum_turnstile_form_labels() keys; also the expected widget action.
 * @return true|WP_Error   true = let the request through (passed, not enforced, or fail-open); WP_Error code = turnstile_*.
 */
function wpum_turnstile_verify($form_key) {
    if (!wpum_turnstile_is_active($form_key)) {
        return true;
    }

    $options = wpum_turnstile_get_options();

    $token = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response'])
        ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response']))
        : '';
    if ('' === $token) {
        return new WP_Error('turnstile_missing', wpum_turnstile_error_message('turnstile_missing'));
    }
    if (strlen($token) > 2048) {
        return new WP_Error('turnstile_failed', wpum_turnstile_error_message('turnstile_failed'));
    }

    $data = wpum_turnstile_request(wpum_turnstile_secret_key(), $token, wpum_get_client_ip());

    if (is_wp_error($data)) {
        wpum_log('Turnstile: ' . $data->get_error_message());
        return !empty($options['fail_open'])
            ? true
            : new WP_Error('turnstile_unavailable', wpum_turnstile_error_message('turnstile_unavailable'));
    }

    $codes = isset($data['error-codes']) ? array_map('strval', (array) $data['error-codes']) : array();

    if (!empty($data['success'])) {
        // Cloudflare's dummy keys answer with hostname "example.com" and no action: nothing to compare there.
        $is_testing_key = !empty($data['metadata']['result_with_testing_key']);

        if (!$is_testing_key) {
            if (!empty($data['action']) && $data['action'] !== $form_key) {
                return new WP_Error('turnstile_failed', wpum_turnstile_error_message('turnstile_failed'));
            }
            if (!empty($data['hostname']) && !in_array(strtolower($data['hostname']), wpum_turnstile_allowed_hostnames(), true)) {
                return new WP_Error('turnstile_failed', wpum_turnstile_error_message('turnstile_failed'));
            }
        }

        return true;
    }

    // Our configuration is wrong (secret rejected): don't lock every visitor out, warn the admin instead.
    if (array_intersect(array('invalid-input-secret', 'missing-input-secret'), $codes)) {
        set_transient('wpum_turnstile_config_error', 'invalid-input-secret', DAY_IN_SECONDS);
        wpum_log('Turnstile: secret key rejected by Cloudflare - verification skipped.');
        return true;
    }

    if (in_array('internal-error', $codes, true)) {
        return !empty($options['fail_open'])
            ? true
            : new WP_Error('turnstile_unavailable', wpum_turnstile_error_message('turnstile_unavailable'));
    }

    return new WP_Error('turnstile_failed', wpum_turnstile_error_message('turnstile_failed'));
}

/* ------------------------------------------------------------------ */
/* Native wp-login.php forms                                           */
/* ------------------------------------------------------------------ */

function wpum_is_wp_login_post() {
    global $pagenow;

    return 'wp-login.php' === $pagenow
        && isset($_SERVER['REQUEST_METHOD'])
        && 'POST' === $_SERVER['REQUEST_METHOD'];
}

function wpum_turnstile_wp_login_render() {
    wpum_turnstile_render('wp_login');
}
add_action('login_form', 'wpum_turnstile_wp_login_render');

function wpum_turnstile_wp_register_render() {
    wpum_turnstile_render('wp_register');
}
add_action('register_form', 'wpum_turnstile_wp_register_render');

function wpum_turnstile_wp_lostpassword_render() {
    wpum_turnstile_render('wp_lostpassword');
}
add_action('lostpassword_form', 'wpum_turnstile_wp_lostpassword_render');

// Runs after the password check (priority 100): that check returns a WP_User and would override an earlier error.
function wpum_turnstile_wp_login_authenticate($user) {
    if (!($user instanceof WP_User) || !wpum_is_wp_login_post() || !wpum_turnstile_is_active('wp_login')) {
        return $user;
    }

    $result = wpum_turnstile_verify('wp_login');

    return is_wp_error($result) ? $result : $user;
}
add_filter('authenticate', 'wpum_turnstile_wp_login_authenticate', 100);

function wpum_turnstile_wp_registration_errors($errors) {
    if (!wpum_is_wp_login_post() || !wpum_turnstile_is_active('wp_register')) {
        return $errors;
    }

    $result = wpum_turnstile_verify('wp_register');
    if (is_wp_error($result) && $errors instanceof WP_Error) {
        $errors->add($result->get_error_code(), esc_html($result->get_error_message()));
    }

    return $errors;
}
add_filter('registration_errors', 'wpum_turnstile_wp_registration_errors');

function wpum_turnstile_wp_lostpassword_post($errors = null) {
    if (!($errors instanceof WP_Error) || !wpum_is_wp_login_post() || !wpum_turnstile_is_active('wp_lostpassword')) {
        return;
    }

    $result = wpum_turnstile_verify('wp_lostpassword');
    if (is_wp_error($result)) {
        $errors->add($result->get_error_code(), esc_html($result->get_error_message()));
    }
}
add_action('lostpassword_post', 'wpum_turnstile_wp_lostpassword_post');
