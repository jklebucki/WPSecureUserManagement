<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Meta keys for user data
function wpum_get_meta_label($meta_key)
{
    $meta_labels = array(
        'club_number' => __('Club Number', 'wp-user-management-plugin'),
    );
    return isset($meta_labels[$meta_key]) ? $meta_labels[$meta_key] : ucfirst(str_replace('_', ' ', $meta_key));
}

// Tab id that should be open after a redirect (the one whose form produced the notice).
function wpum_profile_active_tab()
{
    $flash = wpum_flash_get();
    if (!$flash) {
        return 'profile';
    }

    $map = array(
        'profile'  => 'profile',
        'userdata' => 'user-data',
        'password' => 'change-password',
        'delete'   => 'delete-account',
        'logout'   => 'logout',
    );

    return isset($map[$flash['form']]) ? $map[$flash['form']] : 'profile';
}

// Display profile edit form
function wpum_display_profile_edit_form()
{
    if (!is_user_logged_in()) {
        return wpum_display_login_form();
    }

    wpum_enqueue_profile_assets();

    $current_user = wp_get_current_user();
    $meta_keys    = wpum_get_allowed_user_meta_keys();
    $active       = wpum_profile_active_tab();
    $min_length   = wpum_get_min_password_length();

    $tabs = array(
        'profile'              => __('Profile', 'wp-user-management-plugin'),
        'user-data'            => __('User Data', 'wp-user-management-plugin'),
        'change-password'      => __('Change Password', 'wp-user-management-plugin'),
        'shooting-credentials' => __('Shooting Credentials', 'wp-user-management-plugin'),
        'delete-account'       => __('Delete Account', 'wp-user-management-plugin'),
        'logout'               => __('Logout', 'wp-user-management-plugin'),
    );
    if (empty($meta_keys)) {
        unset($tabs['user-data']);
    }

    ob_start(); ?>
    <div class="wpum-ui wpum-ui--profile">
        <div class="wpum-card wpum-profile">
            <div class="wpum-tabs" role="tablist" aria-label="<?php esc_attr_e('Account', 'wp-user-management-plugin'); ?>">
                <?php foreach ($tabs as $tab_id => $tab_label) : ?>
                    <button type="button" role="tab" id="wpum-tab-<?php echo esc_attr($tab_id); ?>"
                            aria-controls="<?php echo esc_attr($tab_id); ?>" aria-selected="<?php echo $tab_id === $active ? 'true' : 'false'; ?>"
                            class="wpum-tab<?php echo $tab_id === $active ? ' is-active' : ''; ?><?php echo 'delete-account' === $tab_id ? ' wpum-tab--danger' : ''; ?>"
                            data-tab="<?php echo esc_attr($tab_id); ?>"><?php echo esc_html($tab_label); ?></button>
                <?php endforeach; ?>
            </div>

            <div id="profile" class="wpum-panel<?php echo 'profile' === $active ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="wpum-tab-profile">
                <?php wpum_flash_render('profile'); ?>
                <form id="wpum-profile-edit-form" method="post">
                    <?php
                    wpum_form_fields('profile');
                    wpum_render_field(array(
                        'id'           => 'wpum-username',
                        'name'         => 'wpum_username',
                        'label'        => __('Username', 'wp-user-management-plugin'),
                        'value'        => $current_user->user_login,
                        'readonly'     => true,
                        'autocomplete' => 'username',
                    ));
                    wpum_render_field(array(
                        'id'           => 'wpum-email',
                        'name'         => 'wpum_email',
                        'type'         => 'email',
                        'label'        => __('Email', 'wp-user-management-plugin'),
                        'value'        => $current_user->user_email,
                        'required'     => true,
                        'autocomplete' => 'email',
                    ));
                    wpum_render_field(array(
                        'id'           => 'wpum-firstname',
                        'name'         => 'wpum_firstname',
                        'label'        => __('First Name', 'wp-user-management-plugin'),
                        'value'        => get_user_meta($current_user->ID, 'first_name', true),
                        'required'     => true,
                        'autocomplete' => 'given-name',
                    ));
                    wpum_render_field(array(
                        'id'           => 'wpum-lastname',
                        'name'         => 'wpum_lastname',
                        'label'        => __('Last Name', 'wp-user-management-plugin'),
                        'value'        => get_user_meta($current_user->ID, 'last_name', true),
                        'required'     => true,
                        'autocomplete' => 'family-name',
                    ));
                    wpum_render_field(array(
                        'id'           => 'wpum-profile-current-password',
                        'name'         => 'wpum_current_password',
                        'type'         => 'password',
                        'label'        => __('Current Password', 'wp-user-management-plugin'),
                        'autocomplete' => 'current-password',
                        'help'         => __('Required only when you change your email address.', 'wp-user-management-plugin'),
                    ));
                    ?>
                    <div class="wpum-actions">
                        <button type="submit" class="wpum-btn wpum-btn--primary"><?php esc_html_e('Update Profile', 'wp-user-management-plugin'); ?></button>
                    </div>
                </form>
            </div>

            <?php if (!empty($meta_keys)) : ?>
                <div id="user-data" class="wpum-panel<?php echo 'user-data' === $active ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="wpum-tab-user-data">
                    <?php wpum_flash_render('userdata'); ?>
                    <form id="wpum-user-data-form" method="post">
                        <?php wpum_form_fields('userdata'); ?>
                        <?php foreach ($meta_keys as $meta_key) :
                            $meta_value = get_user_meta($current_user->ID, $meta_key, true);
                            wpum_render_field(array(
                                'id'    => 'wpumv-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $meta_key),
                                'name'  => 'wpum_meta[' . $meta_key . ']',
                                'label' => wpum_get_meta_label($meta_key),
                                'value' => is_scalar($meta_value) ? (string) $meta_value : '',
                            ));
                        endforeach; ?>
                        <div class="wpum-actions">
                            <button type="submit" class="wpum-btn wpum-btn--primary"><?php esc_html_e('Save Data', 'wp-user-management-plugin'); ?></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div id="change-password" class="wpum-panel<?php echo 'change-password' === $active ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="wpum-tab-change-password">
                <?php wpum_flash_render('password'); ?>
                <form id="wpum-change-password-form" method="post">
                    <?php
                    wpum_form_fields('password');
                    wpum_render_field(array(
                        'id'           => 'wpum-password-current',
                        'name'         => 'wpum_current_password',
                        'type'         => 'password',
                        'label'        => __('Current Password', 'wp-user-management-plugin'),
                        'required'     => true,
                        'autocomplete' => 'current-password',
                    ));
                    wpum_render_field(array(
                        'id'           => 'wpum-password',
                        'name'         => 'wpum_password',
                        'type'         => 'password',
                        'label'        => __('New Password', 'wp-user-management-plugin'),
                        'required'     => true,
                        'autocomplete' => 'new-password',
                        'attrs'        => array('minlength' => $min_length),
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
                    <div id="password-match-message" class="wpum-match" aria-live="polite"></div>
                    <div class="wpum-actions">
                        <button type="submit" id="wpum-change-password-button" class="wpum-btn wpum-btn--primary" data-wpum-match disabled><?php esc_html_e('Change Password', 'wp-user-management-plugin'); ?></button>
                    </div>
                </form>
            </div>

            <div id="shooting-credentials" class="wpum-panel" role="tabpanel" aria-labelledby="wpum-tab-shooting-credentials">
                <div id="wpum-messages" class="wpum-notice" role="status" hidden></div>

                <form id="wpum-shooting-credentials-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('wpum_shooting_credentials', 'wpum_nonce', false); ?>

                    <?php
                    $credentials      = wpum_get_user_credentials(get_current_user_id());
                    $credential_types = wpum_get_shooting_credential_types();

                    foreach ($credential_types as $type => $label) :
                        $current_credential = null;
                        foreach ($credentials as $cred) {
                            if ($cred->credential_type === $type) {
                                $current_credential = $cred;
                                break;
                            }
                        }
                    ?>
                        <fieldset class="wpum-fieldset">
                            <legend><?php echo esc_html($label); ?></legend>
                            <?php
                            wpum_render_field(array(
                                'id'    => 'wpum_' . $type . '_number',
                                'name'  => 'wpum_credentials[' . $type . '][number]',
                                'label' => __('Number', 'wp-user-management-plugin'),
                                'value' => $current_credential ? $current_credential->credential_number : '',
                            ));
                            ?>
                            <div class="wpum-field">
                                <label class="wpum-label" for="wpum_<?php echo esc_attr($type); ?>_file"><?php esc_html_e('PDF Document', 'wp-user-management-plugin'); ?></label>
                                <input type="file" class="wpum-input wpum-input--file"
                                       name="wpum_credentials[<?php echo esc_attr($type); ?>][file]"
                                       id="wpum_<?php echo esc_attr($type); ?>_file" accept="application/pdf,.pdf">
                                <?php if ($current_credential && '' !== $current_credential->file_path) : ?>
                                    <p class="wpum-help">
                                        <?php esc_html_e('Current file:', 'wp-user-management-plugin'); ?>
                                        <a href="<?php echo esc_url(wpum_credential_download_url($current_credential->id)); ?>" target="_blank" rel="noopener">
                                            <?php esc_html_e('View Document', 'wp-user-management-plugin'); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>

                    <div class="wpum-actions">
                        <button type="submit" class="wpum-btn wpum-btn--primary"><?php esc_html_e('Save Credentials', 'wp-user-management-plugin'); ?></button>
                    </div>
                </form>
            </div>

            <div id="delete-account" class="wpum-panel<?php echo 'delete-account' === $active ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="wpum-tab-delete-account">
                <?php wpum_flash_render('delete'); ?>
                <h3 class="wpum-subtitle"><?php esc_html_e('Delete Account', 'wp-user-management-plugin'); ?></h3>
                <p><?php esc_html_e('Deleting your account is permanent. Your personal data and uploaded documents will be removed.', 'wp-user-management-plugin'); ?></p>
                <div class="wpum-actions wpum-actions--center">
                    <button type="button" id="wpum-delete-account-button" class="wpum-btn wpum-btn--danger"><?php esc_html_e('Delete Account', 'wp-user-management-plugin'); ?></button>
                </div>
            </div>

            <div id="logout" class="wpum-panel<?php echo 'logout' === $active ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="wpum-tab-logout">
                <?php wpum_flash_render('logout'); ?>
                <h3 class="wpum-subtitle"><?php esc_html_e('Logout', 'wp-user-management-plugin'); ?></h3>
                <p><?php esc_html_e('Click the button below to logout from your account.', 'wp-user-management-plugin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpum_logout">
                    <input type="hidden" name="wpum_logout_nonce" value="<?php echo esc_attr(wp_create_nonce('wpum_logout_nonce')); ?>">
                    <div class="wpum-actions wpum-actions--center">
                        <button type="submit" class="wpum-btn wpum-btn--ghost"><?php esc_html_e('Logout', 'wp-user-management-plugin'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal dla usuwania konta (przenoszony na koniec body przez wpum-profile.js) -->
        <div id="wpum-delete-account-modal" class="wpum-ui wpum-modal" hidden role="dialog" aria-modal="true" aria-labelledby="wpum-delete-title">
            <div class="wpum-modal__dialog">
                <button type="button" class="wpum-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-user-management-plugin'); ?>">&times;</button>
                <h3 id="wpum-delete-title" class="wpum-subtitle"><?php esc_html_e('Are you sure you want to delete your account?', 'wp-user-management-plugin'); ?></h3>
                <form id="wpum-delete-account-form" method="post">
                    <?php
                    wpum_form_fields('delete');
                    wpum_render_field(array(
                        'id'           => 'wpum-delete-current-password',
                        'name'         => 'wpum_current_password',
                        'type'         => 'password',
                        'label'        => __('Confirm with your password', 'wp-user-management-plugin'),
                        'required'     => true,
                        'autocomplete' => 'current-password',
                    ));
                    ?>
                    <div class="wpum-actions wpum-actions--split">
                        <button type="button" class="wpum-btn wpum-btn--ghost wpum-cancel"><?php esc_html_e('Cancel', 'wp-user-management-plugin'); ?></button>
                        <button type="submit" class="wpum-btn wpum-btn--danger"><?php esc_html_e('Yes, Delete My Account', 'wp-user-management-plugin'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}

// Common entry for profile handlers: the user must be logged in.
function wpum_require_logged_in_user()
{
    if (!is_user_logged_in()) {
        wpum_redirect_with_flash(wpum_get_page_url('account'), 'login', 'error', 'not_logged_in');
    }

    return wp_get_current_user();
}

// Handle profile update (name + email)
function wpum_process_profile_update()
{
    if (!wpum_is_form_post('profile')) {
        return;
    }

    $user       = wpum_require_logged_in_user();
    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('profile')) {
        wpum_redirect_with_flash($return_url, 'profile', 'error', 'invalid_nonce');
    }

    $email     = sanitize_email(wpum_post_text('wpum_email'));
    $firstname = wpum_post_text('wpum_firstname');
    $lastname  = wpum_post_text('wpum_lastname');

    if ('' === $email || '' === $firstname || '' === $lastname) {
        wpum_redirect_with_flash($return_url, 'profile', 'error', 'fields_required');
    }
    if (!is_email($email)) {
        wpum_redirect_with_flash($return_url, 'profile', 'error', 'invalid_email');
    }

    // Changing the email needs the current password (protects against a hijacked session).
    if (0 !== strcasecmp($email, $user->user_email)) {
        $owner = email_exists($email);
        if ($owner && (int) $owner !== (int) $user->ID) {
            wpum_redirect_with_flash($return_url, 'profile', 'error', 'email_taken');
        }

        $check = wpum_verify_current_password($user->ID, wpum_post_raw('wpum_current_password'));
        if (is_wp_error($check)) {
            wpum_redirect_with_flash($return_url, 'profile', 'error', $check->get_error_code());
        }
    }

    // user_login is intentionally not sent: WordPress does not allow changing it.
    $result = wp_update_user(wp_slash(array(
        'ID'         => $user->ID,
        'user_email' => $email,
        'first_name' => $firstname,
        'last_name'  => $lastname,
    )));
    if (is_wp_error($result)) {
        wpum_redirect_with_flash($return_url, 'profile', 'error', 'update_failed');
    }

    wpum_redirect_with_flash($return_url, 'profile', 'success', 'profile_updated');
}
add_action('template_redirect', 'wpum_process_profile_update');

// Handle password change
function wpum_process_password_change()
{
    if (!wpum_is_form_post('password')) {
        return;
    }

    $user       = wpum_require_logged_in_user();
    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('password')) {
        wpum_redirect_with_flash($return_url, 'password', 'error', 'invalid_nonce');
    }

    $current  = wpum_post_raw('wpum_current_password');
    $password = wpum_post_raw('wpum_password');
    $confirm  = wpum_post_raw('wpum_confirm_password');

    if ('' === $current || '' === $password || '' === $confirm) {
        wpum_redirect_with_flash($return_url, 'password', 'error', 'fields_required');
    }
    if ($password !== $confirm) {
        wpum_redirect_with_flash($return_url, 'password', 'error', 'password_mismatch');
    }
    if (!wpum_validate_password_strength($password)) {
        wpum_redirect_with_flash($return_url, 'password', 'error', 'password_weak');
    }

    $check = wpum_verify_current_password($user->ID, $current);
    if (is_wp_error($check)) {
        wpum_redirect_with_flash($return_url, 'password', 'error', $check->get_error_code());
    }

    // wp_update_user keeps the current session alive (re-issues the auth cookie) and sends the change notice.
    // The slashed form is what WordPress hashes and what wp-login.php later compares against.
    $result = wp_update_user(array('ID' => $user->ID, 'user_pass' => wp_slash($password)));
    if (is_wp_error($result)) {
        wpum_redirect_with_flash($return_url, 'password', 'error', 'update_failed');
    }

    wpum_redirect_with_flash($return_url, 'password', 'success', 'password_changed');
}
add_action('template_redirect', 'wpum_process_password_change');

// Handle the "User Data" tab: only meta keys the admin exposed (and that are not protected) can be written.
function wpum_process_user_data()
{
    if (!wpum_is_form_post('userdata')) {
        return;
    }

    $user       = wpum_require_logged_in_user();
    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('userdata')) {
        wpum_redirect_with_flash($return_url, 'userdata', 'error', 'invalid_nonce');
    }

    $posted = isset($_POST['wpum_meta']) && is_array($_POST['wpum_meta']) ? wp_unslash($_POST['wpum_meta']) : array();

    foreach (wpum_get_allowed_user_meta_keys() as $meta_key) {
        if (isset($posted[$meta_key]) && is_string($posted[$meta_key])) {
            // update_user_meta() unslashes the value, so slash it first to keep backslashes intact.
            update_user_meta($user->ID, $meta_key, wp_slash(mb_substr(sanitize_text_field($posted[$meta_key]), 0, 255)));
        }
    }

    wpum_redirect_with_flash($return_url, 'userdata', 'success', 'data_saved');
}
add_action('template_redirect', 'wpum_process_user_data');

// Handle account deletion (requires the current password)
function wpum_process_account_deletion()
{
    if (!wpum_is_form_post('delete')) {
        return;
    }

    $user       = wpum_require_logged_in_user();
    $return_url = wpum_current_url();

    if (!wpum_verify_form_nonce('delete')) {
        wpum_redirect_with_flash($return_url, 'delete', 'error', 'invalid_nonce');
    }

    $check = wpum_verify_current_password($user->ID, wpum_post_raw('wpum_current_password'));
    if (is_wp_error($check)) {
        wpum_redirect_with_flash($return_url, 'delete', 'error', $check->get_error_code());
    }

    if (wpum_is_last_administrator($user->ID)) {
        wpum_redirect_with_flash($return_url, 'delete', 'error', 'last_admin');
    }
    if (is_multisite() && is_super_admin($user->ID)) {
        wpum_redirect_with_flash($return_url, 'delete', 'error', 'delete_failed');
    }

    require_once(ABSPATH . 'wp-admin/includes/user.php');

    // Content written by the user goes to an administrator instead of being destroyed.
    if (wp_delete_user($user->ID, wpum_get_reassign_user_id($user->ID))) {
        wp_clear_auth_cookie();
        wp_safe_redirect(home_url('/'));
        exit;
    }

    wpum_redirect_with_flash($return_url, 'delete', 'error', 'delete_failed');
}
add_action('template_redirect', 'wpum_process_account_deletion');

// Handle logout
function wpum_process_logout()
{
    $nonce = isset($_POST['wpum_logout_nonce']) && is_string($_POST['wpum_logout_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['wpum_logout_nonce']))
        : '';

    if (wp_verify_nonce($nonce, 'wpum_logout_nonce')) {
        wp_logout();
    }

    wp_safe_redirect(wpum_logout_redirect_url());
    exit;
}
add_action('admin_post_wpum_logout', 'wpum_process_logout');
add_action('admin_post_nopriv_wpum_logout', 'wpum_process_logout');
