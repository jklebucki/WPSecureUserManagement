<?php
// Custom CAPTCHA implementation for user registration, login, and password reset

session_start();

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
    imagettftext($image, 20, 0, 10, 30, $text_color, dirname(__FILE__) . '/fonts/arial.ttf', $captcha_code);
    
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
}

function sum_check_captcha($input) {
    return isset($_SESSION['captcha']) && strtoupper($input) === strtoupper($_SESSION['captcha']);
}
?>