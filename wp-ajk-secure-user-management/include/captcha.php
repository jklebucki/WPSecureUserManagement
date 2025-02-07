<?php
if (!session_id()) {
    session_start();
}

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

header("Content-type: image/png");

$captcha_code = substr(md5(uniqid(mt_rand(), true)), 0, 6);
$_SESSION['sum_captcha'] = $captcha_code;

$image = imagecreatetruecolor(120, 40);
$bg_color = imagecolorallocate($image, 255, 255, 255);
$text_color = imagecolorallocate($image, 0, 0, 0);
$line_color = imagecolorallocate($image, 200, 200, 200);

imagefilledrectangle($image, 0, 0, 120, 40, $bg_color);

// Dodajemy szum (linie)
for ($i = 0; $i < 10; $i++) {
    imageline($image, rand(0, 120), rand(0, 40), rand(0, 120), rand(0, 40), $line_color);
}

// Dodajemy tekst CAPTCHA
imagestring($image, 5, 30, 10, $captcha_code, $text_color);

imagepng($image);
imagedestroy($image);
