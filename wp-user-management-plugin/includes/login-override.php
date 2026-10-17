<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Replace wp-login.php with the plugin's pages.
 *
 * When the option "Disable wp-login.php" is on (default), visitors who open wp-login.php for logging in, registering
 * or the "lost password" form are sent to the pages that carry the plugin's shortcodes (Turnstile, rate limiting, styling),
 * and WordPress itself links to those pages (login_url, register_url, lostpassword_url).
 *
 * Safety: a form is replaced only when its page exists, is published and shows the form (the shortcode is in its content, in a
 * reusable block or in page builder data, or the page, loaded like a visitor would, contains the form), so a missing or
 * different page can never lock anybody out. Emergency switch: define( 'WPUM_KEEP_WP_LOGIN', true ); in wp-config.php.
 *
 * Also handled here: logout (straight to the front page; without a valid nonce the visitor is asked to confirm on the account
 * page, never logged out by a bare link) and the screens behind a password-reset link (rp / resetpass), which move to the
 * plugin's reset page together with the key.
 *
 * Left to WordPress on purpose: password-protected posts (postpass) and WordPress's internal confirmations.
 */

// Is the replacement switched on (the option, unless the emergency constant is set)?
function wpum_wp_login_override_enabled() {
    if (defined('WPUM_KEEP_WP_LOGIN') && WPUM_KEEP_WP_LOGIN) {
        return false;
    }

    return (bool) get_option('wpum_disable_wp_login', 1);
}

// Feature => array( page key, shortcodes that make a page a usable replacement, marker of the form in the rendered HTML ).
function wpum_override_features() {
    return array(
        'login'    => array('account', array('wpum_my_account_content', 'wpum_user_login'), 'id="wpum-login-form"'),
        'register' => array('register', array('wpum_user_registration'), 'id="wpum-registration-form"'),
        'reset'    => array('reset', array('wpum_password_reset'), 'id="wpum-password-reset-form"'),
    );
}

/* ------------------------------------------------------------------ */
/* Is there a usable page? (content check, then a look at the real page) */
/* ------------------------------------------------------------------ */

/**
 * The page WordPress serves at this slug (or "parent/child" path). A draft that happens to share the slug never hides the
 * published page. Returns array( page, published ) or null when there is no page with that slug at all.
 */
function wpum_find_page($slug) {
    $slug = trim((string) $slug, '/');
    if ('' === $slug) {
        return null;
    }

    $parts      = explode('/', $slug);
    $candidates = get_posts(array(
        'post_type'        => 'page',
        'name'             => end($parts),
        'post_status'      => array('publish', 'private', 'draft', 'pending', 'future'),
        'posts_per_page'   => 20,
        'orderby'          => 'ID',
        'order'            => 'ASC',
        'no_found_rows'    => true,
        'suppress_filters' => true,
    ));

    $fallback = null;
    foreach ($candidates as $candidate) {
        if (strtolower(trim((string) get_page_uri($candidate), '/')) !== strtolower($slug)) {
            continue;
        }
        if ('publish' === $candidate->post_status) {
            return array('page' => $candidate, 'published' => true);
        }
        if (null === $fallback) {
            $fallback = $candidate;
        }
    }

    return $fallback ? array('page' => $fallback, 'published' => false) : null;
}

/**
 * Text that makes up a page: its content, the reusable blocks (synced patterns) it uses and the page builder data stored
 * in post meta. Extend with the wpum_page_content_sources filter.
 */
function wpum_collect_page_sources($page, $depth = 0) {
    $sources = array((string) $page->post_content);

    if ($depth < 3 && preg_match_all('/<!--\s*wp:block\s+\{[^}]*"ref"\s*:\s*([0-9]+)/', (string) $page->post_content, $match)) {
        foreach (array_unique($match[1]) as $block_id) {
            $block = get_post((int) $block_id);
            if ($block && 'wp_block' === $block->post_type) {
                $sources = array_merge($sources, wpum_collect_page_sources($block, $depth + 1));
            }
        }
    }

    $elementor = get_post_meta($page->ID, '_elementor_data', true);
    if (is_string($elementor) && '' !== $elementor) {
        $sources[] = $elementor;
    }

    return array_values(array_filter((array) apply_filters('wpum_page_content_sources', $sources, $page), 'is_string'));
}

/**
 * Does the text contain one of the shortcodes? Deliberately NOT has_shortcode(): that only knows shortcodes registered so far,
 * so asked before "init" (some plugins call wp_login_url() that early) it would say "no" for every shortcode of this plugin.
 */
function wpum_text_has_shortcode($text, $tags) {
    foreach ($tags as $tag) {
        if (preg_match('/\[' . preg_quote($tag, '/') . '(?=[\s\]\/])/', $text)) {
            return true;
        }
    }

    return false;
}

// Load the page like a visitor would and look for the form. The answer is cached (12 h when it is there, 10 min when not).
function wpum_check_page_render($feature) {
    $features = wpum_override_features();
    if (!isset($features[$feature])) {
        return false;
    }

    $response = wp_remote_get(wpum_get_page_url($features[$feature][0]), array(
        'timeout'     => 5,
        'redirection' => 3,
        'sslverify'   => (bool) apply_filters('https_local_ssl_verify', false),
        'headers'     => array('Cache-Control' => 'no-cache'),
        'cookies'     => array(),
    ));

    $ok = !is_wp_error($response)
        && 200 === (int) wp_remote_retrieve_response_code($response)
        && false !== strpos((string) wp_remote_retrieve_body($response), $features[$feature][2]);

    set_transient('wpum_render_' . $feature, $ok ? 1 : 0, $ok ? 12 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS);

    return $ok;
}

function wpum_forget_page_checks() {
    foreach (array_keys(wpum_override_features()) as $feature) {
        delete_transient('wpum_render_' . $feature);
    }
}

// Pages or reusable blocks were saved: the earlier answers may be outdated.
function wpum_on_page_saved($post_id, $post) {
    if ($post instanceof WP_Post && in_array($post->post_type, array('page', 'wp_block'), true)) {
        wpum_forget_page_checks();
    }
}
add_action('save_post', 'wpum_on_page_saved', 10, 2);

// WP-Cron worker: look at the pages whose content did not show the shortcode (never inside a visitor's request).
function wpum_run_page_checks() {
    foreach (array_keys(wpum_override_features()) as $feature) {
        $status = wpum_override_page_status($feature, true);
        if (!$status['ready'] && 'no_form' === $status['reason']) {
            wpum_check_page_render($feature);
        }
    }
}
add_action('wpum_cron_check_pages', 'wpum_run_page_checks');

/**
 * Status of the page that would replace a wp-login.php screen.
 *
 * @param bool $fresh Ignore the per-request memory.
 * @return array ready (bool), reason (ready|unpublished|no_form|missing), via (content|render|filter|''), page (WP_Post|null), slug
 */
function wpum_override_page_status($feature, $fresh = false) {
    static $memory = array();

    // Before "init" the answer may be incomplete (other plugins ask early): use it, but do not remember it.
    $remember = did_action('init') > 0;
    if ($remember && !$fresh && isset($memory[$feature])) {
        return $memory[$feature];
    }

    $features = wpum_override_features();
    $slug     = isset($features[$feature]) ? wpum_get_page_slug($features[$feature][0]) : '';
    $status   = array('ready' => false, 'reason' => 'missing', 'via' => '', 'page' => null, 'slug' => $slug);

    $found = isset($features[$feature]) ? wpum_find_page($slug) : null;
    if ($found) {
        $status['page']   = $found['page'];
        $status['reason'] = $found['published'] ? 'no_form' : 'unpublished';

        if ($found['published']) {
            foreach (wpum_collect_page_sources($found['page']) as $source) {
                if (wpum_text_has_shortcode($source, $features[$feature][1])) {
                    $status['ready'] = true;
                    $status['via']   = 'content';
                    break;
                }
            }

            if (!$status['ready']) {
                $rendered = get_transient('wpum_render_' . $feature);
                if (false === $rendered && $remember && !get_transient('wpum_page_check_lock')) {
                    // Nobody has looked at the real page yet: let WP-Cron do it in the background.
                    set_transient('wpum_page_check_lock', 1, 10 * MINUTE_IN_SECONDS);
                    if (!wp_next_scheduled('wpum_cron_check_pages')) {
                        wp_schedule_single_event(time(), 'wpum_cron_check_pages');
                    }
                }
                if (1 === (int) $rendered) {
                    $status['ready'] = true;
                    $status['via']   = 'render';
                }
            }

            if ($status['ready']) {
                $status['reason'] = 'ready';
            }
        }
    }

    // Page builders or custom setups can confirm a page themselves.
    $ready = (bool) apply_filters('wpum_page_is_ready', $status['ready'], $feature, $status['page']);
    if ($ready && !$status['ready']) {
        $status['reason'] = 'ready';
        $status['via']    = 'filter';
    }
    $status['ready'] = $ready;

    if ($remember) {
        $memory[$feature] = $status;
    }

    return $status;
}

function wpum_override_page_ready($feature) {
    $status = wpum_override_page_status($feature);

    return $status['ready'];
}

// URL that replaces wp-login.php for a feature, or '' when WordPress should keep handling it.
function wpum_override_target($feature) {
    if (!wpum_wp_login_override_enabled() || !wpum_override_page_ready($feature)) {
        return '';
    }

    $features = wpum_override_features();

    return wpum_get_page_url($features[$feature][0]);
}

// "Check the pages again": forget the cached answers, load the real pages now and go back.
function wpum_handle_recheck_pages() {
    check_admin_referer('wpum_recheck_pages');

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to do this.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    wpum_forget_page_checks();
    foreach (array_keys(wpum_override_features()) as $feature) {
        $status = wpum_override_page_status($feature, true);
        if (!$status['ready'] && 'no_form' === $status['reason']) {
            wpum_check_page_render($feature);
        }
    }

    $back = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=wp_user_management_configuration');
    wp_safe_redirect(add_query_arg('wpum_checked', '1', $back));
    exit;
}
add_action('admin_post_wpum_recheck_pages', 'wpum_handle_recheck_pages');

function wpum_recheck_notice() {
    if (isset($_GET['wpum_checked']) && current_user_can('manage_options')) {
        printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__('The pages were checked again.', 'wp-user-management-plugin'));
    }
}
add_action('admin_notices', 'wpum_recheck_notice');

/* ------------------------------------------------------------------ */
/* Links generated by WordPress                                        */
/* ------------------------------------------------------------------ */

function wpum_filter_login_url($login_url, $redirect = '', $force_reauth = false) {
    $target = wpum_override_target('login');
    if ('' === $target) {
        return $login_url;
    }

    $args = array();
    if ('' !== (string) $redirect) {
        $args['redirect_to'] = rawurlencode($redirect);
    }
    if ($force_reauth) {
        $args['reauth'] = '1';
    }

    return $args ? add_query_arg($args, $target) : $target;
}
add_filter('login_url', 'wpum_filter_login_url', 10, 3);

function wpum_filter_register_url($register_url) {
    $target = wpum_override_target('register');

    return '' !== $target ? $target : $register_url;
}
add_filter('register_url', 'wpum_filter_register_url');

function wpum_filter_lostpassword_url($lostpassword_url) {
    $target = wpum_override_target('reset');

    return '' !== $target ? $target : $lostpassword_url;
}
add_filter('lostpassword_url', 'wpum_filter_lostpassword_url');

/* ------------------------------------------------------------------ */
/* Visits to wp-login.php itself                                       */
/* ------------------------------------------------------------------ */

// Where visitors land after logging out: the front page.
function wpum_logout_redirect_url() {
    return (string) apply_filters('wpum_logout_redirect', home_url('/'));
}

// wp-login.php?action=logout&_wpnonce=...: log out and go to the front page.
function wpum_override_logout() {
    if (!wpum_wp_login_override_enabled()) {
        return; // WordPress handles it
    }

    $home = wpum_logout_redirect_url();

    if (!is_user_logged_in()) {
        wp_safe_redirect($home);
        exit;
    }

    $nonce = isset($_REQUEST['_wpnonce']) && is_string($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
    if (wp_verify_nonce($nonce, 'log-out')) {
        wp_logout();
        wp_safe_redirect($home);
        exit;
    }

    // A link without a valid nonce could be a forged request: ask first, on the account page.
    $target = wpum_override_target('login');
    if ('' !== $target) {
        wpum_redirect_with_flash($target, 'logout', 'info', 'logout_confirm');
    }
}

function wpum_override_wp_login() {
    $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';

    $left_to_core = (array) apply_filters('wpum_wp_login_allowed_actions', array(
        'postpass', 'confirmaction', 'confirm_admin_email', 'entered_recovery_mode',
    ));
    if (in_array($action, $left_to_core, true)) {
        return;
    }

    if ('logout' === $action) {
        wpum_override_logout();
        return;
    }

    $status = isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD'] ? 303 : 302;

    // The screens behind a password-reset link move to the plugin's reset page; the key travels along and is moved out of
    // the address bar there.
    if (in_array($action, array('rp', 'resetpass'), true)) {
        $target = wpum_override_target('reset');
        if ('' === $target) {
            return;
        }

        $args = array();
        if (isset($_GET['key'], $_GET['login']) && is_string($_GET['key']) && is_string($_GET['login'])) {
            $args['wpum_reset'] = rawurlencode(preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['key'])));
            $args['login']      = rawurlencode(wp_unslash($_GET['login']));
        }

        wp_safe_redirect($args ? add_query_arg($args, $target) : $target, $status);
        exit;
    }

    if ('register' === $action) {
        $feature = 'register';
    } elseif (in_array($action, array('lostpassword', 'retrievepassword'), true)) {
        $feature = 'reset';
    } else {
        $feature = 'login';
        // Somebody who is already logged in is sent on by WordPress as usual (usually to wp-admin).
        if (is_user_logged_in() && empty($_REQUEST['reauth'])) {
            return;
        }
    }

    $target = wpum_override_target($feature);
    if ('' === $target) {
        return; // no usable replacement page: leave WordPress's own form alone
    }

    if ('reset' === $feature && isset($_GET['error']) && in_array($_GET['error'], array('invalidkey', 'expiredkey'), true)) {
        wpum_redirect_with_flash($target, 'reset', 'error', 'reset_link_invalid');
    }

    if ('login' === $feature) {
        // WordPress sends people here after they reset their password through the emailed link.
        if (isset($_GET['password']) && 'changed' === $_GET['password']) {
            wpum_redirect_with_flash($target, 'login', 'success', 'password_reset_done');
        }

        $args = array();
        if (isset($_REQUEST['redirect_to']) && is_string($_REQUEST['redirect_to'])) {
            $safe = wp_validate_redirect(esc_url_raw(wp_unslash($_REQUEST['redirect_to'])), '');
            if ('' !== $safe) {
                $args['redirect_to'] = rawurlencode($safe);
            }
        }
        if (isset($_GET['loggedout'])) {
            $args['loggedout'] = 'true';
        }
        if (!empty($_REQUEST['reauth'])) {
            $args['reauth'] = '1';
        }
        if ($args) {
            $target = add_query_arg($args, $target);
        }
    }

    // A POST is never processed here (no password guessing against wp-login.php): 303 sends it to the page as a GET.
    wp_safe_redirect($target, $status);
    exit;
}
add_action('login_init', 'wpum_override_wp_login', 1);
