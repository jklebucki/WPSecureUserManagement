<?php

// Custom CAPTCHA implementation for user registration, login, and password reset

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure WordPress environment is loaded
if (!function_exists('plugin_dir_path')) {
    exit('WordPress environment not loaded.');
}

function sum_generate_captcha() {
    $captcha_code = '';
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    for ($i = 0; $i < 6; $i++) {
        $captcha_code .= $characters[rand(0, strlen($characters) - 1)];
    }
    $_SESSION['captcha'] = $captcha_code;

    $image = imagecreatetruecolor(120, 40);
    $background_color = imagecolorallocate($image, 255, 255, 255);
    $text_color = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 120, 40, $background_color);
    $font_path = MY_PLUGIN_DIR . 'fonts/NotoSans-Italic.ttf';
    if (file_exists($font_path)) {
        imagettftext($image, 20, 0, 10, 30, $text_color, $font_path, $captcha_code);
    } else {
        imagestring($image, 5, 10, 10, $captcha_code, $text_color);
    }
    
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
}

function sum_check_captcha($input) {
    return isset($_SESSION['captcha']) && strtoupper($input) === strtoupper($_SESSION['captcha']);
}
?>