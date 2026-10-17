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
- Security: nonces and capability checks everywhere, generic login/reset messages (no user enumeration), configurable rate limiting, current password required for email/password changes and account deletion.
- **xmlrpc.php is blocked** (checkbox, on by default): on Apache the plugin writes a verified, marked block into `.htaccess`, everywhere else WordPress itself refuses XML-RPC.
- **wp-login.php is replaced** by the plugin's pages (login, registration, lost password, **setting a new password**) and **logout** goes straight to the front page, with a checkbox in *Configuration*, on by default.
- Password reset with an HTML email in the account's language and a "choose a new password" form on the plugin's own page.
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

**Email template.** All plugin emails (registration confirmation, password reset) share the layout `templates/email.php` (table layout, inline styles, a grey box for the details lines). Point the `wpum_email_template_path` filter at your own file, or change the texts with `wpum_activation_email_strings` / `wpum_password_reset_email_strings`. The `wpum_registration_confirmed` action fires after the account is created.

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

## Replacing wp-login.php
*User Management -> Configuration -> wp-login.php -> "Disable wp-login.php"* (on by default).

- Opening `wp-login.php` for logging in, registering or "lost password" redirects to the plugin's account, registration and password-reset pages, so everything carries Turnstile, rate limiting and your styling. A POST to `wp-login.php` is never processed (no password guessing against it).
- WordPress links point to those pages as well (`login_url`, `register_url`, `lostpassword_url`), including the redirect from `/wp-admin`: after logging in on the plugin page you land back where you were (`redirect_to` is validated, foreign hosts are ignored).
- **Logout** (`wp-login.php?action=logout`, the admin bar, themes) logs out and redirects to the front page (`wpum_logout_redirect` filter). A link without a valid nonce never logs anybody out: the account page opens its Logout tab and asks for confirmation. Not logged in? Straight to the front page.
- **Reset screens:** WordPress's `rp` / `resetpass` links (also those in older emails) are forwarded to the plugin's password-reset page, and a POST to the WordPress reset form is not processed. See *Password reset* below.
- **Left to WordPress on purpose:** password-protected posts (`postpass`) and WordPress's internal confirmations. Visitors who are already logged in are not redirected away from `wp-login.php`.
- **No lock-out:** each form is replaced only when its page exists, is published and really shows the form. The shortcode may be in the page content (also in a Gutenberg *Shortcode* block), in a reusable block (synced pattern) the page uses or in Elementor data; if it is not found there, the page is loaded like a visitor would and checked for the form (in the background, cached for 12 hours, 10 minutes when it failed). A draft that shares the slug never counts. Login: `[wpum_my_account_content]` or `[wpum_user_login]`, registration: `[wpum_user_registration]`, reset: `[wpum_password_reset]`. Otherwise WordPress keeps its own form. The Configuration page shows for each page why it is (not) accepted, links to the editor and has a *Check the pages again* button.
- **Emergency switch:** `define( 'WPUM_KEEP_WP_LOGIN', true );` in `wp-config.php` turns the replacement off regardless of the setting.
- Other builders can add the text they keep the shortcode in with the `wpum_page_content_sources` filter, or confirm a page with `wpum_page_is_ready`; the list of actions left to WordPress is filterable with `wpum_wp_login_allowed_actions`.
- `xmlrpc.php` is a separate entry point and is not touched by this option.

## Blocking xmlrpc.php
*Configuration -> XML-RPC -> "Block xmlrpc.php"* (on by default). `xmlrpc.php` is a second entry for guessing passwords (one request can try many) and for pingback abuse, and replacing wp-login.php does not touch it.

- **WordPress layer (any server):** authenticated XML-RPC calls are refused, the pingback methods and the `X-Pingback` header are removed.
- **Apache / LiteSpeed layer:** the plugin adds this block to the `.htaccess` in the WordPress folder, between its own markers, with WordPress's `insert_with_markers()`; everything else in the file is left alone:
  ```apache
  # BEGIN WP User Management
  <Files xmlrpc.php>
  <IfModule mod_authz_core.c>
  Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
  Order deny,allow
  Deny from all
  </IfModule>
  </Files>
  # END WP User Management
  ```
- **A bad `.htaccess` line takes a whole site down, so the block is checked right after it is written**: the home page must still answer and `xmlrpc.php` must now return 403. If the site answers with an error, if `.htaccess` is ignored (`AllowOverride None`) or if the site cannot call itself, the block is removed again and the Configuration page says which of these happened. If the file is not writable the page shows the lines to add by hand.
- The block is removed when you switch the option off and when the plugin is deactivated. After a plugin update it is (re)applied in the background through WP-Cron.
- **This breaks apps that need XML-RPC** (the WordPress mobile app, Jetpack, some desktop publishing tools). Switch the option off for them, or put `define( 'WPUM_ALLOW_XMLRPC', true );` in `wp-config.php` (also removes the block). `define( 'WPUM_NO_HTACCESS', true );` keeps the plugin away from `.htaccess` but leaves the WordPress layer on.
- `wpum_xmlrpc_htaccess_rules` filters the lines of the block.

## Password reset
1. On the `[wpum_password_reset]` page the visitor enters an email address; the answer is always the same, whether or not an account exists, and requests are limited per IP and per address.
2. An **HTML email with a plain-text part** (same layout as the registration email) is sent in the **account's own language** (user locale, falling back to the closest available language). It links to the plugin page: `/password-reset/?wpum_reset=KEY&login=LOGIN`, valid for 24 hours (the `password_reset_expiration` filter applies).
3. Opening the link checks the key, moves it into an HttpOnly cookie and redirects to the clean page URL, so the key does not stay in the address bar, history or referrers (the same technique WordPress uses). If the reset page is not usable the email falls back to WordPress's own link.
4. The same page now shows **"Choose a new password"** (strength meter, confirmation, minimum length from the settings). Saving it calls WordPress's `reset_password()` (same hooks as wp-login.php, including the admin notification), ends **all** sessions of the account and sends the visitor to the login page with a confirmation. The page is excluded from page caches while the form is shown.
5. Invalid, expired, forged or reused links show a clear message and the request form; guessing keys is rate limited (10 per hour per IP by default).

A successful login also invalidates outstanding reset links (WordPress behaviour).

## Rate limiting
*Configuration -> Rate limiting*: a switch and, for each limit, the maximum number of attempts and the time window in minutes.

| Limit | Default |
|---|---|
| Failed logins per account and IP | 5 / 15 min |
| Failed logins per IP (any account) | 20 / 15 min |
| Password reset requests per IP / per email address | 5 / 60 min, 3 / 60 min |
| Password reset link attempts per IP | 10 / 60 min |
| Registrations per IP / per email address | 5 / 60 min, 3 / 60 min |
| Confirmation link attempts per IP | 20 / 60 min |
| Wrong current-password attempts per user | 5 / 15 min |
| Form messages per IP (keeps invalid posts from filling `wp_options`) | 30 / 1 min |

A club registering many people from one connection should raise the registration limit. *Maintenance -> Clear rate-limit counters* releases everyone who has been locked out.

**Visitor IP.** The plugin reads `REMOTE_ADDR` and trusts the `CF-Connecting-IP` header only when the connection comes from Cloudflare's edge network or from the machine itself (cloudflared tunnel, local reverse proxy), so the header cannot be forged by anyone else. IPv6 visitors are grouped by `/64`. The Configuration page shows the IP the plugin sees for your own request and where it came from.

**Cloudflare's list stays current.** A built-in copy of Cloudflare's published ranges is always used. When a request carries `CF-Connecting-IP` from an address that is not in the list (a forged header, or a range Cloudflare added), the plugin asks WP-Cron to download `https://www.cloudflare.com/ips-v4` and `/ips-v6` (at most once an hour, never inside the visitor's request), merges them with the built-in list and keeps the result. An answer with too few ranges, malformed lines or absurdly wide ranges is rejected. *Maintenance -> Refresh Cloudflare IP ranges* does it on demand. Sites without Cloudflare traffic never contact Cloudflare for this. Other trusted proxies can be added with the `wpum_trusted_proxy_ranges` filter.

## Private credential files
Documents are stored in `wp-content/uploads/wpum-private/`. The plugin writes an `.htaccess` that denies direct access (Apache). **On nginx** add the equivalent rule yourself:

```nginx
location ^~ /wp-content/uploads/wpum-private/ { deny all; return 404; }
```

On Apache/LiteSpeed the plugin creates this rule itself (an `.htaccess` inside the folder, plus an empty `index.php`); the Apache `.htaccess` is only honoured where `AllowOverride` permits it, which you can check by opening `/wp-content/uploads/wpum-private/index.php`: it must answer 403.

Files have random names and are delivered through `admin-post.php?action=wpum_download_credential` only to their owner and to administrators.

## Filters
| Filter | Purpose |
|---|---|
| `wpum_users_per_page` | page size of the admin user list (default 20) |
| `wpum_rate_limit_{bucket}` | final say on a limit: `array( max attempts, window in seconds )` for `login`, `login_ip`, `reset_ip`, `reset_email`, `reset_link`, `register_ip`, `register_email`, `activation_ip`, `password_check`, `flash` |
| `wpum_trusted_proxy_ranges` | CIDR ranges whose `CF-Connecting-IP` header is trusted (Cloudflare + localhost by default) |
| `wpum_wp_login_allowed_actions` | `wp-login.php` actions that stay with WordPress when it is disabled |
| `wpum_page_is_ready` | confirm that a page can replace a `wp-login.php` form (`login`, `register`, `reset`) |
| `wpum_page_content_sources` | extra text to search for the plugin's shortcodes (page builder data) |
| `wpum_xmlrpc_htaccess_rules` | the lines the plugin writes into `.htaccess` to block `xmlrpc.php` |
| `wpum_client_ip` | override the detected client IP |
| `wpum_credential_max_upload_size` | max PDF size in bytes (default 5 MB) |
| `wpum_reassign_user_id` | who receives the content of a user who deletes their own account (default: the oldest administrator) |
| `wpum_forbidden_meta_key` | mark more user meta keys as not editable by users |
| `wpum_turnstile_allowed_hostnames` | hostnames accepted in Turnstile responses |
| `wpum_registration_ttl` | seconds an unconfirmed registration is kept (default: the Configuration setting in days) |
| `wpum_available_locales` | languages offered on the registration form (`locale => label`) |
| `wpum_email_template_path` | path of the template used for an email |
| `wpum_activation_email_strings` | texts and URLs passed to the confirmation email template |
| `wpum_password_reset_email_strings` | the same for the password reset email |
| `wpum_logout_redirect` | where visitors land after logging out (default: the front page) |

## Development
`.wp-env.json` in the repository root describes a local test site (needs Docker and Node):

```bash
npx @wordpress/env start     # http://localhost:8890 (admin / password), debug log on
npx @wordpress/env stop
```

- The main site runs the latest stable WordPress on **PHP 8.3** (the version WordPress itself recommends). The "tests" site (`http://localhost:8891`) runs **PHP 7.4**, the oldest version the plugin supports, so both ends can be checked.
- Another PHP or WordPress version for one session: `WP_ENV_PHP_VERSION=8.5 npx @wordpress/env start --update` (likewise `WP_ENV_CORE`).
- The plugin is mounted live from `wp-user-management-plugin/`; emails are not delivered locally, capture them with a mu-plugin or a mail catcher.

## Languages
English (default), Polish.

## Support
Open an issue on the plugin's GitHub repository or contact the author.

## License
GPL v2 or later.
