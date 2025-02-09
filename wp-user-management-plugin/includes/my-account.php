<?php
if (!defined('ABSPATH')) {
    exit;
}

function wpum_my_account_shortcode()
{
    if (is_user_logged_in()) {
        return do_shortcode('[wpum_user_profile_edit]');
    } else {
        return do_shortcode('[wpum_user_login]');
    }
}
