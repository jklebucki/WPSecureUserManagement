# WP User Management Plugin

## Description
User registration, login, password reset and profile editing for WordPress through shortcodes, plus an admin area for managing users. Forms can be protected with Cloudflare Turnstile.

## Features
- Registration with **email confirmation**: the visitor picks a preferred language and receives a branded HTML email (with a plain-text part) in that language; the account is created only when the link is opened.
- Login (username or email), password reset and a tabbed profile page (profile, user data, password, shooting credentials, delete account).
- Admin menu **User Management**:
  - **User List** - paginated, searchable list with roles, credentials and **bulk deletion** of accounts (confirmation dialog, optional content reassignment).
  - **Configuration** - list columns, profile metadata, minimum password length, page slugs, **days to keep an unconfirmed registration** (default 2) and a maintenance card that deletes leftover backup tables of old plugin versions.
  - **Turnstile** - Cloudflare Turnstile settings (keys, protected forms, appearance, key test).
- Cloudflare Turnstile on the plugin forms and, optionally, on the native `wp-login.php` forms.
- Security: nonces and capability checks everywhere, generic login/reset messages (no user enumeration), rate limiting, current password required for email/password changes and account deletion.
- Shooting credential PDFs are stored in a protected directory and served only to their owner and administrators.
- Privacy: credentials are erased with the account; personal data exporter/eraser and privacy policy text.
- One stylesheet scoped to `.wpum-ui` that wins over theme styling; colours can be changed through CSS custom properties.
- Polish and English translations (languages offered on the form are discovered from the compiled `.mo` files).

## Requirements
WordPress 5.9+, PHP 7.4+, and **working outgoing email** (registration needs it; use an SMTP plugin if the host's `mail()` is unreliable).

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

## Registration with email confirmation
1. The visitor fills in the form and chooses a **preferred language** (English plus every language the plugin ships a `.mo` file for).
2. Nothing is created yet. The data, with the password already hashed, waits in the table `{prefix}wpum_pending_registrations` and a confirmation email is sent in the chosen language. The email says until when the link works and that an unconfirmed registration is deleted automatically.
3. Opening the link (single use) creates the account with the site's default role, stores the language as the user's locale and shows a confirmation on the login page.

Because no account exists before confirmation, unconfirmed sign-ups never reach other plugins' `user_register` hooks, cannot be used to log in and cannot block a username for long.

**Automatic cleanup.** WordPress has no daemon, but it has **WP-Cron**: scheduled events that run when the site gets a visit. The plugin registers the event `wpum_cron_purge_registrations` (twice daily) that deletes registrations older than the number of days set under *User Management -> Configuration* (default **2**, 1-30). Expired links are refused immediately, even before the worker has run. On quiet sites WP-Cron fires late; add a real cron job (`wget -q -O - https://example.com/wp-cron.php?doing_wp_cron`) and `define( 'DISABLE_WP_CRON', true );` for exact timing.

Safeguards: a limit of requests per IP and per email address, Turnstile on the form, newer sign-up for the same address replaces the older one (only the newest link works), the link key is stored only as a SHA-256 hash.

**Email template.** The HTML lives in `templates/email-activation.php` (table layout, inline styles). Point the `wpum_email_template_path` filter at your own file, or change the texts with `wpum_activation_email_strings`. The `wpum_registration_confirmed` action fires after the account is created.

**Adding a language.** Put `wp-user-management-plugin-{locale}.mo` (e.g. `de_DE`) into `languages/`; it appears on the form and the email is sent in it. English is the source language and needs no file.

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
| `wpum_registration_ttl` | seconds an unconfirmed registration is kept (default: the Configuration setting in days) |
| `wpum_available_locales` | languages offered on the registration form (`locale => label`) |
| `wpum_email_template_path` | path of the template used for an email |
| `wpum_activation_email_strings` | texts and URLs passed to the confirmation email template |

## Languages
English (default), Polish.

## Support
Open an issue on the plugin's GitHub repository or contact the author.

## License
GPL v2 or later.
