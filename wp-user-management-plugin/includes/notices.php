<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Flash notices for front-end forms (Post/Redirect/Get).
 *
 * The message lives in a short-lived transient; only a random token travels in the URL,
 * so no personal data ends up in links, referrers or server logs.
 */

// Map of notice codes to (translatable) messages. Only codes are ever stored or put in URLs.
function wpum_notice_messages()
{
    return array(
        'invalid_nonce'         => __('Your session has expired. Please try again.', 'wp-user-management-plugin'),
        'rate_limited'          => __('Too many attempts. Please wait a few minutes and try again.', 'wp-user-management-plugin'),
        'turnstile_missing'     => __('Please complete the security check and try again.', 'wp-user-management-plugin'),
        'turnstile_failed'      => __('The security check failed. Please try again.', 'wp-user-management-plugin'),
        'turnstile_unavailable' => __('The security check is temporarily unavailable. Please try again later.', 'wp-user-management-plugin'),
        'fields_required'       => __('All fields are required.', 'wp-user-management-plugin'),
        'invalid_email'         => __('Invalid email format.', 'wp-user-management-plugin'),
        'invalid_username'      => __('Invalid username.', 'wp-user-management-plugin'),
        'user_exists'           => __('Username or email already taken.', 'wp-user-management-plugin'),
        'email_taken'           => __('Email is already taken.', 'wp-user-management-plugin'),
        'password_mismatch'     => __('Passwords do not match.', 'wp-user-management-plugin'),
        'password_weak'         => __('Password does not meet the strength requirements.', 'wp-user-management-plugin'),
        'wrong_password'        => __('Your current password is incorrect.', 'wp-user-management-plugin'),
        'register_failed'       => __('Registration failed. Please try again.', 'wp-user-management-plugin'),
        'registered'            => __('Account created. You can now log in.', 'wp-user-management-plugin'),
        'login_failed'          => __('Invalid username/email or password.', 'wp-user-management-plugin'),
        'reset_sent'            => __('If an account with that email address exists, password reset instructions have been sent.', 'wp-user-management-plugin'),
        'profile_updated'       => __('Profile updated.', 'wp-user-management-plugin'),
        'password_changed'      => __('Your password has been changed.', 'wp-user-management-plugin'),
        'data_saved'            => __('Data saved.', 'wp-user-management-plugin'),
        'update_failed'         => __('Update failed. Please try again.', 'wp-user-management-plugin'),
        'last_admin'            => __('The last administrator account cannot be deleted.', 'wp-user-management-plugin'),
        'delete_failed'         => __('Error deleting account. Please try again.', 'wp-user-management-plugin'),
        'not_logged_in'         => __('You must be logged in to perform this action.', 'wp-user-management-plugin'),
    );
}

// Store a notice and return its token.
function wpum_flash_set($form, $type, $code, $old = array())
{
    $token = wp_generate_password(20, false);
    set_transient('wpum_flash_' . $token, array(
        'form' => (string) $form,
        'type' => 'success' === $type ? 'success' : 'error',
        'code' => (string) $code,
        'old'  => is_array($old) ? $old : array(),
    ), 2 * MINUTE_IN_SECONDS);

    return $token;
}

// Store a notice, then redirect (and exit) to $url with the token attached.
function wpum_redirect_with_flash($url, $form, $type, $code, $old = array())
{
    $token = wpum_flash_set($form, $type, $code, $old);
    wp_safe_redirect(add_query_arg('wpum_t', $token, $url));
    exit;
}

// Read (once per request) the notice referenced by ?wpum_t=. With $form, returns it only if it targets that form.
function wpum_flash_get($form = null)
{
    static $flash = false;

    if (false === $flash) {
        $flash = null;
        if (isset($_GET['wpum_t']) && is_string($_GET['wpum_t'])) {
            $token = preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['wpum_t']));
            if ('' !== $token) {
                $data = get_transient('wpum_flash_' . $token);
                if (is_array($data)) {
                    delete_transient('wpum_flash_' . $token);
                    $flash = $data;
                }
            }
        }
    }

    if (null === $flash) {
        return null;
    }
    if (null !== $form && $flash['form'] !== $form) {
        return null;
    }

    return $flash;
}

// Value the user typed before an error redirect (never contains passwords).
function wpum_flash_old($form, $key, $default = '')
{
    $flash = wpum_flash_get($form);
    if ($flash && isset($flash['old'][$key]) && is_scalar($flash['old'][$key])) {
        return (string) $flash['old'][$key];
    }

    return $default;
}

// Print the notice that targets $form, if any.
function wpum_flash_render($form)
{
    $flash = wpum_flash_get($form);
    if (!$flash) {
        return;
    }

    $messages = wpum_notice_messages();
    if (!isset($messages[$flash['code']])) {
        return;
    }

    $is_success = 'success' === $flash['type'];
    printf(
        '<div class="wpum-notice wpum-notice--%1$s" role="%2$s">%3$s</div>',
        $is_success ? 'success' : 'error',
        $is_success ? 'status' : 'alert',
        esc_html($messages[$flash['code']])
    );
}
