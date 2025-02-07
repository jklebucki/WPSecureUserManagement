<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display profile edit form
function sum_display_profile_edit_form() {
    $current_user = wp_get_current_user();
    ob_start(); ?>
    <div class="sum-profile-edit-container">
        <h2><?php _e('Edit Profile', 'secure-user-management'); ?></h2>
        <form id="sum-profile-edit-form" method="post">
            <label for="sum-username"><?php _e('Username', 'secure-user-management'); ?> *</label>
            <input type="text" name="sum_username" id="sum-username" value="<?php echo esc_attr($current_user->user_login); ?>" required>

            <label for="sum-email"><?php _e('Email', 'secure-user-management'); ?> *</label>
            <input type="email" name="sum_email" id="sum-email" value="<?php echo esc_attr($current_user->user_email); ?>" required>

            <label for="sum-firstname"><?php _e('First Name', 'secure-user-management'); ?> *</label>
            <input type="text" name="sum_firstname" id="sum-firstname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'first_name', true)); ?>" required>

            <label for="sum-lastname"><?php _e('Last Name', 'secure-user-management'); ?> *</label>
            <input type="text" name="sum_lastname" id="sum-lastname" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'last_name', true)); ?>" required>

            <label for="sum-password"><?php _e('New Password', 'secure-user-management'); ?></label>
            <input type="password" name="sum_password" id="sum-password">

            <label for="sum-confirm-password"><?php _e('Confirm New Password', 'secure-user-management'); ?></label>
            <input type="password" name="sum_confirm_password" id="sum-confirm-password">

            <input type="hidden" name="sum_profile_edit_nonce" value="<?php echo wp_create_nonce('sum_profile_edit_nonce'); ?>">
            <button type="submit"><?php _e('Update Profile', 'secure-user-management'); ?></button>
        </form>

        <h3><?php _e('Delete Account', 'secure-user-management'); ?></h3>
        <form id="sum-delete-account-form" method="post">
            <input type="hidden" name="sum_delete_account_nonce" value="<?php echo wp_create_nonce('sum_delete_account_nonce'); ?>">
            <button type="submit" onclick="return confirm('<?php _e('Are you sure you want to delete your account?', 'secure-user-management'); ?>');"><?php _e('Delete Account', 'secure-user-management'); ?></button>
        </form>
    </div>
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

// Register shortcode
function sum_register_profile_edit_shortcode() {
    add_shortcode('sum_user_profile_edit', 'sum_display_profile_edit_form');
}
add_action('init', 'sum_register_profile_edit_shortcode');
?>