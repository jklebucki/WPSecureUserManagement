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

    add_submenu_page(
        'wp_user_management',
        __('Configurable User List', 'wp-user-management-plugin'),
        __('Configurable User List', 'wp-user-management-plugin'),
        'manage_options',
        'wp_user_management',
        'wp_user_management_user_list'
    );

    add_submenu_page(
        'wp_user_management',
        __('Configuration', 'wp-user-management-plugin'),
        __('Configuration', 'wp-user-management-plugin'),
        'manage_options',
        'wp_user_management_configuration',
        'wp_user_management_configuration'
    );
}
add_action('admin_menu', 'wp_user_management_add_admin_menu');

// Display configurable user list
function wp_user_management_user_list() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Fetch users with filters
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $args = [
        'search' => '*' . esc_attr($search) . '*',
        'search_columns' => ['ID', 'user_login', 'user_email'],
        'orderby' => 'registered',
        'order' => 'DESC',
        'number' => -1,
    ];
    $users = get_users($args);

    // Get configurable columns
    $columns = get_option('wp_user_management_columns', ['ID', 'user_login', 'user_email']); // native fields

    ?>
    <div class="wrap">
        <h1><?php _e('Configurable User List', 'wp-user-management-plugin'); ?></h1>
        <form method="get">
            <input type="hidden" name="page" value="wp_user_management">
            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search...', 'wp-user-management-plugin'); ?>">
            <button type="submit"><?php _e('Search', 'wp-user-management-plugin'); ?></button>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th><?php echo esc_html(ucfirst($column)); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <td>
                                <?php 
                                if ($column === 'user_login') { 
                                    echo esc_html($user->user_login);
                                } elseif ($column === 'user_email') { 
                                    echo esc_html($user->user_email);
                                } else {
                                    echo esc_html($user->$column);
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Display configuration page
function wp_user_management_configuration() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('wp_user_management_configuration');

        $columns = array_map('sanitize_text_field', $_POST['columns']);
        update_option('wp_user_management_columns', $columns);

        $metadata = array_map('sanitize_text_field', $_POST['metadata']);
        update_option('wp_user_management_metadata', $metadata);

        echo '<div class="updated"><p>' . __('Settings saved.', 'wp-user-management-plugin') . '</p></div>';
    }

    $columns = get_option('wp_user_management_columns', ['ID', 'user_login', 'user_email']); // native fields
    $metadata = get_option('wp_user_management_metadata', []);

    // Get all user fields dynamically
    $user_fields = [
        'ID' => __('ID', 'wp-user-management-plugin'),
        'user_login' => __('Username', 'wp-user-management-plugin'),
        'user_email' => __('Email', 'wp-user-management-plugin'),
        'first_name' => __('First Name', 'wp-user-management-plugin'),
        'last_name' => __('Last Name', 'wp-user-management-plugin'),
        'user_registered' => __('Registered', 'wp-user-management-plugin'),
        // Add more fields dynamically if needed
    ];

    // Get all user metadata dynamically
    global $wpdb;
    $meta_keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM $wpdb->usermeta");
    $user_metadata = [];
    foreach ($meta_keys as $meta_key) {
        $user_metadata[$meta_key] = ucfirst(str_replace('_', ' ', $meta_key));
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Configuration', 'wp-user-management-plugin'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('wp_user_management_configuration'); ?>
            <h2><?php _e('User List Columns', 'wp-user-management-plugin'); ?></h2>
            <?php foreach ($user_fields as $field_key => $field_label): ?>
                <label>
                    <input type="checkbox" name="columns[]" value="<?php echo esc_attr($field_key); ?>" <?php checked(in_array($field_key, $columns)); ?>>
                    <?php echo esc_html($field_label); ?>
                </label>
            <?php endforeach; ?>

            <h2><?php _e('User Profile Metadata', 'wp-user-management-plugin'); ?></h2>
            <?php foreach ($user_metadata as $meta_key => $meta_label): ?>
                <label>
                    <input type="checkbox" name="metadata[]" value="<?php echo esc_attr($meta_key); ?>" <?php checked(in_array($meta_key, $metadata)); ?>>
                    <?php echo esc_html($meta_label); ?>
                </label>
            <?php endforeach; ?>

            <button type="submit"><?php _e('Save Settings', 'wp-user-management-plugin'); ?></button>
        </form>
    </div>
    <?php
}
?>