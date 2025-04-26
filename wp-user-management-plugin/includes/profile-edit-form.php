<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue styles and scripts
function wpum_enqueue_profile_edit_styles()
{
    wp_enqueue_style('wpum-profile-edit-form', plugin_dir_url(__FILE__) . 'profile-edit-form.css');
    wp_enqueue_script('jquery');
    wp_enqueue_script('wpum-profile-edit-form', plugin_dir_url(__FILE__) . 'profile-edit-form.js', ['jquery'], null, true);
    wp_enqueue_script('wpum-password-strength', plugin_dir_url(__FILE__) . 'password-strength.js', ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'wpum_enqueue_profile_edit_styles');

// Meta keys for user data
function wpum_get_meta_label($meta_key)
{
    $meta_labels = array(
        'club_number' => __('Club Number', 'wp-user-management-plugin'),
    );
    return isset($meta_labels[$meta_key]) ? $meta_labels[$meta_key] : ucfirst(str_replace('_', ' ', $meta_key));
}

// Display profile edit form
function wpum_display_profile_edit_form()
{
    $current_user = wp_get_current_user();
    $metadata = get_option('wp_user_management_metadata', []);
    ob_start(); ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'profile-edit-form.css'; ?>">
    <div class="wpum-profile-edit-container">
        <div class="wpum-tabs">
            <button type="button" class="active" data-tab="profile"><?php _e('Profile', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="user-data"><?php _e('User Data', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="change-password"><?php _e('Change Password', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="shooting-credentials">
                <?php _e('Shooting Credentials', 'wp-user-management-plugin'); ?>
            </button>
            <button type="button" data-tab="delete-account"><?php _e('Delete Account', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="logout"><?php _e('Logout', 'wp-user-management-plugin'); ?></button>
        </div>
        <div id="profile" class="wpum-tab-content active">
            <form id="wpum-profile-edit-form" method="post">
                <div class="wpum-form-group">
                    <label for="wpum-username"><?php _e('Username', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="wpum_username" id="wpum-username" value="<?php echo esc_attr($current_user->user_login); ?>" required>
                </div>

                <div class="wpum-form-group">
                    <label for="wpum-email"><?php _e('Email', 'wp-user-management-plugin'); ?> *</label>
                    <input type="email" name="wpum_email" id="wpum-email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                </div>

                <div class="wpum-form-group">
                    <label for="wpum-firstname"><?php _e('First Name', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="wpum_firstname" id="wpum-firstname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'first_name', true)); ?>" required>
                </div>

                <div class="wpum-form-group">
                    <label for="wpum-lastname"><?php _e('Last Name', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="wpum_lastname" id="wpum-lastname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'last_name', true)); ?>" required>
                </div>

                <input type="hidden" name="wpum_profile_edit_nonce" value="<?php echo wp_create_nonce('wpum_profile_edit_nonce'); ?>">
                <button type="submit"><?php _e('Update Profile', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
        <div id="user-data" class="wpum-tab-content">
            <form id="wpum-user-data-form" method="post">
                <?php foreach ($metadata as $meta_key): ?>
                    <div class="wpum-form-group">
                        <label for="wpumv-<?php echo esc_attr($meta_key); ?>"><?php echo esc_html(wpum_get_meta_label($meta_key)); ?></label>
                        <input type="text" name="wpumv_<?php echo esc_attr($meta_key); ?>" id="wpumv-<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr(get_user_meta($current_user->ID, $meta_key, true)); ?>">
                    </div>
                <?php endforeach; ?>
                <input type="hidden" name="wpum_user_data_nonce" value="<?php echo wp_create_nonce('wpum_user_data_nonce'); ?>">
                <button type="submit"><?php _e('Save Data', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
        <div id="change-password" class="wpum-tab-content">
            <form id="wpum-change-password-form" method="post">
                <label for="wpum-password"><?php _e('New Password', 'wp-user-management-plugin'); ?></label>
                <input type="password" name="wpum_password" id="wpum-password">
                <div id="password-strength-meter"></div>

                <label for="wpum-confirm-password"><?php _e('Confirm New Password', 'wp-user-management-plugin'); ?></label>
                <input type="password" name="wpum_confirm_password" id="wpum-confirm-password">
                <div id="password-match-message"></div>

                <input type="hidden" name="wpum_profile_edit_nonce" value="<?php echo wp_create_nonce('wpum_profile_edit_nonce'); ?>">
                <button type="submit" id="wpum-change-password-button" disabled><?php _e('Change Password', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
        <div id="delete-account" class="wpum-tab-content">
            <h3><?php _e('Delete Account', 'wp-user-management-plugin'); ?></h3>
            <button type="button" id="wpum-delete-account-button"><?php _e('Delete Account', 'wp-user-management-plugin'); ?></button>
        </div>
        <div id="logout" class="wpum-tab-content">
            <h3><?php _e('Logout', 'wp-user-management-plugin'); ?></h3>
            <p><?php _e('Click the button below to logout from your account.', 'wp-user-management-plugin'); ?></p>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wpum_logout">
                <input type="hidden" name="wpum_logout_nonce" value="<?php echo wp_create_nonce('wpum_logout_nonce'); ?>">
                <button type="submit" class="button button-primary">
                    <?php _e('Logout', 'wp-user-management-plugin'); ?>
                </button>
            </form>
        </div>
        <div id="shooting-credentials" class="wpum-tab-content">
            <div id="wpum-messages" class="wpum-message" style="display: none;"></div>

            <form id="wpum-shooting-credentials-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('wpum_shooting_credentials', 'wpum_nonce'); ?>

                <?php
                $credentials = wpum_get_user_credentials(get_current_user_id());
                $credential_types = wpum_get_shooting_credential_types();

                foreach ($credential_types as $type => $label):
                    $current_credential = array_filter($credentials, function ($cred) use ($type) {
                        return $cred->credential_type === $type;
                    });
                    $current_credential = reset($current_credential);
                ?>
                    <div class="wpum-form-group">
                        <label for="wpum_<?php echo esc_attr($type); ?>_number">
                            <?php echo esc_html($label); ?>
                        </label>
                        <input type="text"
                            name="wpum_credentials[<?php echo esc_attr($type); ?>][number]"
                            id="wpum_<?php echo esc_attr($type); ?>_number"
                            value="<?php echo $current_credential ? esc_attr($current_credential->credential_number) : ''; ?>">

                        <label for="wpum_<?php echo esc_attr($type); ?>_file">
                            <?php _e('PDF Document', 'wp-user-management-plugin'); ?>
                        </label>
                        <input type="file"
                            name="wpum_credentials[<?php echo esc_attr($type); ?>][file]"
                            id="wpum_<?php echo esc_attr($type); ?>_file"
                            accept=".pdf">

                        <?php if ($current_credential && $current_credential->file_path): ?>
                            <div class="current-file">
                                <span><?php _e('Current file:', 'wp-user-management-plugin'); ?></span>
                                <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . $current_credential->file_path); ?>"
                                    target="_blank">
                                    <?php _e('View Document', 'wp-user-management-plugin'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="button button-primary">
                    <?php _e('Save Credentials', 'wp-user-management-plugin'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Modal dla usuwania konta -->
    <div id="wpum-delete-account-modal" class="wpum-modal hidden">
        <div class="wpum-modal-content">
            <span class="wpum-close">&times;</span>
            <p><?php _e('Are you sure you want to delete your account?', 'wp-user-management-plugin'); ?></p>
            <form id="wpum-delete-account-form" method="post">
                <input type="hidden" name="wpum_delete_account_nonce" value="<?php echo wp_create_nonce('wpum_delete_account_nonce'); ?>">
                <button type="submit"><?php _e('Yes, Delete My Account', 'wp-user-management-plugin'); ?></button>
                <button type="button" class="wpum-cancel"><?php _e('Cancel', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
    </div>

    <script src="<?php echo plugin_dir_url(__FILE__) . 'profile-edit-form.js'; ?>"></script>
    <script>
    // Przenosimy modal do końca body po załadowaniu strony
    jQuery(document).ready(function($) {
        $('#wpum-delete-account-modal').appendTo('body');
    });
    </script>
<?php
    return ob_get_clean();
}

// Handle profile update
function wpum_process_profile_update()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_profile_edit_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_profile_edit_nonce'], 'wpum_profile_edit_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $current_user = wp_get_current_user();
        $username = sanitize_user($_POST['wpum_username']);
        $email = sanitize_email($_POST['wpum_email']);
        $firstname = sanitize_text_field($_POST['wpum_firstname']);
        $lastname = sanitize_text_field($_POST['wpum_lastname']);
        $password = isset($_POST['wpum_password']) ? $_POST['wpum_password'] : '';
        $confirm_password = isset($_POST['wpum_confirm_password']) ? $_POST['wpum_confirm_password'] : '';

        // Check if username is already taken by another user
        if ($username !== $current_user->user_login && username_exists($username)) {
            wp_die(__('Username is already taken.', 'wp-user-management-plugin'));
        }

        // Check if email is already taken by another user
        if ($email !== $current_user->user_email && email_exists($email)) {
            wp_die(__('Email is already taken.', 'wp-user-management-plugin'));
        }

        // Update user data
        $update_data = array(
            'ID' => $current_user->ID,
            'user_login' => $username,
            'user_email' => $email,
            'first_name' => $firstname,
            'last_name' => $lastname
        );

        $result = wp_update_user($update_data);
        if (is_wp_error($result)) {
            wp_die(__('Error updating profile. Please try again.', 'wp-user-management-plugin'));
        }

        // Update password if provided
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                wp_die(__('Passwords do not match.', 'wp-user-management-plugin'));
            }

            if (!wpum_validate_password_strength($password)) {
                wp_die(__('Password does not meet the strength requirements.', 'wp-user-management-plugin'));
            }

            wp_set_password($password, $current_user->ID);
        }

        // Redirect to profile page with success message
        wp_redirect(add_query_arg('profile_updated', 'true', home_url('/user-profile')));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_user_data_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_user_data_nonce'], 'wpum_user_data_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $current_user = wp_get_current_user();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'wpumv_') === 0) {
                $meta_key = substr($key, 6);
                update_user_meta($current_user->ID, $meta_key, sanitize_text_field($value));
            }
        }

        // Redirect to profile page with success message
        wp_redirect(add_query_arg('data_saved', 'true', home_url('/user-profile')));
        exit;
    }
}
add_action('init', 'wpum_process_profile_update'); // Ensure this action is added

// Handle account deletion
function wpum_process_account_deletion()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_delete_account_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_delete_account_nonce'], 'wpum_delete_account_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $current_user = wp_get_current_user();
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        if (wp_delete_user($current_user->ID)) {
            // Redirect to home page
            wp_redirect(home_url('/'));
            exit;
        } else {
            wp_die(__('Error deleting account. Please try again.', 'wp-user-management-plugin'));
        }
    }
}
add_action('init', 'wpum_process_account_deletion');

// Handle logout
function wpum_process_logout()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_logout_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_logout_nonce'], 'wpum_logout_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        wp_logout();
        wp_redirect(home_url('/'));
        exit;
    }
}
add_action('admin_post_wpum_logout', 'wpum_process_logout');
add_action('admin_post_nopriv_wpum_logout', 'wpum_process_logout');

// Add this function to handle the form submission
function wpum_process_shooting_credentials()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_shooting_credentials_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_shooting_credentials_nonce'], 'wpum_shooting_credentials_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $user_id = get_current_user_id();
        $credential_types = wpum_get_shooting_credential_types();

        foreach ($credential_types as $type => $label) {
            $number_field = $type . '_number';
            $file_field = $type . '_file';

            if (!empty($_POST[$number_field])) {
                $credential_number = sanitize_text_field($_POST[$number_field]);

                // Handle file upload if new file is provided
                if (!empty($_FILES[$file_field]['name'])) {
                    $upload_result = wpum_handle_credential_file_upload($_FILES[$file_field], $user_id, $type);

                    if (is_wp_error($upload_result)) {
                        wp_die($upload_result->get_error_message());
                    }

                    // Save credential with new file
                    wpum_save_shooting_credential($user_id, $type, $credential_number, $upload_result);
                } else {
                    // Update just the credential number if no new file
                    $existing_credentials = wpum_get_user_credentials($user_id);
                    $current_credential = array_filter($existing_credentials, function ($cred) use ($type) {
                        return $cred->credential_type === $type;
                    });
                    $current_credential = reset($current_credential);

                    if ($current_credential) {
                        wpum_save_shooting_credential($user_id, $type, $credential_number, $current_credential->file_path);
                    }
                }
            }
        }

        // Redirect back to the profile page
        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
}
add_action('init', 'wpum_process_shooting_credentials');

function wpum_process_profile_edit()
{
    // ... existing code ...
}
add_action('init', 'wpum_process_profile_edit');

?>