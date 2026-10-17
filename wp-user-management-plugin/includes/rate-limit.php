<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate limiting: fixed-window counters on transients, with limits editable under User Management -> Configuration.
 *
 * Buckets: login (ip+login), login_ip, reset_ip, reset_email, register_ip, register_email, activation_ip,
 * password_check (user id), flash (ip; protects the database from junk written by invalid form posts).
 * Filter "wpum_rate_limit_{bucket}" can still override a limit: array( max attempts, window in seconds ).
 */

/* ------------------------------------------------------------------ */
/* Client IP                                                           */
/* ------------------------------------------------------------------ */

// Built-in copy of Cloudflare's published ranges (https://www.cloudflare.com/ips-v4 and /ips-v6): the fallback.
function wpum_builtin_cloudflare_ranges() {
    return array(
        // IPv4
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    );
}

// The list downloaded from Cloudflare (see wpum_refresh_cloudflare_ranges()): ranges, fetched (timestamp), v4, v6.
function wpum_fetched_cloudflare_data() {
    $data = get_option('wpum_cloudflare_ranges', array());

    if (is_array($data) && isset($data['ranges']) && is_array($data['ranges'])) {
        return $data;
    }

    return array('ranges' => array(), 'fetched' => 0, 'v4' => 0, 'v6' => 0);
}

/**
 * Proxies whose CF-Connecting-IP header we believe: Cloudflare's edge network (built-in list plus the one downloaded from
 * Cloudflare) and the local machine (cloudflared tunnel, local reverse proxy). Anyone else could forge the header, so for
 * them it is ignored. Extend with the wpum_trusted_proxy_ranges filter.
 */
function wpum_trusted_proxy_ranges() {
    $fetched = wpum_fetched_cloudflare_data();
    $ranges  = array_merge(wpum_builtin_cloudflare_ranges(), $fetched['ranges'], array('127.0.0.0/8', '::1/128'));

    return (array) apply_filters('wpum_trusted_proxy_ranges', array_values(array_unique($ranges)));
}

/**
 * Keep only well-formed CIDR ranges of the right family from a downloaded list. Absurdly wide ranges (wider than /12 for
 * IPv4, /28 for IPv6; Cloudflare's widest are /13 and /29) are dropped, so a damaged or tampered answer can never make
 * "everybody" a trusted proxy.
 */
function wpum_parse_cidr_list($text, $ipv6) {
    $valid = array();

    foreach (preg_split('/\s+/', trim((string) $text)) as $line) {
        if (!preg_match('/^([0-9a-fA-F:.]+)\/([0-9]{1,3})$/', $line, $match)) {
            continue;
        }

        $binary = @inet_pton($match[1]);
        if (false === $binary || $ipv6 !== (16 === strlen($binary))) {
            continue;
        }

        $bits = (int) $match[2];
        if ($bits < ($ipv6 ? 28 : 12) || $bits > ($ipv6 ? 128 : 32)) {
            continue;
        }

        $valid[] = strtolower($line);
    }

    return array_values(array_unique($valid));
}

/**
 * Download Cloudflare's current IP ranges and store them (the previous list stays when anything looks wrong).
 * Runs from WP-Cron (wpum_cron_refresh_cloudflare) or from the "Refresh" button, never inside a visitor's request.
 *
 * @return bool true when a new list was stored
 */
function wpum_refresh_cloudflare_ranges() {
    $all    = array();
    $counts = array(4 => 0, 6 => 0);

    foreach (array(4 => 'https://www.cloudflare.com/ips-v4', 6 => 'https://www.cloudflare.com/ips-v6') as $family => $url) {
        $response = wp_remote_get($url, array('timeout' => 10));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $list = wpum_parse_cidr_list(wp_remote_retrieve_body($response), 6 === $family);
        if (count($list) < (6 === $family ? 3 : 5)) {
            return false; // far fewer ranges than Cloudflare publishes: do not trust this answer
        }

        $all            = array_merge($all, $list);
        $counts[$family] = count($list);
    }

    update_option('wpum_cloudflare_ranges', array('ranges' => $all, 'fetched' => time(), 'v4' => $counts[4], 'v6' => $counts[6]), false);

    return true;
}
add_action('wpum_cron_refresh_cloudflare', 'wpum_refresh_cloudflare_ranges');

/**
 * A request carried CF-Connecting-IP but came from an address we do not know as Cloudflare: either somebody is forging
 * the header or Cloudflare added a range. Ask WP-Cron to re-download the list (at most once an hour; the visitor's
 * request is not delayed and is treated as untrusted meanwhile).
 */
function wpum_maybe_refresh_cloudflare_ranges() {
    if (get_transient('wpum_cf_refresh_lock')) {
        return;
    }
    set_transient('wpum_cf_refresh_lock', 1, HOUR_IN_SECONDS);

    if (!wp_next_scheduled('wpum_cron_refresh_cloudflare')) {
        wp_schedule_single_event(time(), 'wpum_cron_refresh_cloudflare');
    }
}

// Validate an IP; IPv4-mapped IPv6 (::ffff:1.2.3.4) becomes plain IPv4. Returns '' for garbage.
function wpum_valid_ip($value) {
    $value = trim((string) $value);
    if ('' === $value || false === filter_var($value, FILTER_VALIDATE_IP)) {
        return '';
    }

    $binary = @inet_pton($value);
    if (false !== $binary && 16 === strlen($binary) && 0 === strncmp($binary, str_repeat("\0", 10) . "\xff\xff", 12)) {
        return (string) inet_ntop(substr($binary, 12));
    }

    return $value;
}

// Is $ip inside $cidr (IPv4 or IPv6)?
function wpum_ip_in_cidr($ip, $cidr) {
    if (false === strpos($cidr, '/')) {
        return false;
    }

    list($subnet, $bits) = explode('/', $cidr, 2);
    $ip_bin     = @inet_pton($ip);
    $subnet_bin = @inet_pton($subnet);
    if (false === $ip_bin || false === $subnet_bin || strlen($ip_bin) !== strlen($subnet_bin)) {
        return false;
    }

    $bits = (int) $bits;
    if ($bits < 0 || $bits > strlen($ip_bin) * 8) {
        return false;
    }

    $whole = intdiv($bits, 8);
    if ($whole > 0 && 0 !== strncmp($ip_bin, $subnet_bin, $whole)) {
        return false;
    }

    $rest = $bits % 8;
    if ($rest > 0) {
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        if ((ord($ip_bin[$whole]) & $mask) !== (ord($subnet_bin[$whole]) & $mask)) {
            return false;
        }
    }

    return true;
}

function wpum_is_trusted_proxy($ip) {
    foreach (wpum_trusted_proxy_ranges() as $range) {
        if (wpum_ip_in_cidr($ip, $range)) {
            return true;
        }
    }

    return false;
}

/**
 * The visitor's IP and where it came from: array( ip, source ), source = 'cloudflare' (read from CF-Connecting-IP of a
 * trusted proxy) or 'direct' (the connection itself).
 */
function wpum_client_ip_details() {
    $remote = isset($_SERVER['REMOTE_ADDR']) ? wpum_valid_ip(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ip     = $remote;
    $source = 'direct';

    if ('' !== $remote && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        if (wpum_is_trusted_proxy($remote)) {
            $forwarded = wpum_valid_ip(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
            if ('' !== $forwarded) {
                $ip     = $forwarded;
                $source = 'cloudflare';
            }
        } else {
            wpum_maybe_refresh_cloudflare_ranges();
        }
    }

    return array((string) apply_filters('wpum_client_ip', $ip), $source);
}

function wpum_get_client_ip() {
    $details = wpum_client_ip_details();

    return $details[0];
}

// IP used as the rate-limit key: IPv6 visitors are grouped by /64, because one user controls a whole /64.
function wpum_get_rate_limit_ip() {
    $ip     = wpum_get_client_ip();
    $binary = '' !== $ip ? @inet_pton($ip) : false;

    if (false !== $binary && 16 === strlen($binary)) {
        return (string) inet_ntop(substr($binary, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    return $ip;
}

/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

// Defaults: bucket => array( max attempts, window in minutes ).
function wpum_rate_limit_buckets() {
    return array(
        'login'          => array(5, 15),
        'login_ip'       => array(20, 15),
        'reset_ip'       => array(5, 60),
        'reset_email'    => array(3, 60),
        'register_ip'    => array(5, 60),
        'register_email' => array(3, 60),
        'activation_ip'  => array(20, 60),
        'password_check' => array(5, 15),
        'flash'          => array(30, 1),
    );
}

function wpum_rate_limit_labels() {
    return array(
        'login'          => __('Failed logins per account and IP', 'wp-user-management-plugin'),
        'login_ip'       => __('Failed logins per IP (any account)', 'wp-user-management-plugin'),
        'reset_ip'       => __('Password reset requests per IP', 'wp-user-management-plugin'),
        'reset_email'    => __('Password reset requests per email address', 'wp-user-management-plugin'),
        'register_ip'    => __('Registrations per IP', 'wp-user-management-plugin'),
        'register_email' => __('Registrations per email address', 'wp-user-management-plugin'),
        'activation_ip'  => __('Confirmation link attempts per IP', 'wp-user-management-plugin'),
        'password_check' => __('Wrong current-password attempts per user', 'wp-user-management-plugin'),
        'flash'          => __('Form messages per IP (keeps junk requests out of the database)', 'wp-user-management-plugin'),
    );
}

function wpum_get_rate_limit_settings() {
    $settings = get_option('wpum_rate_limits', array());

    return is_array($settings) ? $settings : array();
}

function wpum_rate_limit_enabled() {
    $settings = wpum_get_rate_limit_settings();

    return !isset($settings['enabled']) || !empty($settings['enabled']);
}

// Effective limit of a bucket: array( max attempts, window in seconds ).
function wpum_rate_limit_config($bucket) {
    $defaults = wpum_rate_limit_buckets();
    $max      = isset($defaults[$bucket]) ? $defaults[$bucket][0] : 10;
    $minutes  = isset($defaults[$bucket]) ? $defaults[$bucket][1] : 15;

    $settings = wpum_get_rate_limit_settings();
    if (isset($settings['limits'][$bucket]['max'], $settings['limits'][$bucket]['minutes'])) {
        $saved_max     = absint($settings['limits'][$bucket]['max']);
        $saved_minutes = absint($settings['limits'][$bucket]['minutes']);
        if ($saved_max >= 1 && $saved_minutes >= 1) {
            $max     = $saved_max;
            $minutes = $saved_minutes;
        }
    }

    return (array) apply_filters('wpum_rate_limit_' . $bucket, array($max, $minutes * MINUTE_IN_SECONDS));
}

// Save the settings posted from the Configuration page (values are clamped, junk falls back to the defaults).
function wpum_save_rate_limit_settings($enabled, $max_input, $minutes_input) {
    $limits = array();

    foreach (wpum_rate_limit_buckets() as $bucket => $default) {
        $max     = isset($max_input[$bucket]) && is_scalar($max_input[$bucket]) ? absint($max_input[$bucket]) : 0;
        $minutes = isset($minutes_input[$bucket]) && is_scalar($minutes_input[$bucket]) ? absint($minutes_input[$bucket]) : 0;

        $limits[$bucket] = array(
            'max'     => max(1, min(1000, $max ? $max : $default[0])),
            'minutes' => max(1, min(1440, $minutes ? $minutes : $default[1])),
        );
    }

    update_option('wpum_rate_limits', array('enabled' => $enabled ? 1 : 0, 'limits' => $limits));
}

/* ------------------------------------------------------------------ */
/* Counters                                                            */
/* ------------------------------------------------------------------ */

// The generation is part of every key: bumping it instantly forgets all counters (works with object caches too).
function wpum_rate_limit_key($bucket, $identifier) {
    return 'wpum_rl_' . md5((int) get_option('wpum_rl_generation', 1) . '|' . $bucket . '|' . strtolower((string) $identifier));
}

function wpum_rate_limit_reset_all() {
    update_option('wpum_rl_generation', (int) get_option('wpum_rl_generation', 1) + 1, false);
}

// True when the bucket is exhausted for this identifier.
function wpum_rate_limit_is_blocked($bucket, $identifier) {
    if (!wpum_rate_limit_enabled()) {
        return false;
    }

    $config = wpum_rate_limit_config($bucket);
    $data   = get_transient(wpum_rate_limit_key($bucket, $identifier));

    if (!is_array($data) || empty($data['until']) || $data['until'] < time()) {
        return false;
    }

    return (int) $data['count'] >= (int) $config[0];
}

// Record one attempt.
function wpum_rate_limit_hit($bucket, $identifier) {
    if (!wpum_rate_limit_enabled()) {
        return;
    }

    $config = wpum_rate_limit_config($bucket);
    $key    = wpum_rate_limit_key($bucket, $identifier);
    $data   = get_transient($key);
    $now    = time();

    if (!is_array($data) || empty($data['until']) || $data['until'] < $now) {
        $data = array('count' => 0, 'until' => $now + (int) $config[1]);
    }

    $data['count'] = (int) $data['count'] + 1;
    set_transient($key, $data, max(1, $data['until'] - $now));
}

// Forget attempts (e.g. after a successful login).
function wpum_rate_limit_clear($bucket, $identifier) {
    delete_transient(wpum_rate_limit_key($bucket, $identifier));
}

/* ------------------------------------------------------------------ */
/* Admin action: forget all counters                                   */
/* ------------------------------------------------------------------ */

function wpum_handle_reset_rate_limits() {
    check_admin_referer('wpum_reset_rate_limits');

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to do this.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    wpum_rate_limit_reset_all();

    $back = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=wp_user_management_configuration');
    wp_safe_redirect(add_query_arg('wpum_rl_reset', '1', $back));
    exit;
}
add_action('admin_post_wpum_reset_rate_limits', 'wpum_handle_reset_rate_limits');

function wpum_rate_limit_reset_notice() {
    if (isset($_GET['wpum_rl_reset']) && current_user_can('manage_options')) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Rate-limit counters were cleared.', 'wp-user-management-plugin')
        );
    }
}
add_action('admin_notices', 'wpum_rate_limit_reset_notice');

/* ------------------------------------------------------------------ */
/* Admin action: refresh the Cloudflare ranges now                     */
/* ------------------------------------------------------------------ */

function wpum_handle_refresh_cloudflare_ranges() {
    check_admin_referer('wpum_refresh_cloudflare_ranges');

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to do this.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    $ok   = wpum_refresh_cloudflare_ranges();
    $back = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=wp_user_management_configuration');
    wp_safe_redirect(add_query_arg('wpum_cf', $ok ? 'ok' : 'fail', remove_query_arg('wpum_cf', $back)));
    exit;
}
add_action('admin_post_wpum_refresh_cloudflare_ranges', 'wpum_handle_refresh_cloudflare_ranges');

function wpum_cloudflare_refresh_notice() {
    if (!isset($_GET['wpum_cf']) || !current_user_can('manage_options')) {
        return;
    }

    if ('ok' === $_GET['wpum_cf']) {
        printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__('Cloudflare IP ranges updated.', 'wp-user-management-plugin'));
    } elseif ('fail' === $_GET['wpum_cf']) {
        printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__('Could not download the Cloudflare IP ranges; the previous list is kept.', 'wp-user-management-plugin'));
    }
}
add_action('admin_notices', 'wpum_cloudflare_refresh_notice');
