<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load plugin text domain for translations
function sum_load_textdomain() {
    load_plugin_textdomain('secure-user-management', false, dirname(plugin_basename(__FILE__)) . '/../languages');
}
add_action('plugins_loaded', 'sum_load_textdomain');

// Function to create language directory and add default translation files
function sum_create_language_directory() {
    $lang_dir = plugin_dir_path(__FILE__) . '/../languages';
    
    if (!file_exists($lang_dir)) {
        mkdir($lang_dir, 0755, true);
    }

    $polish_translation = $lang_dir . '/secure-user-management-pl_PL.po';
    
    if (!file_exists($polish_translation)) {
        file_put_contents($polish_translation, 
            "msgid \"User Registration\"\nmsgstr \"Rejestracja użytkownika\"\n\n" .
            "msgid \"User Login\"\nmsgstr \"Logowanie użytkownika\"\n\n" .
            "msgid \"User Profile\"\nmsgstr \"Profil użytkownika\"\n\n" .
            "msgid \"Password Reset\"\nmsgstr \"Resetowanie hasła\"\n\n"
        );
    }
}
