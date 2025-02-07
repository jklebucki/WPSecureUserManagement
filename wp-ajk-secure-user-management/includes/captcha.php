<?php
if (!session_id()) {
    session_start();
}

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check CAPTCHA
function sum_check_captcha($captcha_input)
{
    if (isset($_SESSION['sum_captcha']) && $_SESSION['sum_captcha'] === $captcha_input) {
        return true;
    }
    return false;
}
