<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple fixed-window rate limiting on transients.
 *
 * Buckets: login (ip+login), login_ip (ip), reset_ip, reset_email, register_ip, register_email, activation_ip,
 * password_check (user id).
 * Limits can be changed with the "wpum_rate_limit_{bucket}" filter (array: max attempts, window in seconds).
 */

// Client IP. CF-Connecting-IP is trusted only when the admin declared the site is behind Cloudflare.
function wpum_get_client_ip()
{
    $ip = '';

    if (function_exists('wpum_turnstile_get_options')) {
        $options = wpum_turnstile_get_options();
        if (!empty($options['behind_cloudflare']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidate = trim((string) wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
            if (false !== filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
    }

    if ('' === $ip && !empty($_SERVER['REMOTE_ADDR'])) {
        $candidate = trim((string) wp_unslash($_SERVER['REMOTE_ADDR']));
        if (false !== filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }

    return (string) apply_filters('wpum_client_ip', $ip);
}

function wpum_rate_limit_config($bucket)
{
    $defaults = array(
        'login'          => array(5, 15 * MINUTE_IN_SECONDS),
        'login_ip'       => array(20, 15 * MINUTE_IN_SECONDS),
        'reset_ip'       => array(5, HOUR_IN_SECONDS),
        'reset_email'    => array(3, HOUR_IN_SECONDS),
        'register_ip'    => array(5, HOUR_IN_SECONDS),
        'register_email' => array(3, HOUR_IN_SECONDS),
        'activation_ip'  => array(20, HOUR_IN_SECONDS),
        'password_check' => array(5, 15 * MINUTE_IN_SECONDS),
    );
    $config = isset($defaults[$bucket]) ? $defaults[$bucket] : array(10, 15 * MINUTE_IN_SECONDS);

    return (array) apply_filters('wpum_rate_limit_' . $bucket, $config);
}

function wpum_rate_limit_key($bucket, $identifier)
{
    return 'wpum_rl_' . md5($bucket . '|' . strtolower((string) $identifier));
}

// True when the bucket is exhausted for this identifier.
function wpum_rate_limit_is_blocked($bucket, $identifier)
{
    $config = wpum_rate_limit_config($bucket);
    $data   = get_transient(wpum_rate_limit_key($bucket, $identifier));

    if (!is_array($data) || empty($data['until']) || $data['until'] < time()) {
        return false;
    }

    return (int) $data['count'] >= (int) $config[0];
}

// Record one attempt.
function wpum_rate_limit_hit($bucket, $identifier)
{
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
function wpum_rate_limit_clear($bucket, $identifier)
{
    delete_transient(wpum_rate_limit_key($bucket, $identifier));
}
