<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Privacy / GDPR: erase credentials with the account, personal data exporter + eraser, policy text.
 */

// Account deleted (admin, bulk delete, self-service) -> remove credential rows and documents.
add_action('delete_user', 'wpum_delete_user_credentials');

// Multisite: user removed from the current site only.
function wpum_on_remove_user_from_blog($user_id, $blog_id) {
    if ((int) $blog_id === (int) get_current_blog_id()) {
        wpum_delete_user_credentials($user_id);
    }
}
add_action('remove_user_from_blog', 'wpum_on_remove_user_from_blog', 10, 2);

// Exporter (Tools -> Export Personal Data)
function wpum_register_privacy_exporter($exporters) {
    $exporters['wp-user-management-plugin'] = array(
        'exporter_friendly_name' => __('Shooting credentials', 'wp-user-management-plugin'),
        'callback'               => 'wpum_privacy_export_credentials',
    );

    return $exporters;
}
add_filter('wp_privacy_personal_data_exporters', 'wpum_register_privacy_exporter');

function wpum_privacy_export_credentials($email_address, $page = 1) {
    $items = array();
    $user  = get_user_by('email', $email_address);

    // Registrations that wait for the confirmation link (no account exists yet).
    foreach (wpum_get_pending_registrations_by_email($email_address) as $pending) {
        $pending_data = json_decode($pending->data, true);
        $pending_data = is_array($pending_data) ? $pending_data : array();

        $items[] = array(
            'group_id'          => 'wpum-pending-registration',
            'group_label'       => __('Registration awaiting confirmation', 'wp-user-management-plugin'),
            'group_description' => __('Data of a registration that has not been confirmed by email yet. It is deleted automatically.', 'wp-user-management-plugin'),
            'item_id'           => 'wpum-pending-' . (int) $pending->id,
            'data'              => array(
                array('name' => __('Username', 'wp-user-management-plugin'), 'value' => isset($pending_data['login']) ? $pending_data['login'] : ''),
                array('name' => __('First Name', 'wp-user-management-plugin'), 'value' => isset($pending_data['first_name']) ? $pending_data['first_name'] : ''),
                array('name' => __('Last Name', 'wp-user-management-plugin'), 'value' => isset($pending_data['last_name']) ? $pending_data['last_name'] : ''),
                array('name' => __('Preferred language', 'wp-user-management-plugin'), 'value' => isset($pending_data['locale']) ? $pending_data['locale'] : ''),
                array('name' => __('Registration deleted after', 'wp-user-management-plugin'), 'value' => $pending->expires_at . ' UTC'),
            ),
        );
    }

    if ($user) {
        $types = wpum_get_shooting_credential_types();
        foreach (wpum_get_user_credentials($user->ID) as $row) {
            $items[] = array(
                'group_id'          => 'wpum-credentials',
                'group_label'       => __('Shooting credentials', 'wp-user-management-plugin'),
                'group_description' => __('Credential numbers and uploaded documents stored by WP User Management Plugin.', 'wp-user-management-plugin'),
                'item_id'           => 'wpum-credential-' . (int) $row->id,
                'data'              => array(
                    array(
                        'name'  => __('Type', 'wp-user-management-plugin'),
                        'value' => isset($types[$row->credential_type]) ? $types[$row->credential_type] : $row->credential_type,
                    ),
                    array(
                        'name'  => __('Number', 'wp-user-management-plugin'),
                        'value' => $row->credential_number,
                    ),
                    array(
                        'name'  => __('Document uploaded', 'wp-user-management-plugin'),
                        'value' => '' !== $row->file_path ? __('Yes', 'wp-user-management-plugin') : __('No', 'wp-user-management-plugin'),
                    ),
                    array(
                        'name'  => __('Last updated', 'wp-user-management-plugin'),
                        'value' => $row->uploaded_at,
                    ),
                ),
            );
        }
    }

    return array('data' => $items, 'done' => true);
}

// Eraser (Tools -> Erase Personal Data)
function wpum_register_privacy_eraser($erasers) {
    $erasers['wp-user-management-plugin'] = array(
        'eraser_friendly_name' => __('Shooting credentials', 'wp-user-management-plugin'),
        'callback'             => 'wpum_privacy_erase_credentials',
    );

    return $erasers;
}
add_filter('wp_privacy_personal_data_erasers', 'wpum_register_privacy_eraser');

function wpum_privacy_erase_credentials($email_address, $page = 1) {
    // Waiting registrations are removed even though no account exists for the address.
    $removed = wpum_delete_pending_registrations_by_email($email_address);
    $user    = get_user_by('email', $email_address);

    if ($user) {
        $removed += wpum_delete_user_credentials($user->ID);
    }

    return array(
        'items_removed'  => $removed > 0,
        'items_retained' => false,
        'messages'       => array(),
        'done'           => true,
    );
}

// Suggested privacy policy text (Settings -> Privacy -> Policy Guide)
function wpum_add_privacy_policy_content() {
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }

    $content  = '<h2>' . esc_html__('Shooting credentials', 'wp-user-management-plugin') . '</h2>';
    $content .= '<p>' . esc_html__('If you add a shooting patent or instructor license to your account, we store its number and the PDF document you upload. The document is kept in a protected directory and is visible only to you and the site administrators. It is deleted together with your account.', 'wp-user-management-plugin') . '</p>';
    $days     = wpum_get_unconfirmed_days();
    $content .= '<h2>' . esc_html__('Registration confirmation', 'wp-user-management-plugin') . '</h2>';
    $content .= '<p>' . esc_html(sprintf(
        /* translators: %d: number of days */
        _n('When you register, we keep your name, email address, username, language and a hash of your password until you confirm your email address, but no longer than %d day. Unconfirmed registrations are then deleted automatically.', 'When you register, we keep your name, email address, username, language and a hash of your password until you confirm your email address, but no longer than %d days. Unconfirmed registrations are then deleted automatically.', $days, 'wp-user-management-plugin'),
        $days
    )) . '</p>';
    $content .= '<h2>' . esc_html__('Cloudflare Turnstile', 'wp-user-management-plugin') . '</h2>';
    $content .= '<p>' . esc_html__('The login, registration and password reset forms may be protected by Cloudflare Turnstile. When it is active, your browser contacts Cloudflare and your IP address and browser data are processed by Cloudflare to tell humans from bots.', 'wp-user-management-plugin') . '</p>';

    wp_add_privacy_policy_content(__('WP User Management Plugin', 'wp-user-management-plugin'), wp_kses_post($content));
}
add_action('admin_init', 'wpum_add_privacy_policy_content');
