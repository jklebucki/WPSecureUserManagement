<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Turnstile" submenu of the User Management menu (Settings API).
 */

function wpum_turnstile_add_submenu() {
    add_submenu_page(
        'wp_user_management',
        __('Cloudflare Turnstile', 'wp-user-management-plugin'),
        __('Turnstile', 'wp-user-management-plugin'),
        'manage_options',
        'wp_user_management_turnstile',
        'wpum_turnstile_settings_page'
    );
}
add_action('admin_menu', 'wpum_turnstile_add_submenu', 20);

function wpum_turnstile_register_settings() {
    register_setting('wpum_turnstile_group', 'wpum_turnstile', array(
        'type'              => 'array',
        'sanitize_callback' => 'wpum_turnstile_sanitize_options',
        'default'           => wpum_turnstile_defaults(),
    ));
}
add_action('admin_init', 'wpum_turnstile_register_settings');

function wpum_turnstile_clean_key($value) {
    return preg_replace('/[^A-Za-z0-9_\-]/', '', trim((string) $value));
}

function wpum_turnstile_sanitize_options($input) {
    $input    = is_array($input) ? $input : array();
    $old      = get_option('wpum_turnstile', array());
    $defaults = wpum_turnstile_defaults();
    $out      = $defaults;

    $out['enabled']  = !empty($input['enabled']) ? 1 : 0;
    $out['site_key'] = wpum_turnstile_clean_key(isset($input['site_key']) ? $input['site_key'] : '');

    // The secret is never printed back: an empty field keeps the stored value.
    $secret_in = wpum_turnstile_clean_key(isset($input['secret_key']) ? $input['secret_key'] : '');
    if ('' !== $secret_in) {
        $out['secret_key'] = $secret_in;
    } elseif (empty($input['clear_secret']) && is_array($old) && isset($old['secret_key'])) {
        $out['secret_key'] = (string) $old['secret_key'];
    }

    foreach (array_keys(wpum_turnstile_form_labels()) as $form_key) {
        $out['form_' . $form_key] = !empty($input['form_' . $form_key]) ? 1 : 0;
    }

    $choices = array(
        'theme'      => array('auto', 'light', 'dark'),
        'size'       => array('flexible', 'normal', 'compact'),
        'appearance' => array('always', 'execute', 'interaction-only'),
    );
    foreach ($choices as $field => $allowed) {
        $value        = isset($input[$field]) ? (string) $input[$field] : $defaults[$field];
        $out[$field]  = in_array($value, $allowed, true) ? $value : $defaults[$field];
    }

    $language = isset($input['language']) ? strtolower(trim((string) $input['language'])) : 'auto';
    $out['language'] = preg_match('/^(auto|[a-z]{2}(-[a-z]{2})?)$/', $language) ? $language : 'auto';

    $out['fail_open'] = !empty($input['fail_open']) ? 1 : 0;

    delete_transient('wpum_turnstile_config_error');

    return $out;
}

// Admin assets only on this page.
function wpum_turnstile_admin_assets() {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ('wp_user_management_turnstile' !== $page) {
        return;
    }

    wp_enqueue_script('wpum-turnstile-preview', WPUM_TURNSTILE_SCRIPT_URL . '?render=explicit', array(), null, true);
    wp_enqueue_script('wpum-admin-turnstile', WPUM_PLUGIN_URL . 'assets/js/admin-turnstile.js', array('jquery', 'wpum-turnstile-preview'), WPUM_VERSION, true);
    wp_localize_script('wpum-admin-turnstile', 'wpumTurnstileAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('wpum_turnstile_test'),
        'siteKey' => wpum_turnstile_site_key(),
        'i18n'    => array(
            'testing'      => __('Checking...', 'wp-user-management-plugin'),
            'error'        => __('Request failed. Please try again.', 'wp-user-management-plugin'),
            'noSiteKey'    => __('Enter the site key first.', 'wp-user-management-plugin'),
            'previewError' => __('The widget could not be loaded. Check the site key and that this domain is allowed for the key.', 'wp-user-management-plugin'),
        ),
    ));
}
add_action('admin_enqueue_scripts', 'wpum_turnstile_admin_assets');

// AJAX: validate the secret key against Cloudflare without needing a real token.
function wpum_ajax_turnstile_test() {
    check_ajax_referer('wpum_turnstile_test', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You are not allowed to do this.', 'wp-user-management-plugin')), 403);
    }

    $secret = isset($_POST['secret_key']) ? wpum_turnstile_clean_key(wp_unslash($_POST['secret_key'])) : '';
    if ('' === $secret) {
        $secret = wpum_turnstile_secret_key();
    }
    $site_key = isset($_POST['site_key']) ? wpum_turnstile_clean_key(wp_unslash($_POST['site_key'])) : '';

    if ('' === $secret) {
        wp_send_json_error(array('message' => __('Enter the secret key first.', 'wp-user-management-plugin')));
    }

    $data = wpum_turnstile_request($secret, 'wpum-key-test');
    if (is_wp_error($data)) {
        wp_send_json_error(array('message' => sprintf(
            /* translators: %s: error message */
            __('Could not reach Cloudflare: %s', 'wp-user-management-plugin'),
            $data->get_error_message()
        )));
    }

    $codes = isset($data['error-codes']) ? array_map('strval', (array) $data['error-codes']) : array();
    if (array_intersect(array('invalid-input-secret', 'missing-input-secret'), $codes)) {
        wp_send_json_error(array('message' => __('Cloudflare rejected the secret key. Check that you copied it correctly.', 'wp-user-management-plugin')));
    }

    delete_transient('wpum_turnstile_config_error');

    $message = __('The secret key is accepted by Cloudflare.', 'wp-user-management-plugin');
    if ('' !== $site_key && !preg_match('/^[0-3]x[A-Za-z0-9_\-]{10,}$/', $site_key)) {
        $message .= ' ' . __('Warning: the site key does not look like a Turnstile site key.', 'wp-user-management-plugin');
    }

    wp_send_json_success(array('message' => $message));
}
add_action('wp_ajax_wpum_turnstile_test', 'wpum_ajax_turnstile_test');

// Warn about a broken setup (enabled without keys, or Cloudflare rejected the secret).
function wpum_turnstile_admin_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $options = wpum_turnstile_get_options();
    if (empty($options['enabled']) || (defined('WPUM_TURNSTILE_DISABLE') && WPUM_TURNSTILE_DISABLE)) {
        return;
    }

    $settings_url = admin_url('admin.php?page=wp_user_management_turnstile');

    if ('' === wpum_turnstile_site_key() || '' === wpum_turnstile_secret_key()) {
        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('Cloudflare Turnstile is enabled but the site key or secret key is missing, so the forms are not protected.', 'wp-user-management-plugin'),
            esc_url($settings_url),
            esc_html__('Open Turnstile settings', 'wp-user-management-plugin')
        );
        return;
    }

    if (get_transient('wpum_turnstile_config_error')) {
        printf(
            '<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('Cloudflare rejected the Turnstile secret key, so the security check is being skipped until you fix it.', 'wp-user-management-plugin'),
            esc_url($settings_url),
            esc_html__('Open Turnstile settings', 'wp-user-management-plugin')
        );
    }
}
add_action('admin_notices', 'wpum_turnstile_admin_notices');

// The settings page
function wpum_turnstile_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $o               = wpum_turnstile_get_options();
    $site_const      = defined('WPUM_TURNSTILE_SITE_KEY') && '' !== WPUM_TURNSTILE_SITE_KEY;
    $secret_const    = defined('WPUM_TURNSTILE_SECRET_KEY') && '' !== WPUM_TURNSTILE_SECRET_KEY;
    $disabled_const  = defined('WPUM_TURNSTILE_DISABLE') && WPUM_TURNSTILE_DISABLE;
    $has_secret      = '' !== wpum_turnstile_secret_key();
    $site_key        = wpum_turnstile_site_key();
    $is_live         = !empty($o['enabled']) && '' !== $site_key && $has_secret && !$disabled_const;
    ?>
    <div class="wrap wpum-admin">
        <h1><?php esc_html_e('Cloudflare Turnstile', 'wp-user-management-plugin'); ?></h1>
        <p class="wpum-lead"><?php esc_html_e('A privacy-friendly CAPTCHA alternative that protects login, registration and password reset forms from bots.', 'wp-user-management-plugin'); ?></p>

        <?php settings_errors(); ?>

        <?php if ($disabled_const) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e('Turnstile is switched off by the WPUM_TURNSTILE_DISABLE constant in wp-config.php.', 'wp-user-management-plugin'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="options.php" id="wpum-turnstile-form">
            <?php settings_fields('wpum_turnstile_group'); ?>

            <div class="wpum-card">
                <div class="wpum-card__head">
                    <h2><?php esc_html_e('Status', 'wp-user-management-plugin'); ?></h2>
                    <span class="wpum-pill <?php echo $is_live ? 'wpum-pill--ok' : 'wpum-pill--off'; ?>">
                        <?php echo $is_live ? esc_html__('Active', 'wp-user-management-plugin') : esc_html__('Not active', 'wp-user-management-plugin'); ?>
                    </span>
                </div>
                <label class="wpum-switch">
                    <input type="checkbox" name="wpum_turnstile[enabled]" value="1" <?php checked(!empty($o['enabled'])); ?>>
                    <span><?php esc_html_e('Enable Cloudflare Turnstile', 'wp-user-management-plugin'); ?></span>
                </label>
                <p class="description"><?php esc_html_e('Forms are protected only when Turnstile is enabled and both keys are filled in.', 'wp-user-management-plugin'); ?></p>
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('API keys', 'wp-user-management-plugin'); ?></h2></div>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: link to the Cloudflare dashboard */
                        esc_html__('Create a Turnstile widget in the %s and paste its keys here.', 'wp-user-management-plugin'),
                        '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">' . esc_html__('Cloudflare dashboard', 'wp-user-management-plugin') . '</a>'
                    );
                    ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wpum-ts-site"><?php esc_html_e('Site key', 'wp-user-management-plugin'); ?></label></th>
                        <td>
                            <input type="text" id="wpum-ts-site" name="wpum_turnstile[site_key]" class="regular-text code"
                                   value="<?php echo esc_attr($o['site_key']); ?>" autocomplete="off" spellcheck="false" <?php disabled($site_const); ?>>
                            <?php if ($site_const) : ?>
                                <p class="description"><?php esc_html_e('Defined in wp-config.php (WPUM_TURNSTILE_SITE_KEY).', 'wp-user-management-plugin'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpum-ts-secret"><?php esc_html_e('Secret key', 'wp-user-management-plugin'); ?></label></th>
                        <td>
                            <input type="password" id="wpum-ts-secret" name="wpum_turnstile[secret_key]" class="regular-text code"
                                   value="" autocomplete="new-password" spellcheck="false"
                                   placeholder="<?php echo $has_secret ? esc_attr__('Saved - leave empty to keep it', 'wp-user-management-plugin') : ''; ?>" <?php disabled($secret_const); ?>>
                            <?php if ($secret_const) : ?>
                                <p class="description"><?php esc_html_e('Defined in wp-config.php (WPUM_TURNSTILE_SECRET_KEY).', 'wp-user-management-plugin'); ?></p>
                            <?php elseif ($has_secret) : ?>
                                <label class="wpum-inline-check">
                                    <input type="checkbox" name="wpum_turnstile[clear_secret]" value="1">
                                    <?php esc_html_e('Remove the saved secret key', 'wp-user-management-plugin'); ?>
                                </label>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Check the keys', 'wp-user-management-plugin'); ?></th>
                        <td>
                            <button type="button" class="button" id="wpum-ts-test"><?php esc_html_e('Test keys', 'wp-user-management-plugin'); ?></button>
                            <button type="button" class="button" id="wpum-ts-preview"><?php esc_html_e('Preview widget', 'wp-user-management-plugin'); ?></button>
                            <span id="wpum-ts-result" class="wpum-test-result" role="status"></span>
                            <div id="wpum-ts-widget" class="wpum-preview"></div>
                            <p class="description">
                                <?php esc_html_e('Cloudflare test keys: site key 1x00000000000000000000AA always passes, 2x00000000000000000000AB always blocks. Secrets: 1x0000000000000000000000000000000AA (pass), 2x0000000000000000000000000000000AA (block).', 'wp-user-management-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('Protected forms', 'wp-user-management-plugin'); ?></h2></div>
                <div class="wpum-checks">
                    <?php foreach (wpum_turnstile_form_labels() as $form_key => $form_label) : ?>
                        <label class="wpum-inline-check">
                            <input type="checkbox" name="wpum_turnstile[form_<?php echo esc_attr($form_key); ?>]" value="1" <?php checked(!empty($o['form_' . $form_key])); ?>>
                            <?php echo esc_html($form_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><?php esc_html_e('The wp-login.php options above only matter while wp-login.php is not disabled under Configuration.', 'wp-user-management-plugin'); ?></p>
                <p class="description"><?php esc_html_e('Tip: run "Test keys" before enabling the wp-login.php forms. If you ever get locked out, add define( \'WPUM_TURNSTILE_DISABLE\', true ); to wp-config.php.', 'wp-user-management-plugin'); ?></p>
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('Widget appearance', 'wp-user-management-plugin'); ?></h2></div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wpum-ts-theme"><?php esc_html_e('Theme', 'wp-user-management-plugin'); ?></label></th>
                        <td>
                            <select id="wpum-ts-theme" name="wpum_turnstile[theme]">
                                <option value="auto" <?php selected($o['theme'], 'auto'); ?>><?php esc_html_e('Auto', 'wp-user-management-plugin'); ?></option>
                                <option value="light" <?php selected($o['theme'], 'light'); ?>><?php esc_html_e('Light', 'wp-user-management-plugin'); ?></option>
                                <option value="dark" <?php selected($o['theme'], 'dark'); ?>><?php esc_html_e('Dark', 'wp-user-management-plugin'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpum-ts-size"><?php esc_html_e('Size', 'wp-user-management-plugin'); ?></label></th>
                        <td>
                            <select id="wpum-ts-size" name="wpum_turnstile[size]">
                                <option value="flexible" <?php selected($o['size'], 'flexible'); ?>><?php esc_html_e('Flexible (full width)', 'wp-user-management-plugin'); ?></option>
                                <option value="normal" <?php selected($o['size'], 'normal'); ?>><?php esc_html_e('Normal', 'wp-user-management-plugin'); ?></option>
                                <option value="compact" <?php selected($o['size'], 'compact'); ?>><?php esc_html_e('Compact', 'wp-user-management-plugin'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpum-ts-appearance"><?php esc_html_e('Appearance', 'wp-user-management-plugin'); ?></label></th>
                        <td>
                            <select id="wpum-ts-appearance" name="wpum_turnstile[appearance]">
                                <option value="always" <?php selected($o['appearance'], 'always'); ?>><?php esc_html_e('Always visible', 'wp-user-management-plugin'); ?></option>
                                <option value="execute" <?php selected($o['appearance'], 'execute'); ?>><?php esc_html_e('Visible only while verifying', 'wp-user-management-plugin'); ?></option>
                                <option value="interaction-only" <?php selected($o['appearance'], 'interaction-only'); ?>><?php esc_html_e('Visible only when interaction is needed', 'wp-user-management-plugin'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpum-ts-lang"><?php esc_html_e('Language', 'wp-user-management-plugin'); ?></label></th>
                        <td>
                            <input type="text" id="wpum-ts-lang" name="wpum_turnstile[language]" class="small-text" value="<?php echo esc_attr($o['language']); ?>" maxlength="5">
                            <p class="description"><?php esc_html_e('"auto" follows the visitor\'s browser; or a code such as pl, en, de, fr.', 'wp-user-management-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('Behaviour', 'wp-user-management-plugin'); ?></h2></div>
                <div class="wpum-checks">
                    <label class="wpum-inline-check">
                        <input type="checkbox" name="wpum_turnstile[fail_open]" value="1" <?php checked(!empty($o['fail_open'])); ?>>
                        <?php esc_html_e('Let visitors through when Cloudflare cannot be reached (fail open)', 'wp-user-management-plugin'); ?>
                    </label>
                </div>
            </div>

            <?php submit_button(__('Save settings', 'wp-user-management-plugin')); ?>
        </form>
    </div>
    <?php
}
