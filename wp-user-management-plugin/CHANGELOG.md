# Changelog

## 1.5.4

### Fixed
- **The search on the admin user list only looked at the ID, username and email**, so names shown in the list found nobody. It now searches the account fields (ID, username, email, display name, nicename, website, registration date), first and last name, nickname, the configured user data, credential numbers and roles. Every word has to match, each in any field, so "jan kowalski" works. Session tokens and other internal user meta stay out of the search.

## 1.5.3

### Changed
- **New account page layout.** The tabs no longer wrap: with enough room they are a side menu with icons and the account's name, on narrow pages a strip that scrolls sideways and keeps the open tab in view. Fields sit in two columns where they fit, here and on the registration form. The layout follows the room the page really has (container queries), because that depends on the theme's column and not on the screen.
- On Astra's narrow container (about 700px on any screen) the account page may be wider than the column, up to 1040px, from 900px of screen width.
- Tailwind-like look on every form: slate greys, indigo accent, white fields, smaller shadow. It is plain CSS, so nothing new is loaded. Colours stay overridable with the custom properties on `.wpum-ui`.
- **The active theme can no longer restyle the forms.** Every declaration in the stylesheet is `!important`, and a low-specificity reset clears what themes set on `div`, `span`, headings, labels and buttons through broad selectors. The Turnstile widget is left out of the reset.

## 1.5.2

### Added
- **Show/hide button (eye) on every password field**: login, registration, password reset, profile (current and new password, confirmation, account deletion) and the Turnstile secret key in the admin. The field goes back to hidden when the form is sent and when the deletion dialog closes, so the browser never stores a password as plain text. Password fields also switch off spell checking, which a browser can run on a remote service.

### Fixed
- **Nobody could log in through the plugin's form while Simple Cloudflare Turnstile protected the WordPress login** (visitors saw "Invalid username/email or password" with a correct password). Cause: `wp_signon()` runs that plugin's `authenticate` check, which reads the same `cf-turnstile-response` token the form had just verified. Cloudflare accepts a token once, so the second check failed, and it failed too when this plugin's Turnstile was off, because the form has no widget of the other plugin. The form now skips that check through the plugin's `cfturnstile_wp_login_checks` filter, for this form only; `wp-login.php` stays covered by it.
- A refused login writes the real WordPress error code to the debug log, since the visitor always gets the generic message.
- **Jetpack's leaked password check no longer ends on an empty login form.** When Jetpack Account Protection finds a leaked password it emails a code and sends the visitor to `wp-login.php?action=password-detection`, which this plugin redirected to the account page without the token. That action now stays with `wp-login.php`, but only while something handles it: for an unhandled action WordPress shows its own login form, which must stay replaced.

## 1.5.1

### Fixed
- **The emailed password-reset link led back to the "enter your email" form on sites with a page cache or CDN** (seen with LiteSpeed Cache and Hostinger's CDN). Cause: the link moved the key into a cookie and redirected to the plain page URL, but the cache answered that URL with its stored copy of the request form before PHP ran, whatever the cookie said. The page was only excluded from caches while the "new password" form was being shown, which never happened. The reset page now sends no-cache headers on every visit (`nocache_headers()`, `DONOTCACHEPAGE`, `litespeed_control_set_nocache`), and the link redirects to a one-off address (`?wpum_rp=...`), so a copy left in a cache cannot be served in its place.
- Coming back from the link without a usable cookie now shows the "invalid or expired link" notice instead of silently showing the request form again.
- **The registration page is no longer stored by page caches and CDNs either.** A cached copy keeps an old nonce, so a form older than about a day could be refused with "Your session has expired".

### Upgrade notes
- The link works right after the update. Purge the page cache and the CDN once anyway, so the stored copies of the reset and registration pages disappear (the registration page needs this: until then its old copy keeps being served).

## 1.5.0

### Added
- **Blocking `xmlrpc.php`** (Configuration -> XML-RPC, on by default). WordPress layer for every server: authenticated XML-RPC is refused, pingback methods and the `X-Pingback` header are removed. On Apache and LiteSpeed the plugin also writes a marked block into `.htaccess` (with `insert_with_markers()`, the rest of the file untouched) so the web server refuses the file before PHP starts.
- The block is **verified right after writing** (the site must still answer, `xmlrpc.php` must return 403) and taken out again if the site answers with an error, if `.htaccess` is ignored or if the check cannot run; the Configuration page reports the result (verified, not writable with the lines to add by hand, rejected, ignored, unknown). It is removed when the option is switched off and on deactivation, and re-applied in the background after an update.
- Emergency/opt-out constants: `WPUM_ALLOW_XMLRPC` (allow XML-RPC, removes the block) and `WPUM_NO_HTACCESS` (never touch `.htaccess`). Filter `wpum_xmlrpc_htaccess_rules`.
- Translations: 327 strings.

### Upgrade notes
- XML-RPC blocking is **on by default**. Apps that need it (the WordPress mobile app, Jetpack, some publishing tools) stop working: untick the option or define `WPUM_ALLOW_XMLRPC`.
- The private credentials folder already got its own `.htaccess` in 1.2.0; the README now explains how to verify it works (`/wp-content/uploads/wpum-private/index.php` must answer 403).

## 1.4.1

### Fixed
- **wp-login.php was not replaced for logging in on some sites** (the Configuration page said the account page was "not found, not published or without the shortcode" although it was fine). Cause: when another plugin (security, captcha, ...) asks for `wp_login_url()` before WordPress's `init`, the plugin's shortcodes are not registered yet, `has_shortcode()` answered "no" and that answer was remembered for the whole request. Only the login screen was affected, because `register_url` and `lostpassword_url` are not requested that early. The check no longer relies on registered shortcodes and nothing is remembered before `init`.

### Improved
- Page detection also finds the shortcode in reusable blocks (synced patterns) and Elementor data, ignores a draft that shares the slug with the live page, and - if the content does not show the shortcode - loads the page like a visitor and looks for the form (background WP-Cron check, cached; `wpum_page_content_sources` and `wpum_page_is_ready` filters for other setups).
- The Configuration page explains for each page why it is accepted or not (no such page, not published with its status, published but no form), links to the editor and has a *Check the pages again* button.
- Translations: 314 strings.

## 1.4.0

### Added
- **wp-login.php is replaced by the plugin's pages.** A new checkbox *Disable wp-login.php* in Configuration (on by default) sends the login, registration and "lost password" screens to the plugin's pages and makes `login_url`, `register_url` and `lostpassword_url` (and the wp-admin redirect, with a validated `redirect_to`) point there. POSTs to wp-login.php are not processed. Logout, the screens behind password-reset links, password-protected posts and WordPress's internal confirmations stay with WordPress; logged-in visitors are not redirected.
- Fail-safe: a form is replaced only if its page exists, is published and contains the shortcode; the Configuration page shows the status of each page. Emergency switch: `define( 'WPUM_KEEP_WP_LOGIN', true );`.
- **Configurable rate limits** (Configuration -> Rate limiting): a switch and, for nine limits, max attempts and window in minutes (clamped, junk falls back to defaults). *Maintenance -> Clear rate-limit counters* forgets all counters instantly (generation counter, works with object caches).
- **Automatic Cloudflare detection** replaces the manual "behind Cloudflare" checkbox: `CF-Connecting-IP` is trusted only from Cloudflare's edge ranges (built-in list) and from localhost (cloudflared, local reverse proxy), so it cannot be forged. The list is refreshed from cloudflare.com through WP-Cron when a request carries the header from an unknown address (at most once an hour; implausible answers are rejected), or on demand with a button.
- IPv6 visitors are rate-limited per /64; the Configuration page shows the IP the plugin sees for you and its source.
- New "flash" limit (30 per minute per IP): invalid form posts can no longer fill `wp_options` with notice records.
- Notices "You have been logged out." and "Your password has been reset."
- **Password reset on the plugin's own page:** the emailed link opens `/password-reset/` (key moved into an HttpOnly cookie and out of the URL), which shows a "Choose a new password" form (strength meter, confirmation, minimum length). The password is set with WordPress's `reset_password()` (so its hooks and the admin notification run), all sessions of the account are ended and the visitor is sent to the login page. WordPress's `rp`/`resetpass` links, including those in older emails, are forwarded to this page; a POST to the WordPress reset form is not processed. Expired, forged and reused links are refused; link attempts are rate limited ("reset_link", 10 per hour per IP).
- **Password reset email as an HTML template** with a plain-text part, written in the account's own language (user locale), in the same layout as the registration email. The layout is now one shared file, `templates/email.php` (was `email-activation.php`); filter `wpum_password_reset_email_strings`.
- **Logout:** `wp-login.php?action=logout` (admin bar, themes) logs out and redirects to the front page (`wpum_logout_redirect` filter), also for visitors who are not logged in. Without a valid nonce nobody is logged out: the account page opens the Logout tab with a confirmation ("info" notice style). The profile page's own Logout button ends on the front page as well.

### Changed
- The Turnstile page no longer has the "behind Cloudflare" checkbox (obsolete) and says that its wp-login.php options only matter while wp-login.php is not disabled.
- The login form honours a validated `redirect_to`.
- The registration email template file moved to `templates/email.php`; its `details` lines replace the separate `username`/`expiry`/`deletion` slots (the `wpum_activation_email_strings` filter still receives those keys).
- Translations: 306 strings.

### Upgrade notes
- Existing sites get the replacement switched **on** by default as soon as the account, registration and password-reset pages exist and contain the shortcodes. Admins then log in on the plugin's login page (also when they open `/wp-admin`). If you prefer WordPress's own screen, untick the option or define `WPUM_KEEP_WP_LOGIN`.
- If the site is behind Cloudflare, rate limits now count real visitors instead of Cloudflare's edge addresses.
- Password-reset links in emails sent before the update still work: they are forwarded to the plugin page.

## 1.3.0

### Added
- **Registration with email confirmation.** The form has a *Preferred language* field (English plus every language with a compiled `.mo`). The visitor receives a branded HTML email with a plain-text part in that language; the WordPress account is created only when the single-use link is opened. The language is stored as the user's locale.
- Unconfirmed registrations are kept in `{prefix}wpum_pending_registrations` (password already hashed, link key stored only as a SHA-256 hash) and removed by a **WP-Cron** event (`wpum_cron_purge_registrations`, twice daily). The number of days is set under *Configuration* (default 2, 1-30), is stated in the email and on the "check your email" page, and is part of the suggested privacy policy text. Expired links are refused immediately.
- Limits per email address and per IP for sign-ups and activation attempts; a newer sign-up for the same address replaces the older one.
- Privacy exporter/eraser also cover registrations awaiting confirmation.
- **Maintenance:** the notice about leftover `..._wpum_shooting_credentials_backup_...` tables now has a *Delete these tables* button (confirmation, nonce, `manage_options`; only names matching the exact old pattern are ever dropped), and *Configuration* has a Maintenance card with the same button.
- `wpum_t()`/`wpum_tn()` translate strings into a specific locale (emails use the recipient's language, not the page's).
- Translations: 265 strings, Polish and English catalogues recompiled.

### Fixed
- Regression in 1.2.0: logging in through the plugin's own login form, and the current-password check on the profile page, failed for passwords containing `'`, `"` or `\`. WordPress hashes and compares passwords in the slashed form in which they arrive in `$_POST`; the plugin now does the same everywhere (the 1.2.0 note about "unslashing" passwords was wrong, the 1.1.x code was consistent with core).

### Changed
- New database table (migration 1.2) and DB version 1.2.
- The "Account created" message is replaced by the confirmation flow ("check your email" page and activation notice).

## 1.2.0

### Security
- **Critical:** removing a role was a GET request without nonce or capability check and also ran for logged-out visitors (`admin-ajax.php?action=remove_role...`). Role changes now go through `admin-post.php` with per-user nonces and `promote_user` checks; the last administrator can no longer lose the role.
- Adding a role no longer hijacks every POST containing `user_id` and `role` (it broke the native *Edit user* screen).
- The "User Data" tab wrote **any** `wpumv_*` field to user meta. Only meta keys enabled by the admin are saved, and protected keys (capabilities, sessions, user level, screen options, `_*`) are always refused - in the Configuration page too.
- Credential PDFs moved from the public `uploads/shooting-credentials/` to a protected directory with random file names, content (`%PDF-`) and size validation, and an authenticated download endpoint. Existing files are migrated; orphaned old files are moved to `wpum-private/legacy/`.
- Login and password reset no longer reveal whether an account exists; rate limiting added for login, reset, registration and password checks.
- The custom CAPTCHA printed the answer in the page and in the AJAX response, so it protected nothing. It is replaced by Cloudflare Turnstile (login, registration, password reset and optionally `wp-login.php`).
- Email change, password change and account deletion require the current password. Account deletion no longer destroys the user's content (reassigned to an administrator) and cannot remove the last administrator.
- Configuration saves are whitelisted (no more `TypeError` on empty selection, no arbitrary columns such as `user_pass`).
- Credential types from the browser are validated; error messages are rendered as text.
- Nonce and AJAX data are no longer printed on every public page; assets load only where a shortcode is used.
- The plugin no longer creates copies of the credentials table on activation (they contained personal data).

### Added
- **Bulk deletion** of users in *User List* with confirmation dialog and content reassignment; the list is paginated.
- **Turnstile** submenu: keys, protected forms, appearance, fail-open, key test, widget preview, `wp-config.php` constants.
- Inline form messages (Post/Redirect/Get) instead of `wp_die` white pages; typed values are kept after errors.
- GDPR: credentials erased with the account, personal data exporter and eraser, privacy policy text.
- Configurable slugs for the account, registration and password reset pages.
- Remember-me checkbox on the login form (checked by default, as before).

### Changed
- One front-end stylesheet (`assets/css/wpum-frontend.css`) replaces four conflicting ones; calmer card design that overrides theme styles. Admin styles are loaded only on the plugin's pages (the old file altered every list table in wp-admin).
- The minimum password length from *Configuration* is now actually enforced.
- Form handlers run on `template_redirect` instead of `init`.
- Plugin header: `Requires PHP`, `Requires at least`, `Domain Path`, `License`.

### Fixed
- Changing the password from the profile page failed because the form did not send the other profile fields.
- The credentials form was submitted twice (scripts were loaded twice).
- Database migrations threw on every run because the update function returned nothing.

### Removed
- `captcha.php`, the CAPTCHA AJAX endpoints, old per-form CSS/JS, unused font and CAPTCHA working notes.

### Upgrade notes
- Pages with the shortcodes keep working. Enable Turnstile under **User Management -> Turnstile**.
- On nginx add the `deny` rule from the README for `wp-content/uploads/wpum-private/`.
- Older versions created tables named `..._wpum_shooting_credentials_backup_...`; the plugin shows a notice listing them with a *Delete these tables* button (also available under *Configuration -> Maintenance*).
