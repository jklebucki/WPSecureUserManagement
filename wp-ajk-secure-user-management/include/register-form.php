<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Wyświetlanie formularza rejestracji
function sum_display_registration_form()
{
    ob_start(); ?>
    <div class="sum-registration-container">
        <form id="sum-registration-form" method="post">
            <h2><?php _e('User Registration', 'secure-user-management'); ?></h2>

            <label for="sum-username"><?php _e('Username', 'secure-user-management'); ?> *</label>
            <input type="text" name="sum_username" id="sum-username" required>

            <label for="sum-email"><?php _e('Email', 'secure-user-management'); ?> *</label>
            <input type="email" name="sum_email" id="sum-email" required>

            <label for="sum-firstname"><?php _e('First Name', 'secure-user-management'); ?> *</label>
            <input type="text" name="sum_firstname" id="sum-firstname" required>

            <label for="sum-lastname"><?php _e('Last Name', 'secure-user-management'); ?> *</label>
            <input type="text" name="sum_lastname" id="sum-lastname" required>

            <label for="sum-password"><?php _e('Password', 'secure-user-management'); ?> *</label>
            <input type="password" name="sum_password" id="sum-password" required>

            <label for="sum-confirm-password"><?php _e('Confirm Password', 'secure-user-management'); ?> *</label>
            <input type="password" name="sum_confirm_password" id="sum-confirm-password" required>

            <!-- CAPTCHA -->
            <label for="sum-captcha"><?php _e('Enter the code', 'secure-user-management'); ?> *</label>
            <div class="sum-captcha-container">
                <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'includes/captcha.php'); ?>" alt="CAPTCHA">
                <input type="text" name="sum_captcha" id="sum-captcha" required>
            </div>

            <input type="hidden" name="sum_register_nonce" value="<?php echo wp_create_nonce('sum_register_nonce'); ?>">
            <button type="submit"><?php _e('Register', 'secure-user-management'); ?></button>
        </form>
    </div>
<?php
    return ob_get_clean();
}

// Obsługa rejestracji użytkownika
function sum_process_registration()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sum_register_nonce'])) {
        if (!wp_verify_nonce($_POST['sum_register_nonce'], 'sum_register_nonce')) {
            wp_die(__('Security check failed!', 'secure-user-management'));
        }

        $username = sanitize_user($_POST['sum_username']);
        $email = sanitize_email($_POST['sum_email']);
        $firstname = sanitize_text_field($_POST['sum_firstname']);
        $lastname = sanitize_text_field($_POST['sum_lastname']);
        $password = $_POST['sum_password'];
        $confirm_password = $_POST['sum_confirm_password'];
        $captcha = sanitize_text_field($_POST['sum_captcha']);

        // Sprawdzanie wymaganych pól
        if (empty($username) || empty($email) || empty($firstname) || empty($lastname) || empty($password) || empty($confirm_password) || empty($captcha)) {
            wp_die(__('All fields are required.', 'secure-user-management'));
        }

        // Walidacja adresu email
        if (!is_email($email)) {
            wp_die(__('Invalid email format.', 'secure-user-management'));
        }

        // Sprawdzenie, czy użytkownik już istnieje
        if (username_exists($username) || email_exists($email)) {
            wp_die(__('Username or email already taken.', 'secure-user-management'));
        }

        // Walidacja haseł
        if ($password !== $confirm_password) {
            wp_die(__('Passwords do not match.', 'secure-user-management'));
        }

        // Sprawdzenie CAPTCHA
        if (!sum_check_captcha($captcha)) {
            wp_die(__('Invalid CAPTCHA.', 'secure-user-management'));
        }

        // Tworzenie użytkownika
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_die(__('Registration failed. Please try again.', 'secure-user-management'));
        }

        // Aktualizacja meta użytkownika
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $firstname,
            'last_name' => $lastname
        ]);

        // Przekierowanie do strony logowania
        wp_redirect(home_url('/user-login'));
        exit;
    }
}
add_action('init', 'sum_process_registration');

// Rejestracja shortcode
function sum_register_registration_shortcode()
{
    add_shortcode('sum_user_registration', 'sum_display_registration_form');
}
add_action('init', 'sum_register_registration_shortcode');
