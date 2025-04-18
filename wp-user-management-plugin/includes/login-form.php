<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Enqueue styles
function sum_enqueue_styles()
{
    wp_enqueue_style('sum-login-form', plugin_dir_url(__FILE__) . 'login-form.css');
}
add_action('wp_enqueue_scripts', 'sum_enqueue_styles');

// Display login form
function sum_display_login_form()
{
    ob_start(); ?>
    <div class="sum-login-container">
        <form id="sum-login-form" method="post">
            <h2><?php _e('User Login', 'wp-user-management-plugin'); ?></h2>

            <label for="sum-username-email"><?php _e('Username or Email', 'wp-user-management-plugin'); ?> *</label>
            <input type="text" name="sum_username_email" id="sum-username-email" required>

            <label for="sum-password"><?php _e('Password', 'wp-user-management-plugin'); ?> *</label>
            <input type="password" name="sum_password" id="sum-password" required>

            <input type="hidden" name="sum_login_nonce" value="<?php echo wp_create_nonce('sum_login_nonce'); ?>">
            <button type="submit"><?php _e('Login', 'wp-user-management-plugin'); ?></button>
        </form>
        <p><a href="<?php echo wp_lostpassword_url(); ?>"><?php _e('Forgot Password?', 'wp-user-management-plugin'); ?></a></p>
        <p><a href="<?php echo home_url('/register'); ?>"><?php _e('Register', 'wp-user-management-plugin'); ?></a></p>
    </div>
<?php
    return ob_get_clean();
}

// Handle user login
function sum_process_login()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_login_nonce'])) {
        if (! wp_verify_nonce($_POST['sum_login_nonce'], 'sum_login_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $username_email = sanitize_text_field($_POST['sum_username_email']);
        $password       = $_POST['sum_password'];

        // Attempt to log the user in
        $creds = array();
        if (is_email($username_email)) {
            $user = get_user_by('email', $username_email);
            if ($user) {
                $creds['user_login'] = $user->user_login;
            }
        } else {
            $creds['user_login'] = $username_email;
        }
        $creds['user_password'] = $password;
        $creds['remember']      = true;

        // Set secure cookie dynamically (if the site uses SSL or requires SSL in the admin panel)
        $secure_cookie = is_ssl() || force_ssl_admin();
        $user = wp_signon($creds, $secure_cookie);

        if (is_wp_error($user)) {
            wp_die(__('Login failed. Please check your credentials.', 'wp-user-management-plugin'));
        } else {
            wp_redirect(home_url('/'));
            exit;
        }
    }
}
add_action('init', 'sum_process_login');

// Register shortcode
function sum_register_login_shortcode()
{
    add_shortcode('sum_user_login', 'sum_display_login_form');
}
add_action('init', 'sum_register_login_shortcode');
?>