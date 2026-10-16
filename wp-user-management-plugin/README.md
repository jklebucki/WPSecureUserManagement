# WP User Management Plugin

## Description
User registration, login, password reset and profile editing for WordPress through shortcodes, plus an admin area for managing users. Forms can be protected with Cloudflare Turnstile.

## Features
- Registration, login (username or email), password reset and a tabbed profile page (profile, user data, password, shooting credentials, delete account).
- Admin menu **User Management**:
  - **User List** - paginated, searchable list with roles, credentials and **bulk deletion** of accounts (confirmation dialog, optional content reassignment).
  - **Configuration** - list columns, profile metadata, minimum password length, page slugs.
  - **Turnstile** - Cloudflare Turnstile settings (keys, protected forms, appearance, key test).
- Cloudflare Turnstile on the plugin forms and, optionally, on the native `wp-login.php` forms.
- Security: nonces and capability checks everywhere, generic login/reset messages (no user enumeration), rate limiting, current password required for email/password changes and account deletion.
- Shooting credential PDFs are stored in a protected directory and served only to their owner and administrators.
- Privacy: credentials are erased with the account; personal data exporter/eraser and privacy policy text.
- One stylesheet scoped to `.wpum-ui` that wins over theme styling; colours can be changed through CSS custom properties.
- Polish and English translations.

## Requirements
WordPress 5.9+, PHP 7.4+.

## Installation
1. Upload the `wp-user-management-plugin` folder to `/wp-content/plugins/` and activate it.
2. Create pages with the shortcodes below and, if they differ from the defaults, set their slugs under **User Management -> Configuration**.
3. Optional: configure **User Management -> Turnstile**.

## Shortcodes
| Shortcode | Default page slug |
|---|---|
| `[wpum_user_registration]` | `user-register` |
| `[wpum_user_login]` | |
| `[wpum_password_reset]` | `password-reset` |
| `[wpum_user_profile_edit]` | |
| `[wpum_my_account_content]` (profile when logged in, login form otherwise) | `user-account` |

## Cloudflare Turnstile
1. In the Cloudflare dashboard create a Turnstile widget for your domain and copy the site key and secret key.
2. Open **User Management -> Turnstile**, paste the keys, press **Test keys**, choose the protected forms and save.
3. The native `wp-login.php` forms are opt-in. Test the keys first.

Keys can also be set in `wp-config.php` (they then override the settings page):

```php
define( 'WPUM_TURNSTILE_SITE_KEY', '0x4AAAA...' );
define( 'WPUM_TURNSTILE_SECRET_KEY', '0x4AAAA...' );
```

If you ever lock yourself out, switch it off without touching the database:

```php
define( 'WPUM_TURNSTILE_DISABLE', true );
```

Cloudflare's test keys (always pass / always block) are listed on the settings page. Turnstile loads `challenges.cloudflare.com`; mention it in your privacy policy (the plugin adds suggested text to the WordPress privacy policy guide).

If the site is behind the Cloudflare proxy and you enable rate limiting by IP, tick *"This site is behind the Cloudflare proxy"* on the Turnstile page so the visitor IP is read from `CF-Connecting-IP`. Do it only when the origin accepts connections exclusively from Cloudflare.

## Private credential files
Documents are stored in `wp-content/uploads/wpum-private/`. The plugin writes an `.htaccess` that denies direct access (Apache). **On nginx** add the equivalent rule yourself:

```nginx
location ^~ /wp-content/uploads/wpum-private/ { deny all; return 404; }
```

Files have random names and are delivered through `admin-post.php?action=wpum_download_credential` only to their owner and to administrators.

## Filters
| Filter | Purpose |
|---|---|
| `wpum_users_per_page` | page size of the admin user list (default 20) |
| `wpum_rate_limit_{bucket}` | `array( max attempts, window in seconds )` for `login`, `login_ip`, `reset_ip`, `reset_email`, `register_ip`, `password_check` |
| `wpum_client_ip` | override the detected client IP |
| `wpum_credential_max_upload_size` | max PDF size in bytes (default 5 MB) |
| `wpum_reassign_user_id` | who receives the content of a user who deletes their own account (default: the oldest administrator) |
| `wpum_forbidden_meta_key` | mark more user meta keys as not editable by users |
| `wpum_turnstile_allowed_hostnames` | hostnames accepted in Turnstile responses |

## Languages
English (default), Polish.

## Support
Open an issue on the plugin's GitHub repository or contact the author.

## License
GPL v2 or later.
