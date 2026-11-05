<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Turnstile on the forms of other plugins. Every form system needs its own pair of hooks, one that shows the widget and one that
 * checks the token on the server, so only the systems listed in wpum_turnstile_integrations() can be protected.
 *
 * While Simple Cloudflare Turnstile protects one of these forms, the integration waits: two checks of one form would fight over
 * the token, which Cloudflare accepts once. It takes over by itself when the form is switched off in that plugin.
 */

// Key => label, whether the form system is on this site, and the option of Simple Cloudflare Turnstile for the same form.
function wpum_turnstile_integrations() {
    return array(
        'comments' => array(
            'label'        => __('Comments', 'wp-user-management-plugin'),
            'available'    => true,
            'other_option' => 'cfturnstile_comment',
        ),
        'wpforms'  => array(
            'label'        => 'WPForms',
            'available'    => function_exists('wpforms'),
            'other_option' => 'cfturnstile_wpforms',
        ),
    );
}

// Does Simple Cloudflare Turnstile protect this form right now?
function wpum_turnstile_other_plugin_protects($form_key) {
    $integrations = wpum_turnstile_integrations();

    return function_exists('cfturnstile_check')
        && isset($integrations[$form_key])
        && get_option($integrations[$form_key]['other_option'])
        && get_option('cfturnstile_key')
        && get_option('cfturnstile_secret');
}

// Should this request see the widget and pass the check?
function wpum_turnstile_integration_active($form_key) {
    if (!wpum_turnstile_is_active($form_key) || wpum_turnstile_other_plugin_protects($form_key)) {
        return false;
    }

    $options = wpum_turnstile_get_options();

    return empty($options['skip_logged_in']) || !is_user_logged_in();
}

// The widget with some air around it: the plugin's stylesheet is not loaded on these pages.
function wpum_turnstile_integration_widget($form_key) {
    ob_start();
    wpum_turnstile_render($form_key);

    return '<div class="wpum-turnstile-wrap" style="margin:0 0 16px">' . ob_get_clean() . '</div>';
}

/* ------------------------------------------------------------------ */
/* Comments                                                            */
/* ------------------------------------------------------------------ */

function wpum_turnstile_comment_field($submit_button) {
    return wpum_turnstile_integration_active('comments') ? wpum_turnstile_integration_widget('comments') . $submit_button : $submit_button;
}
add_filter('comment_form_submit_button', 'wpum_turnstile_comment_field', 20);

function wpum_turnstile_comment_check() {
    if (!wpum_turnstile_integration_active('comments')) {
        return;
    }

    $result = wpum_turnstile_verify('comments');
    if (is_wp_error($result)) {
        wp_die(esc_html($result->get_error_message()), '', array('response' => 403, 'back_link' => true));
    }
}
add_action('pre_comment_on_post', 'wpum_turnstile_comment_check');

/* ------------------------------------------------------------------ */
/* WPForms                                                             */
/* ------------------------------------------------------------------ */

function wpum_turnstile_wpforms_field() {
    if (!wpum_turnstile_integration_active('wpforms')) {
        return;
    }

    // WPForms sends its forms with AJAX: after a refused attempt the used token has to be replaced.
    wp_enqueue_script('wpum-turnstile-forms', WPUM_PLUGIN_URL . 'assets/js/wpum-turnstile-forms.js', array('jquery'), WPUM_VERSION, true);

    echo wpum_turnstile_integration_widget('wpforms'); // Escaped in wpum_turnstile_render().
}
add_action('wpforms_display_submit_before', 'wpum_turnstile_wpforms_field');

function wpum_turnstile_wpforms_check($entry, $form_data) {
    if (!wpum_turnstile_integration_active('wpforms')) {
        return;
    }

    $result = wpum_turnstile_verify('wpforms');
    if (is_wp_error($result)) {
        wpforms()->obj('process')->errors[$form_data['id']]['header'] = esc_html($result->get_error_message());
    }
}
add_action('wpforms_process_before', 'wpum_turnstile_wpforms_check', 10, 2);
