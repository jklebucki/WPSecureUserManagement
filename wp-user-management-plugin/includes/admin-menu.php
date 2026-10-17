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

// Enqueue assets only on this plugin's pages
function wp_user_management_enqueue_admin_assets() {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (0 !== strpos($page, 'wp_user_management')) {
        return;
    }

    wp_enqueue_style('wpum-admin', WPUM_PLUGIN_URL . 'assets/css/wpum-admin.css', array(), WPUM_VERSION);

    if ('wp_user_management' === $page) {
        wp_enqueue_script('wpum-admin-users', WPUM_PLUGIN_URL . 'assets/js/admin-users.js', array('jquery'), WPUM_VERSION, true);
        wp_localize_script('wpum-admin-users', 'wpumUsers', array(
            'i18n' => array(
                'none'         => __('Select at least one user.', 'wp-user-management-plugin'),
                'selected'     => __('%d selected', 'wp-user-management-plugin'),
                'summaryOne'   => __('You are about to permanently delete 1 user.', 'wp-user-management-plugin'),
                'summaryMany'  => __('You are about to permanently delete %d users.', 'wp-user-management-plugin'),
                'more'         => __('...and %d more', 'wp-user-management-plugin'),
            ),
        ));
    }
}
add_action('admin_enqueue_scripts', 'wp_user_management_enqueue_admin_assets');

// Native user fields that can be shown as columns in the user list.
function wpum_admin_user_fields() {
    return array(
        'ID'              => __('ID', 'wp-user-management-plugin'),
        'user_login'      => __('Username', 'wp-user-management-plugin'),
        'user_email'      => __('Email', 'wp-user-management-plugin'),
        'first_name'      => __('First Name', 'wp-user-management-plugin'),
        'last_name'       => __('Last Name', 'wp-user-management-plugin'),
        'user_registered' => __('Registered', 'wp-user-management-plugin'),
    );
}

// Configured columns, restricted to the known fields.
function wpum_admin_get_columns() {
    $saved = get_option('wp_user_management_columns', array('ID', 'user_login', 'user_email'));
    $saved = is_array($saved) ? $saved : array();

    return array_values(array_intersect($saved, array_keys(wpum_admin_user_fields())));
}

function wpum_admin_list_url($args = array()) {
    return add_query_arg($args, admin_url('admin.php?page=wp_user_management'));
}

// Result messages shown after role/bulk actions (codes only travel in the URL).
function wpum_admin_list_notices() {
    $messages = array(
        'confirm_required' => array('error', __('Deleting users needs to be confirmed in the dialog. Please try again.', 'wp-user-management-plugin')),
        'no_selection'     => array('error', __('No users were selected.', 'wp-user-management-plugin')),
        'reassign_invalid' => array('error', __('Choose a valid user to receive the content of the deleted users.', 'wp-user-management-plugin')),
        'role_added'       => array('success', __('Role added.', 'wp-user-management-plugin')),
        'role_removed'     => array('success', __('Role removed.', 'wp-user-management-plugin')),
        'role_invalid'     => array('error', __('This role cannot be assigned.', 'wp-user-management-plugin')),
        'role_blocked'     => array('error', __('You cannot remove the administrator role from yourself or from the last administrator.', 'wp-user-management-plugin')),
    );

    if (isset($_GET['wpum_deleted']) || isset($_GET['wpum_skipped'])) {
        $deleted = isset($_GET['wpum_deleted']) ? absint($_GET['wpum_deleted']) : 0;
        $skipped = isset($_GET['wpum_skipped']) ? absint($_GET['wpum_skipped']) : 0;

        if ($deleted > 0) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: %d: number of users */
                    _n('%d user deleted.', '%d users deleted.', $deleted, 'wp-user-management-plugin'),
                    $deleted
                ))
            );
        }
        if ($skipped > 0) {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: %d: number of users */
                    _n('%d user was skipped (your own account, the last administrator, or no permission).', '%d users were skipped (your own account, the last administrator, or no permission).', $skipped, 'wp-user-management-plugin'),
                    $skipped
                ))
            );
        }
    }

    $code = isset($_GET['wpum_msg']) ? sanitize_key(wp_unslash($_GET['wpum_msg'])) : '';
    if ('' !== $code && isset($messages[$code])) {
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($messages[$code][0]),
            esc_html($messages[$code][1])
        );
    }
}

// Display the user list (paginated, with bulk delete)
function wp_user_management_user_list() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    $search   = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
    $paged    = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $per_page = max(5, min(200, (int) apply_filters('wpum_users_per_page', 20)));

    $args = array(
        'orderby'     => 'registered',
        'order'       => 'DESC',
        'number'      => $per_page,
        'paged'       => $paged,
        'count_total' => true,
    );
    if ('' !== $search) {
        $args['search']         = '*' . $search . '*';
        $args['search_columns'] = array('ID', 'user_login', 'user_email');
    }

    $query       = new WP_User_Query($args);
    $users       = $query->get_results();
    $total       = (int) $query->get_total();
    $total_pages = max(1, (int) ceil($total / $per_page));

    $user_fields = wpum_admin_user_fields();
    $columns     = wpum_admin_get_columns();
    $roles       = get_editable_roles();
    $credentials = wpum_get_credentials_for_users(wp_list_pluck($users, 'ID'));
    $credential_types = wpum_get_shooting_credential_types();
    $current_id  = get_current_user_id();

    $credential_columns = array(
        'shooting_patent'    => __('Shooting Patent', 'wp-user-management-plugin'),
        'instructor_license' => __('Instructor License', 'wp-user-management-plugin'),
    );

    $pagination = paginate_links(array(
        'base'      => add_query_arg('paged', '%#%', wpum_admin_list_url()),
        'format'    => '',
        'current'   => $paged,
        'total'     => $total_pages,
        'add_args'  => '' !== $search ? array('search' => $search) : false,
        'prev_text' => '&lsaquo;',
        'next_text' => '&rsaquo;',
    ));
    ?>
    <div class="wrap wpum-admin">
        <h1>
            <?php esc_html_e('User List', 'wp-user-management-plugin'); ?>
            <span class="wpum-count"><?php echo esc_html(number_format_i18n($total)); ?></span>
        </h1>

        <?php wpum_admin_list_notices(); ?>

        <div class="wpum-card wpum-card--flush">
            <div class="wpum-toolbar">
                <form id="wpum-bulk-form" class="wpum-bulk" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpum_bulk_delete_users">
                    <input type="hidden" name="wpum_confirm" id="wpum-bulk-confirm" value="">
                    <input type="hidden" name="search" value="<?php echo esc_attr($search); ?>">
                    <input type="hidden" name="paged" value="<?php echo esc_attr($paged); ?>">
                    <?php wp_nonce_field('wpum_bulk_delete_users', '_wpnonce', false); ?>

                    <label class="screen-reader-text" for="wpum-bulk-action"><?php esc_html_e('Bulk actions', 'wp-user-management-plugin'); ?></label>
                    <select id="wpum-bulk-action">
                        <option value=""><?php esc_html_e('Bulk actions', 'wp-user-management-plugin'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'wp-user-management-plugin'); ?></option>
                    </select>
                    <button type="button" class="button" id="wpum-bulk-apply"><?php esc_html_e('Apply', 'wp-user-management-plugin'); ?></button>
                    <span class="wpum-selected" id="wpum-selected-count" aria-live="polite"></span>

                    <div id="wpum-bulk-modal" class="wpum-modal" hidden role="dialog" aria-modal="true" aria-labelledby="wpum-bulk-title">
                        <div class="wpum-modal__dialog">
                            <h2 id="wpum-bulk-title"><?php esc_html_e('Delete selected users', 'wp-user-management-plugin'); ?></h2>
                            <p id="wpum-bulk-summary"></p>
                            <ul id="wpum-bulk-list" class="wpum-bulk-list"></ul>

                            <fieldset class="wpum-content-choice">
                                <legend><?php esc_html_e('What should happen to the content of these users?', 'wp-user-management-plugin'); ?></legend>
                                <label><input type="radio" name="wpum_content" value="delete" checked> <?php esc_html_e('Delete all content', 'wp-user-management-plugin'); ?></label>
                                <label>
                                    <input type="radio" name="wpum_content" value="reassign"> <?php esc_html_e('Attribute all content to:', 'wp-user-management-plugin'); ?>
                                </label>
                                <?php
                                wp_dropdown_users(array(
                                    'name'       => 'wpum_reassign_to',
                                    'id'         => 'wpum-reassign-to',
                                    'selected'   => $current_id,
                                    'show'       => 'display_name_with_login',
                                    'capability' => array('edit_posts'),
                                ));
                                ?>
                            </fieldset>

                            <div class="wpum-modal__actions">
                                <button type="button" class="button" id="wpum-bulk-cancel"><?php esc_html_e('Cancel', 'wp-user-management-plugin'); ?></button>
                                <button type="submit" class="button wpum-button-danger" id="wpum-bulk-submit"><?php esc_html_e('Delete users', 'wp-user-management-plugin'); ?></button>
                            </div>
                        </div>
                    </div>
                </form>

                <form method="get" class="wpum-search">
                    <input type="hidden" name="page" value="wp_user_management">
                    <label class="screen-reader-text" for="wpum-search-input"><?php esc_html_e('Search', 'wp-user-management-plugin'); ?></label>
                    <input type="search" id="wpum-search-input" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search...', 'wp-user-management-plugin'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Search', 'wp-user-management-plugin'); ?></button>
                </form>

                <?php if ($pagination) : ?>
                    <nav class="wpum-pagination" aria-label="<?php esc_attr_e('Pagination', 'wp-user-management-plugin'); ?>"><?php echo $pagination; // Generated by paginate_links(), already escaped. ?></nav>
                <?php endif; ?>
            </div>

            <div class="wpum-table-wrap">
                <table class="wp-list-table widefat striped wpum-table">
                    <thead>
                        <tr>
                            <td class="check-column"><input type="checkbox" id="wpum-select-all" aria-label="<?php esc_attr_e('Select all users on this page', 'wp-user-management-plugin'); ?>"></td>
                            <?php foreach ($columns as $column) : ?>
                                <th scope="col"><?php echo esc_html($user_fields[$column]); ?></th>
                            <?php endforeach; ?>
                            <th scope="col"><?php esc_html_e('Roles', 'wp-user-management-plugin'); ?></th>
                            <?php foreach ($credential_columns as $label) : ?>
                                <th scope="col"><?php echo esc_html($label); ?></th>
                            <?php endforeach; ?>
                            <th scope="col"><?php esc_html_e('Actions', 'wp-user-management-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)) : ?>
                            <tr><td colspan="<?php echo esc_attr(count($columns) + count($credential_columns) + 3); ?>"><?php esc_html_e('No users found.', 'wp-user-management-plugin'); ?></td></tr>
                        <?php endif; ?>

                        <?php foreach ($users as $user) :
                            $can_delete = $user->ID !== $current_id && current_user_can('delete_user', $user->ID);
                            ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" class="wpum-user-cb" form="wpum-bulk-form" name="user_ids[]"
                                           value="<?php echo esc_attr($user->ID); ?>" data-login="<?php echo esc_attr($user->user_login); ?>"
                                           <?php disabled(!$can_delete); ?>
                                           aria-label="<?php echo esc_attr(sprintf(
                                               /* translators: %s: username */
                                               __('Select %s', 'wp-user-management-plugin'),
                                               $user->user_login
                                           )); ?>">
                                </th>

                                <?php foreach ($columns as $column) : ?>
                                    <td><?php echo esc_html((string) $user->$column); ?></td>
                                <?php endforeach; ?>

                                <td>
                                    <?php foreach ((array) $user->roles as $role) :
                                        $role_name = isset($roles[$role]['name']) ? translate_user_role($roles[$role]['name']) : $role;
                                        $remove_url = wp_nonce_url(
                                            add_query_arg(
                                                array('action' => 'wpum_remove_role', 'user_id' => $user->ID, 'role' => $role),
                                                admin_url('admin-post.php')
                                            ),
                                            'wpum_remove_role_' . $user->ID . '_' . $role
                                        );
                                        ?>
                                        <span class="wpum-role">
                                            <?php echo esc_html($role_name); ?>
                                            <a href="<?php echo esc_url($remove_url); ?>" class="wpum-role__remove"
                                               aria-label="<?php echo esc_attr(sprintf(
                                                   /* translators: %s: role name */
                                                   __('Remove role %s', 'wp-user-management-plugin'),
                                                   $role_name
                                               )); ?>">&times;</a>
                                        </span>
                                    <?php endforeach; ?>
                                </td>

                                <?php foreach ($credential_columns as $type => $label) :
                                    $credential = isset($credentials[$user->ID][$type]) ? $credentials[$user->ID][$type] : null;
                                    ?>
                                    <td>
                                        <?php if ($credential) : ?>
                                            <?php echo esc_html($credential->credential_number); ?>
                                            <?php if ('' !== $credential->file_path) : ?>
                                                <a href="<?php echo esc_url(wpum_credential_download_url($credential->id)); ?>" target="_blank" rel="noopener"
                                                   class="wpum-doc" title="<?php esc_attr_e('View document', 'wp-user-management-plugin'); ?>">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                    <span class="screen-reader-text"><?php esc_html_e('View document', 'wp-user-management-plugin'); ?></span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <td>
                                    <form method="post" class="wpum-inline-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="wpum_add_role">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('wpum_add_role_' . $user->ID)); ?>">
                                        <label class="screen-reader-text" for="wpum-role-<?php echo esc_attr($user->ID); ?>"><?php esc_html_e('Role', 'wp-user-management-plugin'); ?></label>
                                        <select name="role" id="wpum-role-<?php echo esc_attr($user->ID); ?>">
                                            <?php foreach ($roles as $role_key => $role) : ?>
                                                <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html(translate_user_role($role['name'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="button"><?php esc_html_e('Add role', 'wp-user-management-plugin'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// Where to go back to after an admin-post action.
function wpum_admin_return_url($args = array()) {
    $search = isset($_REQUEST['search']) ? sanitize_text_field(wp_unslash($_REQUEST['search'])) : '';
    $paged  = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 0;

    if ('' !== $search) {
        $args['search'] = $search;
    }
    if ($paged > 1) {
        $args['paged'] = $paged;
    }

    return wpum_admin_list_url($args);
}

// Handle adding a role
function wp_user_management_handle_add_role() {
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    check_admin_referer('wpum_add_role_' . $user_id);

    if (!current_user_can('promote_user', $user_id)) {
        wp_die(esc_html__('You are not allowed to change the roles of this user.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    $role = isset($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : '';
    $user = get_userdata($user_id);

    if (!$user || !array_key_exists($role, get_editable_roles())) {
        wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'role_invalid')));
        exit;
    }

    $user->add_role($role);

    wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'role_added')));
    exit;
}
add_action('admin_post_wpum_add_role', 'wp_user_management_handle_add_role');

// Handle removing a role (nonce-protected, capability-checked, lock-out safe)
function wp_user_management_handle_remove_role() {
    $user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;
    $role    = isset($_REQUEST['role']) ? sanitize_key(wp_unslash($_REQUEST['role'])) : '';
    check_admin_referer('wpum_remove_role_' . $user_id . '_' . $role);

    if (!current_user_can('promote_user', $user_id)) {
        wp_die(esc_html__('You are not allowed to change the roles of this user.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    $user = get_userdata($user_id);
    if (!$user || !in_array($role, (array) $user->roles, true)) {
        wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'role_invalid')));
        exit;
    }

    // Never let an admin strip their own administrator role or remove the last administrator.
    if ('administrator' === $role && ($user_id === get_current_user_id() || wpum_is_last_administrator($user_id))) {
        wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'role_blocked')));
        exit;
    }

    $user->remove_role($role);

    wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'role_removed')));
    exit;
}
add_action('admin_post_wpum_remove_role', 'wp_user_management_handle_remove_role');

// Handle bulk deletion of users
function wp_user_management_handle_bulk_delete() {
    check_admin_referer('wpum_bulk_delete_users');

    if (!current_user_can('delete_users')) {
        wp_die(esc_html__('You are not allowed to delete users.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    // The confirmation dialog sets this flag; without JavaScript nothing is deleted.
    if (!isset($_POST['wpum_confirm']) || '1' !== $_POST['wpum_confirm']) {
        wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'confirm_required')));
        exit;
    }

    $ids = array();
    if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
        $ids = array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['user_ids'])))));
    }
    if (empty($ids)) {
        wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'no_selection')));
        exit;
    }
    $ids = array_slice($ids, 0, 100);

    $reassign = null;
    if (isset($_POST['wpum_content']) && 'reassign' === $_POST['wpum_content']) {
        $reassign = isset($_POST['wpum_reassign_to']) ? absint($_POST['wpum_reassign_to']) : 0;
        if (!$reassign || !get_userdata($reassign) || in_array($reassign, $ids, true)) {
            wp_safe_redirect(wpum_admin_return_url(array('wpum_msg' => 'reassign_invalid')));
            exit;
        }
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';

    $current_id = get_current_user_id();
    $deleted    = 0;
    $skipped    = 0;

    foreach ($ids as $user_id) {
        if (
            $user_id === $current_id
            || !get_userdata($user_id)
            || !current_user_can('delete_user', $user_id)
            || wpum_is_last_administrator($user_id)
        ) {
            $skipped++;
            continue;
        }

        if (wp_delete_user($user_id, $reassign)) {
            $deleted++;
        } else {
            $skipped++;
        }
    }

    wp_safe_redirect(wpum_admin_return_url(array('wpum_deleted' => $deleted, 'wpum_skipped' => $skipped)));
    exit;
}
add_action('admin_post_wpum_bulk_delete_users', 'wp_user_management_handle_bulk_delete');

// Sanitize a page slug option (letters, digits, dash, underscore, slash).
function wpum_sanitize_page_slug($value, $default) {
    $value = strtolower(trim((string) $value));
    $value = trim(preg_replace('#[^a-z0-9/_-]#', '', $value), '/');

    return '' !== $value ? $value : $default;
}

// Display configuration page
function wp_user_management_configuration() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'wp-user-management-plugin'), '', array('response' => 403));
    }

    $user_fields = wpum_admin_user_fields();
    $page_labels = array(
        'account'  => array(__('Account page', 'wp-user-management-plugin'), 'user-account'),
        'register' => array(__('Registration page', 'wp-user-management-plugin'), 'user-register'),
        'reset'    => array(__('Password reset page', 'wp-user-management-plugin'), 'password-reset'),
    );

    if (isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD']) {
        check_admin_referer('wp_user_management_configuration');

        $columns = isset($_POST['columns']) && is_array($_POST['columns'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['columns']))
            : array();
        update_option('wp_user_management_columns', array_values(array_intersect($columns, array_keys($user_fields))));

        $metadata = isset($_POST['metadata']) && is_array($_POST['metadata'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['metadata']))
            : array();
        $metadata = array_values(array_filter($metadata, function ($meta_key) {
            return '' !== $meta_key && !wpum_is_forbidden_meta_key($meta_key);
        }));
        update_option('wp_user_management_metadata', $metadata);

        $password_length = isset($_POST['password_length']) ? absint($_POST['password_length']) : 8;
        update_option('wp_user_management_password_length', max(8, min(128, $password_length)));

        foreach ($page_labels as $key => $info) {
            $value = isset($_POST['page_' . $key]) ? wp_unslash($_POST['page_' . $key]) : '';
            update_option('wpum_page_' . $key, wpum_sanitize_page_slug($value, $info[1]));
        }

        $unconfirmed_days = isset($_POST['unconfirmed_days']) ? absint($_POST['unconfirmed_days']) : 2;
        update_option('wpum_unconfirmed_days', max(1, min(30, $unconfirmed_days)));

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'wp-user-management-plugin') . '</p></div>';
    }

    $columns         = wpum_admin_get_columns();
    $metadata        = get_option('wp_user_management_metadata', array());
    $metadata        = is_array($metadata) ? $metadata : array();
    $password_length = wpum_get_min_password_length();

    // All user meta keys except the protected ones (capabilities, sessions, screen options...).
    global $wpdb;
    $meta_keys     = $wpdb->get_col("SELECT DISTINCT meta_key FROM $wpdb->usermeta ORDER BY meta_key ASC");
    $user_metadata = array();
    foreach ($meta_keys as $meta_key) {
        if (!wpum_is_forbidden_meta_key($meta_key)) {
            $user_metadata[$meta_key] = ucfirst(str_replace('_', ' ', $meta_key));
        }
    }

    ?>
    <div class="wrap wpum-admin">
        <h1><?php esc_html_e('Configuration', 'wp-user-management-plugin'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('wp_user_management_configuration'); ?>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('User List Columns', 'wp-user-management-plugin'); ?></h2></div>
                <div class="wpum-checks">
                    <?php foreach ($user_fields as $field_key => $field_label) : ?>
                        <label class="wpum-inline-check">
                            <input type="checkbox" name="columns[]" value="<?php echo esc_attr($field_key); ?>" <?php checked(in_array($field_key, $columns, true)); ?>>
                            <?php echo esc_html($field_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('User Profile Metadata', 'wp-user-management-plugin'); ?></h2></div>
                <p class="description"><?php esc_html_e('Fields the user can edit on the "User Data" tab of the profile page. Sensitive internal keys (capabilities, sessions, screen options) are hidden on purpose.', 'wp-user-management-plugin'); ?></p>
                <div class="wpum-checks wpum-scroll">
                    <?php foreach ($user_metadata as $meta_key => $meta_label) : ?>
                        <label class="wpum-inline-check">
                            <input type="checkbox" name="metadata[]" value="<?php echo esc_attr($meta_key); ?>" <?php checked(in_array($meta_key, $metadata, true)); ?>>
                            <?php echo esc_html($meta_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('Password Settings', 'wp-user-management-plugin'); ?></h2></div>
                <label for="password_length"><?php esc_html_e('Minimum Password Length', 'wp-user-management-plugin'); ?></label>
                <input type="number" name="password_length" id="password_length" value="<?php echo esc_attr($password_length); ?>" min="8" max="128" class="small-text">
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('Registration', 'wp-user-management-plugin'); ?></h2></div>
                <p class="description"><?php esc_html_e('New users confirm their email address before an account is created. A registration that is not confirmed in time is deleted automatically (checked twice a day by WP-Cron) and its link stops working. The number of days is also stated in the confirmation email.', 'wp-user-management-plugin'); ?></p>
                <label for="unconfirmed_days"><?php esc_html_e('Days to keep an unconfirmed registration', 'wp-user-management-plugin'); ?></label>
                <input type="number" name="unconfirmed_days" id="unconfirmed_days" value="<?php echo esc_attr(wpum_get_unconfirmed_days()); ?>" min="1" max="30" class="small-text">
                <p class="description">
                    <?php
                    $waiting = wpum_count_pending_registrations();
                    echo esc_html(sprintf(
                        /* translators: %s: number of registrations */
                        _n('%s registration is waiting for confirmation right now.', '%s registrations are waiting for confirmation right now.', $waiting, 'wp-user-management-plugin'),
                        number_format_i18n($waiting)
                    ));
                    ?>
                </p>
            </div>

            <div class="wpum-card">
                <div class="wpum-card__head"><h2><?php esc_html_e('Page addresses', 'wp-user-management-plugin'); ?></h2></div>
                <p class="description"><?php esc_html_e('Slugs of the pages that contain the plugin shortcodes. They are used for redirects and links between the forms.', 'wp-user-management-plugin'); ?></p>
                <table class="form-table" role="presentation">
                    <?php foreach ($page_labels as $key => $info) : ?>
                        <tr>
                            <th scope="row"><label for="page_<?php echo esc_attr($key); ?>"><?php echo esc_html($info[0]); ?></label></th>
                            <td>
                                <code><?php echo esc_html(trailingslashit(home_url())); ?></code>
                                <input type="text" name="page_<?php echo esc_attr($key); ?>" id="page_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(wpum_get_page_slug($key)); ?>" class="regular-text code">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <?php submit_button(__('Save Settings', 'wp-user-management-plugin')); ?>
        </form>
    </div>
    <?php
}
