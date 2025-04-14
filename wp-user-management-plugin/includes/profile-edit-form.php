<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue styles and scripts
function sum_enqueue_profile_edit_styles()
{
    wp_enqueue_style('sum-profile-edit-form', plugin_dir_url(__FILE__) . 'profile-edit-form.css');
    wp_enqueue_script('sum-password-strength', plugin_dir_url(__FILE__) . 'password-strength.js', ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'sum_enqueue_profile_edit_styles');

// Meta keys for user data
function wpum_get_meta_label($meta_key)
{
    $meta_labels = array(
        'club_number' => __('Club Number', 'wp-user-management-plugin'),
    );
    return isset($meta_labels[$meta_key]) ? $meta_labels[$meta_key] : ucfirst(str_replace('_', ' ', $meta_key));
}

// Display profile edit form
function sum_display_profile_edit_form()
{
    $current_user = wp_get_current_user();
    $metadata = get_option('wp_user_management_metadata', []);
    ob_start(); ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'profile-edit-form.css'; ?>">
    <div class="sum-profile-edit-container">
        <div class="sum-tabs">
            <button type="button" class="active" data-tab="profile"><?php _e('Profile', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="user-data"><?php _e('User Data', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="change-password"><?php _e('Change Password', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="delete-account"><?php _e('Delete Account', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="logout"><?php _e('Logout', 'wp-user-management-plugin'); ?></button>
            <button type="button" data-tab="shooting-credentials">
                <?php _e('Shooting Credentials', 'wp-user-management-plugin'); ?>
            </button>
        </div>
        <div id="profile" class="sum-tab-content active">
            <form id="sum-profile-edit-form" method="post">
                <div class="sum-form-group">
                    <label for="sum-username"><?php _e('Username', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="sum_username" id="sum-username" value="<?php echo esc_attr($current_user->user_login); ?>" required>
                </div>

                <div class="sum-form-group">
                    <label for="sum-email"><?php _e('Email', 'wp-user-management-plugin'); ?> *</label>
                    <input type="email" name="sum_email" id="sum-email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                </div>

                <div class="sum-form-group">
                    <label for="sum-firstname"><?php _e('First Name', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="sum_firstname" id="sum-firstname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'first_name', true)); ?>" required>
                </div>

                <div class="sum-form-group">
                    <label for="sum-lastname"><?php _e('Last Name', 'wp-user-management-plugin'); ?> *</label>
                    <input type="text" name="sum_lastname" id="sum-lastname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'last_name', true)); ?>" required>
                </div>

                <input type="hidden" name="sum_profile_edit_nonce" value="<?php echo wp_create_nonce('sum_profile_edit_nonce'); ?>">
                <button type="submit"><?php _e('Update Profile', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
        <div id="user-data" class="sum-tab-content">
            <form id="sum-user-data-form" method="post">
                <?php foreach ($metadata as $meta_key): ?>
                    <div class="sum-form-group">
                        <label for="sumv-<?php echo esc_attr($meta_key); ?>"><?php echo esc_html(wpum_get_meta_label($meta_key)); ?></label>
                        <input type="text" name="sumv_<?php echo esc_attr($meta_key); ?>" id="sumv-<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr(get_user_meta($current_user->ID, $meta_key, true)); ?>">
                    </div>
                <?php endforeach; ?>
                <input type="hidden" name="sum_user_data_nonce" value="<?php echo wp_create_nonce('sum_user_data_nonce'); ?>">
                <button type="submit"><?php _e('Save Data', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
        <div id="change-password" class="sum-tab-content">
            <form id="sum-change-password-form" method="post">
                <label for="sum-password"><?php _e('New Password', 'wp-user-management-plugin'); ?></label>
                <input type="password" name="sum_password" id="sum-password">
                <div id="password-strength-meter"></div>

                <label for="sum-confirm-password"><?php _e('Confirm New Password', 'wp-user-management-plugin'); ?></label>
                <input type="password" name="sum_confirm_password" id="sum-confirm-password">
                <div id="password-match-message"></div>

                <input type="hidden" name="sum_profile_edit_nonce" value="<?php echo wp_create_nonce('sum_profile_edit_nonce'); ?>">
                <button type="submit" id="sum-change-password-button" disabled><?php _e('Change Password', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
        <div id="delete-account" class="sum-tab-content">
            <h3><?php _e('Delete Account', 'wp-user-management-plugin'); ?></h3>
            <button type="button" id="sum-delete-account-button"><?php _e('Delete Account', 'wp-user-management-plugin'); ?></button>
        </div>
        <div id="logout" class="sum-tab-content">
            <h3><?php _e('Logout', 'wp-user-management-plugin'); ?></h3>
            <button type="button" id="sum-logout-button"><?php _e('Logout', 'wp-user-management-plugin'); ?></button>
            <?php $logout_nonce = wp_create_nonce('sum_logout_nonce'); ?>
            <input type="hidden" id="sum-logout-nonce" value="<?php echo esc_attr($logout_nonce); ?>">
        </div>
        <div id="shooting-credentials" class="sum-tab-content">
            <form id="sum-shooting-credentials-form" method="post" enctype="multipart/form-data">
                <?php
                $credentials = wpum_get_user_credentials(get_current_user_id());
                $credential_types = wpum_get_shooting_credential_types();
                foreach ($credential_types as $type => $label):
                    $current_credential = array_filter($credentials, function($cred) use ($type) {
                        return $cred->credential_type === $type;
                    });
                    $current_credential = reset($current_credential);
                ?>
                    <div class="sum-form-group">
                        <label for="<?php echo esc_attr($type); ?>_number">
                            <?php echo esc_html($label); ?>
                        </label>
                        <input type="text" 
                               name="<?php echo esc_attr($type); ?>_number" 
                               id="<?php echo esc_attr($type); ?>_number"
                               value="<?php echo $current_credential ? esc_attr($current_credential->credential_number) : ''; ?>">
                        
                        <label for="<?php echo esc_attr($type); ?>_file">
                            <?php _e('PDF Document', 'wp-user-management-plugin'); ?>
                        </label>
                        <input type="file" 
                               name="<?php echo esc_attr($type); ?>_file" 
                               id="<?php echo esc_attr($type); ?>_file"
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
                
                <input type="hidden" name="sum_shooting_credentials_nonce" 
                       value="<?php echo wp_create_nonce('sum_shooting_credentials_nonce'); ?>">
                <button type="submit">
                    <?php _e('Save Credentials', 'wp-user-management-plugin'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Modal -->
    <div id="sum-delete-account-modal" class="sum-modal hidden">
        <div class="sum-modal-content">
            <span class="sum-close">&times;</span>
            <p><?php _e('Are you sure you want to delete your account?', 'wp-user-management-plugin'); ?></p>
            <form id="sum-delete-account-form" method="post">
                <input type="hidden" name="sum_delete_account_nonce" value="<?php echo wp_create_nonce('sum_delete_account_nonce'); ?>">
                <button type="submit"><?php _e('Yes, Delete My Account', 'wp-user-management-plugin'); ?></button>
                <button type="button" class="sum-cancel"><?php _e('Cancel', 'wp-user-management-plugin'); ?></button>
            </form>
        </div>
    </div>

    <script src="<?php echo plugin_dir_url(__FILE__) . 'profile-edit-form.js'; ?>"></script>
<?php
    return ob_get_clean();
}

// Handle profile update
function sum_process_profile_update()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_profile_edit_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_profile_edit_nonce'], 'sum_profile_edit_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $current_user = wp_get_current_user();
        $username = sanitize_user($_POST['sum_username']);
        $email = sanitize_email($_POST['sum_email']);
        $firstname = sanitize_text_field($_POST['sum_firstname']);
        $lastname = sanitize_text_field($_POST['sum_lastname']);
        $password = $_POST['sum_password'];
        $confirm_password = $_POST['sum_confirm_password'];

        // Update user data
        wp_update_user([
            'ID' => $current_user->ID,
            'user_login' => $username,
            'user_email' => $email,
            'first_name' => $firstname,
            'last_name' => $lastname,
        ]);

        // Update password if provided
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                wp_die(__('Passwords do not match.', 'wp-user-management-plugin'));
            }

            if (!sum_validate_password_strength($password)) {
                wp_die(__('Password does not meet the strength requirements.', 'wp-user-management-plugin'));
            }

            wp_set_password($password, $current_user->ID);
        }

        // Redirect to profile edit page
        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_user_data_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_user_data_nonce'], 'sum_user_data_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $current_user = wp_get_current_user();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'sumv_') === 0) {
                $meta_key = substr($key, 5);
                update_user_meta($current_user->ID, $meta_key, sanitize_text_field($value));
            }
        }

        // Redirect to profile edit page
        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
}
add_action('init', 'sum_process_profile_update'); // Ensure this action is added

// Handle account deletion
function sum_process_account_deletion()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_delete_account_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_delete_account_nonce'], 'sum_delete_account_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $current_user = wp_get_current_user();
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($current_user->ID);

        // Redirect to home page after deletion
        wp_redirect(home_url());
        exit;
    }
}
add_action('init', 'sum_process_account_deletion');

// Handle logout
function sum_process_logout()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_logout_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_logout_nonce'], 'sum_logout_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        wp_logout();
        wp_redirect(home_url());
        exit;
    }
}
add_action('admin_post_sum_logout', 'sum_process_logout');
add_action('admin_post_nopriv_sum_logout', 'sum_process_logout');

// Register shortcode
function sum_register_profile_edit_shortcode()
{
    add_shortcode('sum_user_profile_edit', 'sum_display_profile_edit_form');
}
add_action('init', 'sum_register_profile_edit_shortcode');

// Add this function to handle the form submission
function sum_process_shooting_credentials() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_shooting_credentials_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_shooting_credentials_nonce'], 'sum_shooting_credentials_nonce')) {
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
                    $current_credential = array_filter($existing_credentials, function($cred) use ($type) {
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
add_action('init', 'sum_process_shooting_credentials');

?>