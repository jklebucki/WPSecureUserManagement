<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared helpers for front-end forms: request detection, nonces, URLs, assets, field markup.
 */

// Was the current request a POST of the given plugin form?
function wpum_is_form_post($action)
{
    return isset($_SERVER['REQUEST_METHOD'], $_POST['wpum_action'])
        && 'POST' === $_SERVER['REQUEST_METHOD']
        && $action === $_POST['wpum_action'];
}

// Print the hidden fields every plugin form needs (action + nonce).
function wpum_form_fields($action)
{
    echo '<input type="hidden" name="wpum_action" value="' . esc_attr($action) . '">';
    echo '<input type="hidden" name="wpum_form_nonce" value="' . esc_attr(wp_create_nonce('wpum_form_' . $action)) . '">';
}

// Verify the nonce printed by wpum_form_fields().
function wpum_verify_form_nonce($action)
{
    if (!isset($_POST['wpum_form_nonce']) || !is_string($_POST['wpum_form_nonce'])) {
        return false;
    }

    return (bool) wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpum_form_nonce'])), 'wpum_form_' . $action);
}

// Read a text field from $_POST (unslashed + sanitized).
function wpum_post_text($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
}

// Read a raw (unsanitized, unslashed) value from $_POST. Use for passwords only.
function wpum_post_raw($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';
}

// URL of the page the form was submitted from (site origin + request path), without the flash token.
function wpum_current_url()
{
    $parts  = wp_parse_url(home_url('/'));
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $uri    = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $uri    = '/' . ltrim((string) $uri, '/');

    return remove_query_arg(array('wpum_t'), $origin . $uri);
}

// Slugs of the pages that hold the plugin shortcodes (configurable on the Configuration page).
function wpum_get_page_slug($key)
{
    $defaults = array(
        'account'  => 'user-account',
        'register' => 'user-register',
        'reset'    => 'password-reset',
    );
    $default = isset($defaults[$key]) ? $defaults[$key] : '';
    $slug    = trim((string) get_option('wpum_page_' . $key, $default), '/');

    return '' !== $slug ? $slug : $default;
}

function wpum_get_page_url($key)
{
    // user_trailingslashit() follows the site's permalink structure, so no extra canonical redirect happens.
    return user_trailingslashit(home_url('/' . wpum_get_page_slug($key)));
}

// Enqueue the front-end stylesheet + shared script (idempotent).
function wpum_enqueue_frontend_assets()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    wp_enqueue_style('wpum-frontend', WPUM_PLUGIN_URL . 'assets/css/wpum-frontend.css', array(), WPUM_VERSION);
    wp_enqueue_script('wpum-forms', WPUM_PLUGIN_URL . 'assets/js/wpum-forms.js', array('jquery'), WPUM_VERSION, true);
    wp_localize_script('wpum-forms', 'wpumFront', array(
        'ajaxurl'           => admin_url('admin-ajax.php'),
        'minPasswordLength' => wpum_get_min_password_length(),
        'i18n'              => array(
            'weak'          => __('Weak', 'wp-user-management-plugin'),
            'medium'        => __('Medium', 'wp-user-management-plugin'),
            'strong'        => __('Strong', 'wp-user-management-plugin'),
            'passwordMatch' => __('Passwords match', 'wp-user-management-plugin'),
            'passwordDiff'  => __('Passwords do not match', 'wp-user-management-plugin'),
            'working'       => __('Please wait...', 'wp-user-management-plugin'),
            'saveError'     => __('An error occurred. Please try again.', 'wp-user-management-plugin'),
        ),
    ));
}

// Profile page needs extra behaviour (tabs, modal, AJAX credentials).
function wpum_enqueue_profile_assets()
{
    wpum_enqueue_frontend_assets();
    wp_enqueue_script('wpum-profile', WPUM_PLUGIN_URL . 'assets/js/wpum-profile.js', array('jquery', 'wpum-forms'), WPUM_VERSION, true);
}

// Enqueue early (in <head>) when the main post contains one of our shortcodes; shortcodes also enqueue themselves as a fallback.
function wpum_maybe_enqueue_assets()
{
    if (!is_singular()) {
        return;
    }
    $post = get_post();
    if (!$post || empty($post->post_content)) {
        return;
    }

    $profile_codes = array('wpum_user_profile_edit', 'wpum_my_account_content');
    $form_codes    = array('wpum_user_registration', 'wpum_user_login', 'wpum_password_reset');

    foreach ($profile_codes as $code) {
        if (has_shortcode($post->post_content, $code)) {
            wpum_enqueue_profile_assets();
            return;
        }
    }
    foreach ($form_codes as $code) {
        if (has_shortcode($post->post_content, $code)) {
            wpum_enqueue_frontend_assets();
            return;
        }
    }
}
add_action('wp_enqueue_scripts', 'wpum_maybe_enqueue_assets');

// Render a single labelled input.
function wpum_render_field($args)
{
    $a = wp_parse_args($args, array(
        'id'           => '',
        'name'         => '',
        'type'         => 'text',
        'label'        => '',
        'value'        => '',
        'required'     => false,
        'readonly'     => false,
        'autocomplete' => '',
        'attrs'        => array(),
        'help'         => '',
    ));

    $attrs = array(
        'type'  => $a['type'],
        'id'    => $a['id'],
        'name'  => $a['name'],
        'class' => 'wpum-input',
    );
    if ('password' !== $a['type']) {
        $attrs['value'] = (string) $a['value'];
    }
    if ('' !== $a['autocomplete']) {
        $attrs['autocomplete'] = $a['autocomplete'];
    }
    foreach ((array) $a['attrs'] as $attr_name => $attr_value) {
        $attrs[$attr_name] = $attr_value;
    }

    $attr_html = '';
    foreach ($attrs as $attr_name => $attr_value) {
        $attr_html .= ' ' . esc_attr($attr_name) . '="' . esc_attr($attr_value) . '"';
    }
    if ($a['required']) {
        $attr_html .= ' required';
    }
    if ($a['readonly']) {
        $attr_html .= ' readonly';
    }

    echo '<div class="wpum-field">';
    echo '<label class="wpum-label" for="' . esc_attr($a['id']) . '">' . esc_html($a['label']);
    if ($a['required']) {
        echo ' <span class="wpum-req" aria-hidden="true">*</span>';
    }
    echo '</label>';
    echo '<input' . $attr_html . '>'; // Attributes are escaped above.
    if ('' !== $a['help']) {
        echo '<p class="wpum-help">' . esc_html($a['help']) . '</p>';
    }
    echo '</div>';
}

// Password strength meter placeholder (filled by wpum-forms.js).
function wpum_render_strength_meter()
{
    echo '<div id="password-strength-meter" class="wpum-strength" aria-live="polite"><span class="wpum-strength__bar"></span><span class="wpum-strength__label"></span></div>';
}
