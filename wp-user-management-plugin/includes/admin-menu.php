<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
function wp_user_management_add_admin_menu() {
    add_menu_page(
        __('User Management', 'wp-user-management-plugin'),
        __('User Management', 'wp-user-management-plugin'),
        'manage_options',
        'wp_user_management',
        'wp_user_management_user_list',
        'dashicons-admin-users',
        6
    );
}
add_action('admin_menu', 'wp_user_management_add_admin_menu');

// Display user list
function wp_user_management_user_list() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Fetch users
    $args = [
        'role' => 'subscriber',
        'orderby' => 'registered',
        'order' => 'DESC',
        'number' => -1,
    ];
    $users = get_users($args);

    // Display user list
    ?>
    <div class="wrap">
        <h1><?php _e('User List', 'wp-user-management-plugin'); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Username', 'wp-user-management-plugin'); ?></th>
                    <th><?php _e('Email', 'wp-user-management-plugin'); ?></th>
                    <th><?php _e('First Name', 'wp-user-management-plugin'); ?></th>
                    <th><?php _e('Last Name', 'wp-user-management-plugin'); ?></th>
                    <th><?php _e('Registered', 'wp-user-management-plugin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo esc_html($user->user_login); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo esc_html(get_user_meta($user->ID, 'first_name', true)); ?></td>
                        <td><?php echo esc_html(get_user_meta($user->ID, 'last_name', true)); ?></td>
                        <td><?php echo esc_html(date('Y-m-d', strtotime($user->user_registered))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>