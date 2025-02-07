<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include CAPTCHA functions
require_once plugin_dir_path(__FILE__) . 'captcha.php';

// Display registration form
function wpum_display_registration_form() {
    ob_start(); ?>
    <style>
        .wpum-registration-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .wpum-registration-container h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .wpum-registration-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .wpum-registration-container input[type="text"],
        .wpum-registration-container input[type="email"],
        .wpum-registration-container input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .wpum-registration-container .wpum-captcha-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .wpum-registration-container .wpum-captcha-container img {
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .wpum-registration-container button {
            width: 100%;
            padding: 10px;
            background: #0073aa;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .wpum-registration-container button:hover {
            background: #005177;
        }
    </style>
    <div class="wpum-registration-container">
        <form id="wpum-registration-form" method="post">
            <h2><?php _e('User Registration', 'wp-user-management-plugin'); ?></h2>

            <label for="wpum-username"><?php _e('Username', 'wp-user-management-plugin'); ?> *</label>
            <input type="text" name="wpum_username" id="wpum-username" required>

            <label for="wpum-email"><?php _e('Email', 'wp-user-management-plugin'); ?> *</label>
            <input type="email" name="wpum_email" id="wpum-email" required>

            <label for="wpum-firstname"><?php _e('First Name', 'wp-user-management-plugin'); ?> *</label>
            <input type="text" name="wpum_firstname" id="wpum-firstname" required>

            <label for="wpum-lastname"><?php _e('Last Name', 'wp-user-management-plugin'); ?> *</label>
            <input type="text" name="wpum_lastname" id="wpum-lastname" required>

            <label for="wpum-password"><?php _e('Password', 'wp-user-management-plugin'); ?> *</label>
            <input type="password" name="wpum_password" id="wpum-password" required>

            <label for="wpum-confirm-password"><?php _e('Confirm Password', 'wp-user-management-plugin'); ?> *</label>
            <input type="password" name="wpum_confirm_password" id="wpum-confirm-password" required>

            <!-- CAPTCHA -->
            <label for="wpum-captcha"><?php _e('Enter the code', 'wp-user-management-plugin'); ?> *</label>
            <div class="wpum-captcha-container">
                <?php sum_generate_captcha(); ?>
                <input type="text" name="wpum_captcha" id="wpum-captcha" required>
            </div>

            <input type="hidden" name="wpum_register_nonce" value="<?php echo wp_create_nonce('wpum_register_nonce'); ?>">
            <button type="submit"><?php _e('Register', 'wp-user-management-plugin'); ?></button>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Handle user registration
function wpum_process_registration() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpum_register_nonce'])) {
        if (!wp_verify_nonce($_POST['wpum_register_nonce'], 'wpum_register_nonce')) {
            wp_die(__('Security check failed!', 'wp-user-management-plugin'));
        }

        $username = sanitize_user($_POST['wpum_username']);
        $email = sanitize_email($_POST['wpum_email']);
        $firstname = sanitize_text_field($_POST['wpum_firstname']);
        $lastname = sanitize_text_field($_POST['wpum_lastname']);
        $password = $_POST['wpum_password'];
        $confirm_password = $_POST['wpum_confirm_password'];
        $captcha = sanitize_text_field($_POST['wpum_captcha']);

        // Check required fields
        if (empty($username) || empty($email) || empty($firstname) || empty($lastname) || empty($password) || empty($confirm_password) || empty($captcha)) {
            wp_die(__('All fields are required.', 'wp-user-management-plugin'));
        }

        // Validate email address
        if (!is_email($email)) {
            wp_die(__('Invalid email format.', 'wp-user-management-plugin'));
        }

        // Check if user already exists
        if (username_exists($username) || email_exists($email)) {
            wp_die(__('Username or email already taken.', 'wp-user-management-plugin'));
        }

        // Validate passwords
        if ($password !== $confirm_password) {
            wp_die(__('Passwords do not match.', 'wp-user-management-plugin'));
        }

        // Check CAPTCHA
        if (!sum_check_captcha($captcha)) {
            wp_die(__('Invalid CAPTCHA.', 'wp-user-management-plugin'));
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_die(__('Registration failed. Please try again.', 'wp-user-management-plugin'));
        }

        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $firstname,
            'last_name' => $lastname
        ]);

        // Redirect to login page
        wp_redirect(home_url('/user-login'));
        exit;
    }
}
add_action('init', 'wpum_process_registration');

// Register shortcode
function wpum_register_registration_shortcode() {
    add_shortcode('wpum_user_registration', 'wpum_display_registration_form');
}
add_action('init', 'wpum_register_registration_shortcode');
?>
