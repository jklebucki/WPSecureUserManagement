<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blocking xmlrpc.php (a second way to guess passwords and to abuse pingbacks; replacing wp-login.php does not cover it).
 *
 * Two layers, switched by Configuration -> XML-RPC (default on):
 *  1. WordPress itself: authenticated XML-RPC is refused and the pingback methods are removed (works on every server).
 *  2. Apache (and LiteSpeed): a marked block in the .htaccess of the WordPress folder makes the web server refuse the file
 *     before PHP starts. It is written with insert_with_markers() like WordPress's own rewrite rules, checked right away
 *     (the site must still answer and xmlrpc.php must be refused) and removed again if anything is off, because a bad
 *     .htaccess line takes the whole site down. It is also removed when the option is switched off or the plugin is
 *     deactivated.
 *
 * Emergency switch: define( 'WPUM_ALLOW_XMLRPC', true ); in wp-config.php (needed e.g. for Jetpack or the mobile app).
 */

function wpum_htaccess_marker() {
    return 'WP User Management';
}

// Should XML-RPC be blocked?
function wpum_xmlrpc_block_wanted() {
    if (defined('WPUM_ALLOW_XMLRPC') && WPUM_ALLOW_XMLRPC) {
        return false;
    }

    return (bool) get_option('wpum_block_xmlrpc', 1);
}

/* ------------------------------------------------------------------ */
/* Layer 1: WordPress                                                  */
/* ------------------------------------------------------------------ */

function wpum_filter_xmlrpc_enabled($enabled) {
    return wpum_xmlrpc_block_wanted() ? false : $enabled;
}
add_filter('xmlrpc_enabled', 'wpum_filter_xmlrpc_enabled');

function wpum_filter_xmlrpc_methods($methods) {
    if (wpum_xmlrpc_block_wanted() && is_array($methods)) {
        unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
    }

    return $methods;
}
add_filter('xmlrpc_methods', 'wpum_filter_xmlrpc_methods');

function wpum_filter_pingback_header($headers) {
    if (wpum_xmlrpc_block_wanted() && is_array($headers)) {
        unset($headers['X-Pingback']);
    }

    return $headers;
}
add_filter('wp_headers', 'wpum_filter_pingback_header');

/* ------------------------------------------------------------------ */
/* Layer 2: .htaccess                                                  */
/* ------------------------------------------------------------------ */

function wpum_htaccess_path() {
    if (!function_exists('get_home_path')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    return get_home_path() . '.htaccess';
}

// The lines between the BEGIN/END markers.
function wpum_xmlrpc_htaccess_rules() {
    return (array) apply_filters('wpum_xmlrpc_htaccess_rules', array(
        '# Blocks XML-RPC (password guessing, pingback abuse). Managed by WP User Management Plugin.',
        '<Files xmlrpc.php>',
        '<IfModule mod_authz_core.c>',
        'Require all denied',
        '</IfModule>',
        '<IfModule !mod_authz_core.c>',
        'Order deny,allow',
        'Deny from all',
        '</IfModule>',
        '</Files>',
    ));
}

// Apache and LiteSpeed read .htaccess files; WordPress sets $is_apache for both.
function wpum_htaccess_supported() {
    global $is_apache;

    return !empty($is_apache) && !(defined('WPUM_NO_HTACCESS') && WPUM_NO_HTACCESS);
}

function wpum_htaccess_has_block() {
    $file = wpum_htaccess_path();

    return is_readable($file) && false !== strpos((string) file_get_contents($file), '# BEGIN ' . wpum_htaccess_marker());
}

// Take our block out of .htaccess (the rest of the file is left untouched).
function wpum_remove_htaccess_block() {
    $file = wpum_htaccess_path();
    if (!is_readable($file) || !is_writable($file)) {
        return false;
    }

    $content = (string) file_get_contents($file);
    $marker  = preg_quote(wpum_htaccess_marker(), '/');
    $clean   = preg_replace('/\n?# BEGIN ' . $marker . '\n.*?# END ' . $marker . '\n?/s', "\n", $content, 1);

    if (null === $clean || $clean === $content) {
        return false;
    }

    return false !== file_put_contents($file, preg_replace("/\n{3,}/", "\n\n", $clean), LOCK_EX);
}

/**
 * After writing the block: does the site still work, and does Apache really refuse xmlrpc.php?
 *
 * @return string ok | broken (the site answers with a server error) | ineffective (.htaccess is ignored here) | unknown (could not ask)
 */
function wpum_verify_htaccess() {
    $args = array(
        'timeout'     => 8,
        'redirection' => 2,
        'sslverify'   => (bool) apply_filters('https_local_ssl_verify', false),
        'headers'     => array('Cache-Control' => 'no-cache'),
    );

    $home = wp_remote_get(home_url('/'), $args);
    if (is_wp_error($home)) {
        return 'unknown';
    }
    if ((int) wp_remote_retrieve_response_code($home) >= 500) {
        return 'broken';
    }

    $xmlrpc = wp_remote_get(site_url('xmlrpc.php'), $args);
    if (is_wp_error($xmlrpc)) {
        return 'unknown';
    }

    return 403 === (int) wp_remote_retrieve_response_code($xmlrpc) ? 'ok' : 'ineffective';
}

function wpum_set_htaccess_status($state) {
    update_option('wpum_htaccess_status', array('state' => $state, 'time' => time()), false);

    return $state;
}

function wpum_get_htaccess_status() {
    $status = get_option('wpum_htaccess_status', array());

    return is_array($status) && !empty($status['state']) ? $status['state'] : 'not_checked';
}

/**
 * Bring .htaccess in line with the setting. Returns the resulting state:
 * active | off | allowed (emergency constant) | not_apache | not_writable | write_failed | rejected | ineffective | unknown
 */
function wpum_sync_htaccess() {
    // Taking our own block out is always safe, whatever the server detection says (e.g. WP-CLI has no server software).
    if (defined('WPUM_ALLOW_XMLRPC') && WPUM_ALLOW_XMLRPC) {
        if (wpum_htaccess_has_block()) {
            wpum_remove_htaccess_block();
        }

        return wpum_set_htaccess_status('allowed');
    }

    if (!wpum_xmlrpc_block_wanted()) {
        if (wpum_htaccess_has_block()) {
            wpum_remove_htaccess_block();
        }

        return wpum_set_htaccess_status('off');
    }

    if (!wpum_htaccess_supported()) {
        return wpum_set_htaccess_status('not_apache');
    }

    if (wpum_htaccess_has_block()) {
        return wpum_set_htaccess_status('active');
    }

    $file = wpum_htaccess_path();
    if (!(file_exists($file) ? is_writable($file) : is_writable(dirname($file)))) {
        return wpum_set_htaccess_status('not_writable');
    }

    if (!function_exists('insert_with_markers')) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
    if (!insert_with_markers($file, wpum_htaccess_marker(), wpum_xmlrpc_htaccess_rules())) {
        return wpum_set_htaccess_status('write_failed');
    }

    $result = wpum_verify_htaccess();
    if ('ok' !== $result) {
        wpum_remove_htaccess_block(); // never leave something behind that we could not confirm works
        $result = ('broken' === $result) ? 'rejected' : $result;

        return wpum_set_htaccess_status($result);
    }

    return wpum_set_htaccess_status('active');
}

// WP-Cron worker used after a plugin update, so no visitor waits for the checks.
add_action('wpum_cron_sync_htaccess', 'wpum_sync_htaccess');

// Text for the Configuration page.
function wpum_htaccess_status_text($state) {
    $texts = array(
        'active'       => __('The block is in .htaccess and Apache refuses xmlrpc.php (verified).', 'wp-user-management-plugin'),
        'off'          => __('XML-RPC blocking is switched off.', 'wp-user-management-plugin'),
        'allowed'      => __('WPUM_ALLOW_XMLRPC is set in wp-config.php, so XML-RPC is allowed.', 'wp-user-management-plugin'),
        'not_apache'   => __('This server does not read .htaccess files: XML-RPC is refused by WordPress itself.', 'wp-user-management-plugin'),
        'not_writable' => __('The .htaccess file is not writable, so only WordPress refuses XML-RPC. To block it in Apache as well, add the lines below to .htaccess yourself.', 'wp-user-management-plugin'),
        'write_failed' => __('Writing .htaccess failed, so only WordPress refuses XML-RPC.', 'wp-user-management-plugin'),
        'rejected'     => __('Apache rejected the block (the site answered with an error), so it was removed again. WordPress itself still refuses XML-RPC.', 'wp-user-management-plugin'),
        'ineffective'  => __('This server ignores .htaccess here (AllowOverride), so the block was not kept. WordPress itself still refuses XML-RPC.', 'wp-user-management-plugin'),
        'unknown'      => __('The block could not be verified (the site could not call itself), so it was not kept. WordPress itself still refuses XML-RPC.', 'wp-user-management-plugin'),
        'not_checked'  => __('Not applied yet. Save the settings to apply it.', 'wp-user-management-plugin'),
    );

    return isset($texts[$state]) ? $texts[$state] : $texts['not_checked'];
}
