<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registration with email confirmation.
 *
 * The visitor fills in the form; no WordPress account exists yet. The data (with the password already hashed) is parked
 * in {prefix}wpum_pending_registrations under the SHA-256 of a random key, and an email with a link carrying that key
 * is sent in the language the visitor chose. The account is created only when the link is opened, so unconfirmed
 * sign-ups never exist as users and never trigger other plugins' user_register hooks.
 *
 * Unconfirmed sign-ups are removed by a WP-Cron event (twice daily) after the number of days set under
 * User Management -> Configuration (default 2). Expired rows are ignored immediately, the cron run only tidies up.
 */

/* ------------------------------------------------------------------ */
/* Languages                                                           */
/* ------------------------------------------------------------------ */

// Native names of languages we know; unknown locales are shown by their code.
function wpum_locale_labels() {
    return array(
        'en_US' => 'English',
        'pl_PL' => 'Polski',
        'de_DE' => 'Deutsch',
        'fr_FR' => 'Français',
        'es_ES' => 'Español',
        'it_IT' => 'Italiano',
        'nl_NL' => 'Nederlands',
        'pt_BR' => 'Português (Brasil)',
        'pt_PT' => 'Português',
        'cs_CZ' => 'Čeština',
        'sk_SK' => 'Slovenčina',
        'uk'    => 'Українська',
        'ru_RU' => 'Русский',
        'sv_SE' => 'Svenska',
        'da_DK' => 'Dansk',
    );
}

/**
 * Languages offered on the registration form: English (the source language) plus every language the plugin ships
 * a compiled catalogue for (languages/wp-user-management-plugin-{locale}.mo). Drop in another .mo to add a language.
 */
function wpum_get_available_locales() {
    static $locales = null;

    if (null !== $locales) {
        return $locales;
    }

    $labels  = wpum_locale_labels();
    $locales = array('en_US' => $labels['en_US']);

    foreach ((array) glob(WPUM_PLUGIN_DIR . 'languages/wp-user-management-plugin-*.mo') as $file) {
        if (preg_match('/wp-user-management-plugin-([a-z]{2,3}(?:_[A-Za-z0-9]{2,4})?)\.mo$/', basename($file), $match)) {
            $code           = $match[1];
            $locales[$code] = isset($labels[$code]) ? $labels[$code] : $code;
        }
    }

    asort($locales, SORT_NATURAL | SORT_FLAG_CASE);

    $locales = (array) apply_filters('wpum_available_locales', $locales);

    return $locales;
}

// The language pre-selected on the form: the page's own language when we have it, otherwise English.
function wpum_default_locale() {
    $available = wpum_get_available_locales();
    $current   = function_exists('determine_locale') ? determine_locale() : get_locale();

    if (isset($available[$current])) {
        return $current;
    }

    $language = strtolower(substr($current, 0, 2));
    foreach (array_keys($available) as $code) {
        if (strtolower(substr($code, 0, 2)) === $language) {
            return $code;
        }
    }

    return isset($available['en_US']) ? 'en_US' : (string) key($available);
}

// Accept only a language we offer; anything else falls back to the default.
function wpum_sanitize_locale($value) {
    $value     = is_string($value) ? trim($value) : '';
    $available = wpum_get_available_locales();

    return isset($available[$value]) ? $value : wpum_default_locale();
}

// Compiled catalogue of a locale (loaded once), or false for English / when there is none.
function wpum_get_catalogue($locale) {
    static $catalogues = array();

    if (!is_string($locale) || '' === $locale || 'en_US' === $locale || !preg_match('/^[A-Za-z0-9_]+$/', $locale)) {
        return false;
    }

    if (!isset($catalogues[$locale])) {
        $catalogues[$locale] = false;
        $file                = WPUM_PLUGIN_DIR . 'languages/wp-user-management-plugin-' . $locale . '.mo';

        if (is_readable($file)) {
            if (!class_exists('MO')) {
                require_once ABSPATH . WPINC . '/pomo/mo.php';
            }
            $mo = new MO();
            if ($mo->import_from_file($file)) {
                $catalogues[$locale] = $mo;
            }
        }
    }

    return $catalogues[$locale];
}

/**
 * Translate a plugin string into a specific locale, independent of the language of the current request.
 * Used for emails, which must be written in the recipient's language, not the language of the page.
 */
function wpum_t($text, $locale) {
    $catalogue = wpum_get_catalogue($locale);

    return $catalogue ? $catalogue->translate($text) : $text;
}

// Plural-aware version of wpum_t() (uses the plural rules of the target language).
function wpum_tn($single, $plural, $count, $locale) {
    $catalogue = wpum_get_catalogue($locale);

    if ($catalogue) {
        return $catalogue->translate_plural($single, $plural, (int) $count);
    }

    return 1 === (int) $count ? $single : $plural;
}

/* ------------------------------------------------------------------ */
/* Pending registrations (table + WP-Cron cleanup)                     */
/* ------------------------------------------------------------------ */

function wpum_pending_table() {
    global $wpdb;

    return $wpdb->prefix . 'wpum_pending_registrations';
}

function wpum_create_pending_registrations_table() {
    global $wpdb;

    $table = wpum_pending_table();
    $sql   = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        key_hash char(64) NOT NULL,
        email varchar(100) NOT NULL,
        data longtext NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY key_hash (key_hash),
        KEY email (email),
        KEY expires_at (expires_at)
    ) " . $wpdb->get_charset_collate() . ';';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    return wpum_table_exists($table);
}

// Make sure the table exists (checked at most once per request).
function wpum_ensure_pending_table() {
    static $ok = null;

    if (null === $ok) {
        $ok = wpum_table_exists(wpum_pending_table()) || wpum_create_pending_registrations_table();
    }

    return $ok;
}

// Days an unconfirmed registration is kept (Configuration page, default 2).
function wpum_get_unconfirmed_days() {
    return max(1, min(30, (int) get_option('wpum_unconfirmed_days', 2)));
}

function wpum_registration_ttl() {
    return max(HOUR_IN_SECONDS, (int) apply_filters('wpum_registration_ttl', wpum_get_unconfirmed_days() * DAY_IN_SECONDS));
}

// The TTL in whole days, as shown in messages.
function wpum_registration_days() {
    return max(1, (int) round(wpum_registration_ttl() / DAY_IN_SECONDS));
}

// Only the hash of the key is stored, so a database leak does not expose usable links.
function wpum_registration_key_hash($key) {
    return hash('sha256', (string) $key);
}

/**
 * Store a sign-up waiting for confirmation. A newer sign-up for the same email replaces the older one, so only the
 * newest link works.
 *
 * @param array $data login, email, first_name, last_name, locale, password (plain text, hashed here)
 * @return array|false key (for the link) and expires (timestamp), or false when it could not be stored
 */
function wpum_create_pending_registration($data) {
    global $wpdb;

    if (!wpum_ensure_pending_table()) {
        return false;
    }

    $table   = wpum_pending_table();
    $now     = time();
    $expires = $now + wpum_registration_ttl();
    $key     = wp_generate_password(40, false);

    $wpdb->delete($table, array('email' => strtolower($data['email'])), array('%s'));

    $stored = $wpdb->insert($table, array(
        'key_hash'   => wpum_registration_key_hash($key),
        'email'      => strtolower($data['email']),
        'data'       => wp_json_encode(array(
            'login'      => $data['login'],
            'email'      => $data['email'],
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'locale'     => $data['locale'],
            // WordPress hashes and compares passwords in the slashed form in which they arrive in $_POST (wp-login.php does
        // the same), so hash the slashed form; otherwise a password with ' " or \ would not match there.
        'pass_hash'  => wp_hash_password(wp_slash($data['password'])),
        )),
        'created_at' => gmdate('Y-m-d H:i:s', $now),
        'expires_at' => gmdate('Y-m-d H:i:s', $expires),
    ), array('%s', '%s', '%s', '%s', '%s'));

    return $stored ? array('key' => $key, 'expires' => $expires) : false;
}

function wpum_delete_pending_registration($key) {
    global $wpdb;

    if (wpum_ensure_pending_table()) {
        $wpdb->delete(wpum_pending_table(), array('key_hash' => wpum_registration_key_hash($key)), array('%s'));
    }
}

// Remove waiting sign-ups of an address (privacy eraser). Returns the number of removed rows.
function wpum_delete_pending_registrations_by_email($email) {
    global $wpdb;

    if (!wpum_ensure_pending_table()) {
        return 0;
    }

    return (int) $wpdb->delete(wpum_pending_table(), array('email' => strtolower($email)), array('%s'));
}

// Sign-ups of an address that are still valid (privacy exporter).
function wpum_get_pending_registrations_by_email($email) {
    global $wpdb;

    if (!wpum_ensure_pending_table()) {
        return array();
    }

    $table = wpum_pending_table();

    return (array) $wpdb->get_results($wpdb->prepare(
        "SELECT id, data, created_at, expires_at FROM $table WHERE email = %s AND expires_at > %s",
        strtolower($email),
        gmdate('Y-m-d H:i:s')
    ));
}

// How many valid sign-ups are waiting for confirmation (shown on the Configuration page).
function wpum_count_pending_registrations() {
    global $wpdb;

    if (!wpum_ensure_pending_table()) {
        return 0;
    }

    $table = wpum_pending_table();

    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE expires_at > %s", gmdate('Y-m-d H:i:s')));
}

/**
 * Find and remove the sign-up behind a key. Returns its data, or null when the key is unknown, expired or used.
 * The DELETE doubles as a lock: only the request that actually removed the row gets the data.
 */
function wpum_consume_pending_registration($key) {
    global $wpdb;

    if (!wpum_ensure_pending_table()) {
        return null;
    }

    $table = wpum_pending_table();
    $row   = $wpdb->get_row($wpdb->prepare(
        "SELECT id, data FROM $table WHERE key_hash = %s AND expires_at > %s",
        wpum_registration_key_hash($key),
        gmdate('Y-m-d H:i:s')
    ));
    if (!$row || 1 !== (int) $wpdb->delete($table, array('id' => (int) $row->id), array('%d'))) {
        return null;
    }

    $data = json_decode($row->data, true);
    if (!is_array($data) || empty($data['login']) || empty($data['email']) || empty($data['pass_hash'])) {
        return null;
    }

    return $data;
}

/**
 * WP-Cron worker: delete sign-ups that were not confirmed in time.
 *
 * WordPress has no daemon; WP-Cron runs scheduled events when the site gets a visit. On quiet sites add a real
 * cron job hitting wp-cron.php (and define DISABLE_WP_CRON) so the event fires on time.
 *
 * @return int number of deleted sign-ups
 */
function wpum_purge_expired_registrations() {
    global $wpdb;

    $table = wpum_pending_table();
    if (!wpum_table_exists($table)) {
        return 0;
    }

    $deleted = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at <= %s", gmdate('Y-m-d H:i:s')));

    return (int) $deleted;
}
add_action('wpum_cron_purge_registrations', 'wpum_purge_expired_registrations');

// Register the recurring event (also repairs it if something removed it, e.g. a cron-cleaning tool).
function wpum_schedule_registration_cleanup() {
    if (!wp_next_scheduled('wpum_cron_purge_registrations')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', 'wpum_cron_purge_registrations');
    }
}
add_action('init', 'wpum_schedule_registration_cleanup');

function wpum_unschedule_registration_cleanup() {
    wp_clear_scheduled_hook('wpum_cron_purge_registrations');
}

/* ------------------------------------------------------------------ */
/* The email                                                           */
/* ------------------------------------------------------------------ */

function wpum_activation_url($key) {
    return add_query_arg('wpum_confirm', rawurlencode($key), wpum_get_page_url('account'));
}

// Render a template from templates/ (override the path with the wpum_email_template_path filter).
function wpum_render_email_template($template, $s) {
    $file = apply_filters('wpum_email_template_path', WPUM_PLUGIN_DIR . 'templates/' . $template . '.php', $template);

    ob_start();
    include $file;

    return (string) ob_get_clean();
}

/**
 * Build the confirmation email in the visitor's language.
 *
 * @param array  $data login, email, first_name, locale, expires (timestamp)
 * @param string $url  confirmation link
 * @return array subject, html, text
 */
function wpum_build_activation_email($data, $url) {
    $locale  = $data['locale'];
    $site    = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $name    = '' !== $data['first_name'] ? $data['first_name'] : $data['login'];
    $days    = wpum_registration_days();
    $expires = wp_date('Y-m-d H:i T', (int) $data['expires']);

    $s = array(
        'lang'      => str_replace('_', '-', $locale),
        'site'      => $site,
        'site_url'  => home_url('/'),
        'url'       => $url,
        'preheader' => wpum_t('Confirm your email address to activate your account.', $locale),
        'title'     => wpum_t('Confirm your registration', $locale),
        /* translators: %s: first name of the person registering */
        'greeting'  => sprintf(wpum_t('Hi %s,', $locale), $name),
        /* translators: %s: site name */
        'intro'     => sprintf(wpum_t('Thanks for signing up at %s. Please confirm your email address to activate your account.', $locale), $site),
        'button'    => wpum_t('Confirm registration', $locale),
        'fallback'  => wpum_t('If the button does not work, copy and paste this link into your browser:', $locale),
        /* translators: %s: username */
        'username'  => sprintf(wpum_t('Your username: %s', $locale), $data['login']),
        /* translators: 1: number of days, 2: date and time the link stops working */
        'expiry'    => sprintf(wpum_tn('This link is valid for %1$d day (until %2$s).', 'This link is valid for %1$d days (until %2$s).', $days, $locale), $days, $expires),
        /* translators: %d: number of days */
        'deletion'  => sprintf(wpum_tn('If you do not confirm within %d day, your registration is deleted automatically.', 'If you do not confirm within %d days, your registration is deleted automatically.', $days, $locale), $days),
        'ignore'    => wpum_t('If you did not sign up, you can safely ignore this email. No account will be created.', $locale),
        /* translators: %s: site name */
        'footer'    => sprintf(wpum_t('This message was sent by %s. Please do not reply to it.', $locale), $site),
    );

    $s = (array) apply_filters('wpum_activation_email_strings', $s, $data);

    $text  = $s['title'] . "\n\n";
    $text .= $s['greeting'] . "\n\n";
    $text .= $s['intro'] . "\n\n";
    $text .= $s['button'] . ":\n" . $url . "\n\n";
    $text .= $s['username'] . "\n";
    $text .= $s['expiry'] . "\n";
    $text .= $s['deletion'] . "\n\n";
    $text .= $s['ignore'] . "\n\n";
    $text .= "--\n" . $s['footer'] . "\n";

    return array(
        /* translators: %s: site name */
        'subject' => sprintf(wpum_t('[%s] Confirm your registration', $locale), $site),
        'html'    => wpum_render_email_template('email-activation', $s),
        'text'    => $text,
    );
}

// Send a multipart (HTML + plain text) email.
function wpum_send_html_mail($to, $subject, $html, $text) {
    $add_text_part = function ($phpmailer) use ($text) {
        $phpmailer->AltBody = $text;
    };

    add_action('phpmailer_init', $add_text_part);
    $sent = wp_mail($to, $subject, $html, array('Content-Type: text/html; charset=UTF-8'));
    remove_action('phpmailer_init', $add_text_part);

    return $sent;
}

function wpum_send_activation_email($data, $key) {
    $mail = wpum_build_activation_email($data, wpum_activation_url($key));

    return wpum_send_html_mail($data['email'], $mail['subject'], $mail['html'], $mail['text']);
}

/* ------------------------------------------------------------------ */
/* Confirmation link                                                   */
/* ------------------------------------------------------------------ */

// Opening the link in the email creates the account (GET: harmless for mail scanners, the link is single use).
function wpum_process_activation() {
    if (!isset($_GET['wpum_confirm']) || !is_string($_GET['wpum_confirm'])) {
        return;
    }

    $return_url = wpum_get_page_url('account');
    $ip         = wpum_get_client_ip();

    if (wpum_rate_limit_is_blocked('activation_ip', $ip)) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'rate_limited');
    }
    wpum_rate_limit_hit('activation_ip', $ip);

    $key = preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['wpum_confirm']));
    if (strlen($key) < 20 || strlen($key) > 80) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'activation_invalid');
    }

    $data = wpum_consume_pending_registration($key);
    if (!$data) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'activation_invalid');
    }

    if (username_exists($data['login']) || email_exists($data['email'])) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'activation_conflict');
    }

    // wp_insert_user() unslashes its input, so slash the data first.
    $user_id = wp_insert_user(wp_slash(array(
        'user_login' => $data['login'],
        'user_email' => $data['email'],
        'user_pass'  => wp_generate_password(32, true, true),
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'locale'     => $data['locale'],
        'role'       => get_option('default_role'),
    )));
    if (is_wp_error($user_id)) {
        wpum_redirect_with_flash($return_url, 'login', 'error', 'register_failed');
    }

    // Replace the throw-away password with the hash of the one the visitor chose.
    global $wpdb;
    $wpdb->update($wpdb->users, array('user_pass' => $data['pass_hash']), array('ID' => (int) $user_id), array('%s'), array('%d'));
    clean_user_cache($user_id);

    do_action('wpum_registration_confirmed', $user_id, $data);

    wpum_redirect_with_flash($return_url, 'login', 'success', 'activated');
}
add_action('template_redirect', 'wpum_process_activation', 5);
