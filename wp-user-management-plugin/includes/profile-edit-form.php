<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display profile edit form
function sum_display_profile_edit_form() {
    $current_user = wp_get_current_user();
    $metadata = get_option('wp_user_management_metadata', []);
    ob_start(); ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'profile-edit-form.css'; ?>">
    <div class="sum-profile-edit-container">
        <div class="sum-tabs">
            <button type="button" class="active" data-tab="profile"><?php _e('Profile', 'secure-user-management'); ?></button>
            <button type="button" data-tab="user-data"><?php _e('User Data', 'secure-user-management'); ?></button>
            <button type="button" data-tab="change-password"><?php _e('Change Password', 'secure-user-management'); ?></button>
            <button type="button" data-tab="delete-account"><?php _e('Delete Account', 'secure-user-management'); ?></button>
            <button type="button" data-tab="logout"><?php _e('Logout', 'secure-user-management'); ?></button>
        </div>
        <div id="profile" class="sum-tab-content active">
            <form id="sum-profile-edit-form" method="post">
                <div class="sum-form-group">
                    <label for="sum-username"><?php _e('Username', 'secure-user-management'); ?> *</label>
                    <input type="text" name="sum_username" id="sum-username" value="<?php echo esc_attr($current_user->user_login); ?>" required>
                </div>

                <div class="sum-form-group">
                    <label for="sum-email"><?php _e('Email', 'secure-user-management'); ?> *</label>
                    <input type="email" name="sum_email" id="sum-email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                </div>

                <div class="sum-form-group">
                    <label for="sum-firstname"><?php _e('First Name', 'secure-user-management'); ?> *</label>
                    <input type="text" name="sum_firstname" id="sum-firstname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'first_name', true)); ?>" required>
                </div>

                <div class="sum-form-group">
                    <label for="sum-lastname"><?php _e('Last Name', 'secure-user-management'); ?> *</label>
                    <input type="text" name="sum_lastname" id="sum-lastname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'last_name', true)); ?>" required>
                </div>

                <input type="hidden" name="sum_profile_edit_nonce" value="<?php echo wp_create_nonce('sum_profile_edit_nonce'); ?>">
                <button type="submit"><?php _e('Update Profile', 'secure-user-management'); ?></button>
            </form>
        </div>
        <div id="user-data" class="sum-tab-content">
            <form id="sum-user-data-form" method="post">
                <?php foreach ($metadata as $meta_key): ?>
                    <div class="sum-form-group">
                        <label for="sumv-<?php echo esc_attr($meta_key); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $meta_key))); ?></label>
                        <input type="text" name="sumv_<?php echo esc_attr($meta_key); ?>" id="sumv-<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr(get_user_meta($current_user->ID, $meta_key, true)); ?>">
                    </div>
                <?php endforeach; ?>
                <input type="hidden" name="sum_user_data_nonce" value="<?php echo wp_create_nonce('sum_user_data_nonce'); ?>">
                <button type="submit"><?php _e('Save Data', 'secure-user-management'); ?></button>
            </form>
        </div>
        <div id="change-password" class="sum-tab-content">
            <form id="sum-change-password-form" method="post">
                <label for="sum-password"><?php _e('New Password', 'secure-user-management'); ?></label>
                <input type="password" name="sum_password" id="sum-password">

                <label for="sum-confirm-password"><?php _e('Confirm New Password', 'secure-user-management'); ?></label>
                <input type="password" name="sum_confirm_password" id="sum-confirm-password">

                <input type="hidden" name="sum_profile_edit_nonce" value="<?php echo wp_create_nonce('sum_profile_edit_nonce'); ?>">
                <button type="submit"><?php _e('Change Password', 'secure-user-management'); ?></button>
            </form>
        </div>
        <div id="delete-account" class="sum-tab-content">
            <h3><?php _e('Delete Account', 'secure-user-management'); ?></h3>
            <button type="button" id="sum-delete-account-button"><?php _e('Delete Account', 'secure-user-management'); ?></button>
        </div>
        <div id="logout" class="sum-tab-content">
            <h3><?php _e('Logout', 'secure-user-management'); ?></h3>
            <button type="button" id="sum-logout-button"><?php _e('Logout', 'secure-user-management'); ?></button>
            <?php $logout_nonce = wp_create_nonce('wp_rest'); ?>
            <input type="hidden" id="sum-logout-nonce" value="<?php echo esc_attr($logout_nonce); ?>">
        </div>
    </div>

    <!-- Modal -->
    <div id="sum-delete-account-modal" class="sum-modal hidden">
        <div class="sum-modal-content">
            <span class="sum-close">&times;</span>
            <p><?php _e('Are you sure you want to delete your account?', 'secure-user-management'); ?></p>
            <form id="sum-delete-account-form" method="post">
                <input type="hidden" name="sum_delete_account_nonce" value="<?php echo wp_create_nonce('sum_delete_account_nonce'); ?>">
                <button type="submit"><?php _e('Yes, Delete My Account', 'secure-user-management'); ?></button>
                <button type="button" class="sum-cancel"><?php _e('Cancel', 'secure-user-management'); ?></button>
            </form>
        </div>
    </div>

    <script src="<?php echo plugin_dir_url(__FILE__) . 'profile-edit-form.js'; ?>"></script>
    <?php
    return ob_get_clean();
}

// Handle profile update
function sum_process_profile_update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_profile_edit_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_profile_edit_nonce'], 'sum_profile_edit_nonce')) {
            wp_die(__('Security check failed!', 'secure-user-management'));
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
        if (!empty($password) && $password === $confirm_password) {
            wp_set_password($password, $current_user->ID);
        }

        // Redirect to profile edit page
        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_user_data_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_user_data_nonce'], 'sum_user_data_nonce')) {
            wp_die(__('Security check failed!', 'secure-user-management'));
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
add_action('init', 'sum_process_profile_update');

// Handle account deletion
function sum_process_account_deletion() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_delete_account_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_delete_account_nonce'], 'sum_delete_account_nonce')) {
            wp_die(__('Security check failed!', 'secure-user-management'));
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
function sum_process_logout() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_logout_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_logout_nonce'], 'sum_logout_nonce')) {
            wp_die(__('Security check failed!', 'secure-user-management'));
        }

        wp_logout();
        wp_redirect(home_url());
        exit;
    }
}
add_action('init', 'sum_process_logout');

// Register shortcode
function sum_register_profile_edit_shortcode() {
    add_shortcode('sum_user_profile_edit', 'sum_display_profile_edit_form');
}
add_action('init', 'sum_register_profile_edit_shortcode');
?>