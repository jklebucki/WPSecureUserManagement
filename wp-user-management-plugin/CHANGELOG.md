# Changelog

## 1.2.0

### Security
- **Critical:** removing a role was a GET request without nonce or capability check and also ran for logged-out visitors (`admin-ajax.php?action=remove_role...`). Role changes now go through `admin-post.php` with per-user nonces and `promote_user` checks; the last administrator can no longer lose the role.
- Adding a role no longer hijacks every POST containing `user_id` and `role` (it broke the native *Edit user* screen).
- The "User Data" tab wrote **any** `wpumv_*` field to user meta. Only meta keys enabled by the admin are saved, and protected keys (capabilities, sessions, user level, screen options, `_*`) are always refused - in the Configuration page too.
- Credential PDFs moved from the public `uploads/shooting-credentials/` to a protected directory with random file names, content (`%PDF-`) and size validation, and an authenticated download endpoint. Existing files are migrated; orphaned old files are moved to `wpum-private/legacy/`.
- Login and password reset no longer reveal whether an account exists; rate limiting added for login, reset, registration and password checks.
- The custom CAPTCHA printed the answer in the page and in the AJAX response, so it protected nothing. It is replaced by Cloudflare Turnstile (login, registration, password reset and optionally `wp-login.php`).
- Passwords are handled consistently with WordPress core: the login form compared a slashed password (so passwords containing `'`, `"` or `\` could not log in) and the profile password change stored a slashed hash. Request data is now unslashed on input and slashed where WordPress APIs expect it.
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
- Older versions created tables named `..._wpum_shooting_credentials_backup_...`; the plugin shows a notice listing them. Drop them if you do not need them.
