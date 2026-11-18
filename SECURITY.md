# Security policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 0.1.x   | yes       |

## Reporting a vulnerability

Please do **not** open a public issue. Use GitHub's private vulnerability
reporting ("Security" tab, "Report a vulnerability") on
<https://github.com/saizul/wp-maintenance-lite>, or contact the maintainer
through <https://saizul.com>.

Include the affected version, a description and steps to reproduce. You can
expect an acknowledgement within 7 days. Please give us reasonable time to
release a fix before disclosing the issue.

A way to see the real site while maintenance mode is on without a bypass
role, an allowlisted IP or a valid preview link (for example a path, header
or cookie trick) is a security bug too; report it privately.

## How the plugin protects your site

- **Admin**: the settings screen requires `manage_options` (filterable with
  `maintenance_lite_capability`). The settings form uses the Settings API
  (`options.php` checks the capability and the nonce); "Regenerate preview
  link" has its own nonce, checked with `check_admin_referer()`.
- **Input**: every value goes through one sanitiser: booleans, a fixed list
  of modes, roles that exist on the site, validated IP addresses and CIDR
  ranges (at most 100), a clamped Retry-After, hex colours, http(s) logo URL,
  `sanitize_text_field()` for the headline and `wp_kses_post()` for the
  message.
- **Output**: everything printed on the settings screen and the maintenance
  page is escaped with `esc_html()`, `esc_attr()`, `esc_url()`,
  `esc_textarea()` or `wp_kses_post()`.
- **Preview link**: the 32-character token is compared in constant time. The
  cookie stores an HMAC of the token keyed with the site's auth salt, never
  the token, and is `HttpOnly` and `SameSite=Lax` (and `Secure` on HTTPS).
  Regenerating the token invalidates every link and cookie handed out before.
- **IP allowlist**: only `REMOTE_ADDR` is trusted. Proxy headers such as
  `X-Forwarded-For` are ignored unless you opt in with the
  `maintenance_lite_client_ip` filter.
- **Lock-out safe**: wp-login.php, wp-admin, admin-ajax.php and wp-cron.php
  are never blocked, and `define( 'WP_MAINTENANCE_LITE_DISABLE', true );`
  switches the page off without touching the database.
- **No remote requests**, no tracking, no fonts or scripts from other sites.
- **Uninstall** removes the `wp_maintenance_lite_settings` option on every
  site of a network.

Maintenance mode hides the front end; it is not access control for private
content. REST API data stays available to logged-in users, and files served
directly by the web server (uploads, static assets) are not blocked.
