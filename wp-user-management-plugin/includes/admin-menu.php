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
        __('User List', 'wp-user-management-plugin'),
        __('User List', 'wp-user-management-plugin'),
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
    $columns[] = 'roles'; // Add roles column
    $columns[] = 'shooting_patent'; // Add shooting patent column
    $columns[] = 'instructor_license'; // Add instructor license column
    $columns[] = 'actions'; // Add actions column

    // Get all roles
    $roles = get_editable_roles();

    ?>
    <div class="wrap">
        <h1><?php _e('User List', 'wp-user-management-plugin'); ?></h1>
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
                                } elseif ($column === 'roles') {
                                    foreach ($user->roles as $role) {
                                        ?>
                                        <span class="role-badge">
                                            <?php echo esc_html($roles[$role]['name']); ?>
                                            <a href="<?php echo esc_url(add_query_arg(['action' => 'remove_role', 'user_id' => $user->ID, 'role' => $role])); ?>" class="styled-button remove-role">x</a>
                                        </span>
                                        <?php
                                    }
                                } elseif ($column === 'shooting_patent') {
                                    $credentials = wpum_get_user_credentials($user->ID);
                                    $license_number = '';
                                    $file_path = '';
                                    
                                    foreach ($credentials as $credential) {
                                        if ($credential->credential_type === 'shooting_patent') {
                                            $license_number = esc_html($credential->credential_number);
                                            $file_path = $credential->file_path;
                                            break;
                                        }
                                    }
                                    
                                    echo $license_number;
                                    if (!empty($file_path) && strpos($file_path, 'empty') === false) {
                                        $file_url = wp_upload_dir()['baseurl'] . $file_path;
                                        echo ' <a href="' . esc_url($file_url) . '" target="_blank"><span class="dashicons dashicons-media-document"></span></a>';
                                    }
                                } elseif ($column === 'instructor_license') {
                                    $credentials = wpum_get_user_credentials($user->ID);
                                    $instructor_number = '';
                                    $file_path = '';
                                    
                                    foreach ($credentials as $credential) {
                                        if ($credential->credential_type === 'instructor_license') {
                                            $instructor_number = esc_html($credential->credential_number);
                                            $file_path = $credential->file_path;
                                            break;
                                        }
                                    }
                                    
                                    echo $instructor_number;
                                    if (!empty($file_path) && strpos($file_path, 'empty') === false) {
                                        $file_url = wp_upload_dir()['baseurl'] . $file_path;
                                        echo ' <a href="' . esc_url($file_url) . '" target="_blank"><span class="dashicons dashicons-media-document"></span></a>';
                                    }
                                } elseif ($column === 'actions') {
                                    ?>
                                    <form method="post" class="inline-form">
                                        <?php wp_nonce_field('wp_user_management_add_role'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                        <select name="role" class="styled-dropdown">
                                            <?php foreach ($roles as $role_key => $role): ?>
                                                <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="styled-button"><?php _e('Add role', 'wp-user-management-plugin'); ?></button>
                                    </form>
                                    <?php
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

// Handle adding roles
function wp_user_management_handle_add_role() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['role'])) {
        check_admin_referer('wp_user_management_add_role');

        $user_id = intval($_POST['user_id']);
        $role = sanitize_text_field($_POST['role']);

        $user = get_userdata($user_id);
        if ($user && in_array($role, array_keys(get_editable_roles()))) {
            $user->add_role($role);
        }
    }
}
add_action('admin_init', 'wp_user_management_handle_add_role');

// Handle removing roles
function wp_user_management_handle_remove_role() {
    if (isset($_GET['action']) && $_GET['action'] === 'remove_role' && isset($_GET['user_id'], $_GET['role'])) {
        $user_id = intval($_GET['user_id']);
        $role = sanitize_text_field($_GET['role']);

        $user = get_userdata($user_id);
        if ($user && in_array($role, $user->roles)) {
            $user->remove_role($role);
        }
    }
}
add_action('admin_init', 'wp_user_management_handle_remove_role');

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

        $password_length = max(8, intval($_POST['password_length']));
        update_option('wp_user_management_password_length', $password_length);

        echo '<div class="updated"><p>' . __('Settings saved.', 'wp-user-management-plugin') . '</p></div>';
    }

    $columns = get_option('wp_user_management_columns', ['ID', 'user_login', 'user_email']); // native fields
    $metadata = get_option('wp_user_management_metadata', []);
    $password_length = get_option('wp_user_management_password_length', 8);

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
            <div class="scrollable-container">
                <?php foreach ($user_fields as $field_key => $field_label): ?>
                    <label>
                        <input type="checkbox" name="columns[]" value="<?php echo esc_attr($field_key); ?>" <?php checked(in_array($field_key, $columns)); ?>>
                        <?php echo esc_html($field_label); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <h2><?php _e('User Profile Metadata', 'wp-user-management-plugin'); ?></h2>
            <div class="scrollable-container">
                <?php foreach ($user_metadata as $meta_key => $meta_label): ?>
                    <label>
                        <input type="checkbox" name="metadata[]" value="<?php echo esc_attr($meta_key); ?>" <?php checked(in_array($meta_key, $metadata)); ?>>
                        <?php echo esc_html($meta_label); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <h2><?php _e('Password Settings', 'wp-user-management-plugin'); ?></h2>
            <label for="password_length"><?php _e('Minimum Password Length', 'wp-user-management-plugin'); ?></label>
            <input type="number" name="password_length" id="password_length" value="<?php echo esc_attr($password_length); ?>" min="8">

            <button type="submit" class="button button-primary"><?php _e('Save Settings', 'wp-user-management-plugin'); ?></button>
        </form>
    </div>
    <?php
}

// Enqueue the styles
function wp_user_management_enqueue_styles() {
    wp_enqueue_style('wp-user-management-admin', plugin_dir_url(__FILE__) . 'admin-menu.css');
}
add_action('admin_enqueue_scripts', 'wp_user_management_enqueue_styles');
?>