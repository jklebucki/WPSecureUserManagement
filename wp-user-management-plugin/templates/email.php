<?php
/**
 * Layout of the plugin's emails (registration confirmation, password reset).
 *
 * Email clients ignore most modern CSS, so the layout is table based with inline styles. All text arrives already
 * translated in $s (see wpum_build_activation_email() and wpum_build_password_reset_email()). To use your own markup,
 * point the "wpum_email_template_path" filter at another file.
 *
 * @var array $s Strings and URLs: lang, site, site_url, url, preheader, title, greeting, intro, button, fallback,
 *               details (array of short lines shown in the grey box), ignore, footer.
 */

if (!defined('ABSPATH')) {
    exit;
}

$font   = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
$accent = '#3b5bdb';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($s['lang']); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title><?php echo esc_html($s['title']); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;"><?php echo esc_html($s['preheader']); ?></div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
                    <tr>
                        <td align="center" style="padding:0 8px 18px;font-family:<?php echo $font; ?>;font-size:16px;font-weight:700;letter-spacing:-0.01em;color:#374151;">
                            <a href="<?php echo esc_url($s['site_url']); ?>" style="color:#374151;text-decoration:none;"><?php echo esc_html($s['site']); ?></a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #e5e7eb;border-radius:14px;padding:36px 32px;font-family:<?php echo $font; ?>;color:#1f2937;">
                            <h1 style="margin:0 0 20px;font-family:<?php echo $font; ?>;font-size:22px;line-height:1.3;font-weight:700;color:#111827;"><?php echo esc_html($s['title']); ?></h1>

                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1f2937;"><?php echo esc_html($s['greeting']); ?></p>
                            <p style="margin:0 0 28px;font-size:15px;line-height:1.6;color:#374151;"><?php echo esc_html($s['intro']); ?></p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 28px;">
                                <tr>
                                    <td align="center" bgcolor="<?php echo esc_attr($accent); ?>" style="border-radius:9px;background-color:<?php echo esc_attr($accent); ?>;">
                                        <a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener" style="display:inline-block;padding:14px 32px;border-radius:9px;background-color:<?php echo esc_attr($accent); ?>;font-family:<?php echo $font; ?>;font-size:16px;font-weight:600;line-height:1.2;color:#ffffff;text-decoration:none;"><?php echo esc_html($s['button']); ?></a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#6b7280;"><?php echo esc_html($s['fallback']); ?></p>
                            <p style="margin:0 0 24px;font-size:13px;line-height:1.5;word-break:break-all;"><a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener" style="color:<?php echo esc_attr($accent); ?>;text-decoration:underline;"><?php echo esc_html($s['url']); ?></a></p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
                                <tr>
                                    <td style="padding:14px 16px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:9px;font-size:13px;line-height:1.6;color:#4b5563;">
                                        <?php echo implode('<br>', array_map('esc_html', (array) $s['details'])); ?>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;"><?php echo esc_html($s['ignore']); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 8px 0;font-family:<?php echo $font; ?>;font-size:12px;line-height:1.5;color:#9ca3af;"><?php echo esc_html($s['footer']); ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
