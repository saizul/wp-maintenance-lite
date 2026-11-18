# Maintenance Lite

[![CI](https://github.com/saizul/wp-maintenance-lite/actions/workflows/ci.yml/badge.svg)](https://github.com/saizul/wp-maintenance-lite/actions/workflows/ci.yml)
[![Release zip](https://github.com/saizul/wp-maintenance-lite/actions/workflows/release-zip.yml/badge.svg)](https://github.com/saizul/wp-maintenance-lite/actions/workflows/release-zip.yml)
![PHP](https://img.shields.io/badge/PHP-7.4%20to%208.4-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![Good first issues](https://img.shields.io/github/issues/saizul/wp-maintenance-lite/good%20first%20issue?label=good%20first%20issues)](https://github.com/saizul/wp-maintenance-lite/labels/good%20first%20issue)

A **lightweight, no-upsell maintenance and coming-soon mode** for WordPress
that does the HTTP part right: a real `503 Service Unavailable` with
`Retry-After` while you work (so search engines keep your rankings), or a
`200` coming-soon page with `noindex` before launch.

Plugin slug: `wp-maintenance-lite` - display name: *Maintenance Lite*.

- No tracking, no remote requests, no page builder, no upsells.
- One option row per site; nothing changes until you switch it on.
- You cannot lock yourself out: wp-login.php and wp-admin always work.

## Contents

- [Features](#features)
- [Screenshots](#screenshots)
- [Quick start](#quick-start)
- [Who sees the real site](#who-sees-the-real-site)
- [WP-CLI](#wp-cli)
- [Custom template](#custom-template)
- [Hooks for developers](#hooks-for-developers)
- [FAQ](#faq)
- [Development and testing](#development-and-testing)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [Security and license](#security-and-license)

## Features

- **Maintenance mode**: HTTP 503, `Retry-After` set to the seconds until your
  end time (or a fallback you choose), no-cache headers.
- **Coming-soon mode**: HTTP 200 with an optional `noindex` meta tag and
  `X-Robots-Tag` header.
- **Bypass** for logged-in users with the roles you choose, an **IP
  allowlist** (IPv4/IPv6 addresses and CIDR ranges) and a **secret preview
  link** that sets a 7-day cookie. One click regenerates the link and
  invalidates every old link and cookie.
- **Always allowed**: wp-login.php, wp-admin, admin-ajax.php, wp-cron.php,
  WP-CLI and the REST API for authenticated users. Anonymous REST requests get
  a 503 JSON error.
- **Schedule**: optional end time with a live countdown on the page, and an
  option to switch off automatically when it passes.
- **Customisable page**: headline, message (basic HTML), logo, background and
  text colours. Self-contained HTML, no external assets.
- **Theme override**: drop `maintenance-lite.php` into your theme.
- **Admin bar indicator** while the mode is on.
- **WP-CLI**: `wp maintenance-lite on | off | status`.
- Settings API, nonces, capability checks, strict sanitising, escaped
  output, translation ready, multisite-aware uninstall.
- Emergency switch: `define( 'WP_MAINTENANCE_LITE_DISABLE', true );`.

## Screenshots

Screenshots will be added with the first WordPress.org release. Planned:

1. Settings > Maintenance Lite: status, mode and access rules.
2. The built-in maintenance page with a countdown.
3. The admin bar indicator while maintenance mode is on.

Want to help? Take the screenshots on a fresh install and open a pull request
(see [Contributing](#contributing)).

## Quick start

Requirements: WordPress 6.0 or newer (tested up to 7.1), PHP 7.4 or newer.

1. Download `wp-maintenance-lite.zip` from the
   [latest release](https://github.com/saizul/wp-maintenance-lite/releases)
   and install it from **Plugins > Add New > Upload Plugin**, or clone this
   repository into `wp-content/plugins/wp-maintenance-lite`.
2. Activate **Maintenance Lite**. Nothing changes yet.
3. Go to **Settings > Maintenance Lite**, check **On**, choose
   **Maintenance** (503) or **Coming soon** (200) and save.
4. Open the site in a private window, or check the headers:

```sh
curl -I https://example.org/
# HTTP/1.1 503 Service Unavailable
# Retry-After: 3600
# Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private
```

5. Send the **secret preview link** (shown on the settings screen) to a
   client who should see the real site without an account.

## Who sees the real site

The rules are checked in this order (see `src/Gate.php`):

| Rule | Result |
| --- | --- |
| Mode off, or end time passed with "switch off automatically" | Real site |
| WP-CLI, wp-login.php, `/wp-admin`, admin-ajax.php, wp-cron.php | Real site |
| REST API request from an authenticated user | Real response |
| Path in `maintenance_lite_allowed_paths` | Real site |
| Logged in with a bypass role | Real site |
| Client IP on the allowlist | Real site |
| Valid preview link or preview cookie | Real site |
| Everyone else | Maintenance page (503) or coming-soon page (200); REST: 503 error |

Path rules match whole segments: `/my-login` allows `/my-login` and
`/my-login/x`, not `/my-login-page`.

## WP-CLI

```sh
# Switch on (keeps your saved mode, texts and rules).
wp maintenance-lite on

# Coming-soon mode.
wp maintenance-lite on --mode=coming_soon

# Expected end in the site time zone; switch off automatically at that time.
wp maintenance-lite on --until="2026-12-01 09:30" --auto-off

# Clear the end time.
wp maintenance-lite on --until=none

# Switch off.
wp maintenance-lite off

# Status for humans or scripts.
wp maintenance-lite status
wp maintenance-lite status --format=json
```

`status --format=json` returns `active`, `enabled`, `mode`, `http_status`,
`end_time` (ISO 8601), `auto_off`, `bypass_roles`, `ip_allowlist`,
`preview_url` and `disabled_by_constant`. Typical deploy script:

```sh
wp maintenance-lite on --until="$(date -d '+20 min' '+%Y-%m-%d %H:%M')"
# ... deploy, migrate ...
wp maintenance-lite off
```

## Custom template

Copy `templates/maintenance.php` from the plugin to your (child) theme as
`maintenance-lite.php`. The plugin still sends the status code and headers;
your file only prints the HTML. It receives a `$maintenance_lite` array:

| Key | Content |
| --- | --- |
| `mode` | `maintenance` or `coming_soon` |
| `status` | `503` or `200` |
| `site_name` | Site title |
| `headline`, `message` | Text from the settings (message already filtered with `wp_kses_post()`), or the default text |
| `logo_url` | Logo URL or empty string |
| `background_color`, `text_color` | Hex colours |
| `noindex` | Whether to print a `noindex` robots meta tag |
| `end_time`, `end_label` | Unix time of the expected end (0 if none) and a formatted date |

Escape everything you print, as the built-in template does.

## Hooks for developers

| Hook | Type | Arguments | Use it to |
| --- | --- | --- | --- |
| `maintenance_lite_should_block` | filter | `bool $block`, `Decision $decision`, `Request $request` | Override the final decision (for example allow a health-check user agent). |
| `maintenance_lite_allowed_paths` | filter | `string[] $paths` | Never block extra paths, such as a custom login URL or payment webhooks. |
| `maintenance_lite_client_ip` | filter | `string $ip` | Read the visitor IP from a trusted proxy header. |
| `maintenance_lite_retry_after` | filter | `int $seconds` | Change the `Retry-After` value (0 removes the header). |
| `maintenance_lite_template` | filter | `string $path` | Use a template file from a plugin. |
| `maintenance_lite_view` | filter | `array $view`, `Decision $decision` | Change the data passed to the template. |
| `maintenance_lite_capability` | filter | `string $capability` | Who can change settings and see the admin bar notice (`manage_options`). |
| `maintenance_lite_before_render` | action | `Decision $decision` | Log or send extra headers before the page is printed. |

Examples (in a must-use plugin or your theme's `functions.php`):

```php
// Keep payment webhooks and a custom login URL working.
add_filter( 'maintenance_lite_allowed_paths', function ( $paths ) {
	$paths[] = '/wc-api';
	$paths[] = '/my-login';
	return $paths;
} );

// Behind Cloudflare: trust CF-Connecting-IP (check the request came from Cloudflare first).
add_filter( 'maintenance_lite_client_ip', function ( $ip ) {
	return isset( $_SERVER['HTTP_CF_CONNECTING_IP'] )
		? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) )
		: $ip;
} );

// Let an uptime monitor see the real site.
add_filter( 'maintenance_lite_should_block', function ( $block, $decision, $request ) {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return $block && false === strpos( $agent, 'MyUptimeBot' );
}, 10, 3 );
```

## FAQ

**Is a 503 really better than a 200 page for SEO?**
Yes, for temporary maintenance. A 200 page tells crawlers the new content is
your page; a 503 with `Retry-After` tells them to come back later and keep
what they have. Use coming-soon mode (200, `noindex`) only before launch.

**I am logged in but see the maintenance page.**
Your role is not in "Roles that see the site". wp-admin always works, so
open Settings > Maintenance Lite and add your role.

**I am locked out.**
wp-login.php and wp-admin are never blocked. If something else is wrong, run
`wp maintenance-lite off` or add `define( 'WP_MAINTENANCE_LITE_DISABLE', true );`
to `wp-config.php`.

**My cache or CDN shows the wrong page.**
Purge the cache after switching. The maintenance page is sent with no-cache
headers. If you use the preview link with a page cache, configure the cache
to bypass requests with the `wp_maintenance_lite_preview` cookie (logged-in
users are usually bypassed already).

**The IP allowlist does not work behind a proxy.**
Only `REMOTE_ADDR` is trusted, because headers like `X-Forwarded-For` can be
forged. Use the `maintenance_lite_client_ip` filter (example above).

**Is the REST API blocked?**
For anonymous visitors, yes (503 JSON error), unless their IP or preview
cookie allows them. Authenticated requests (cookie with nonce, application
passwords) work, so the block editor keeps working.

**Does it work on multisite?**
Yes. Each site has its own settings; uninstall cleans up every site.

**Does it hide uploads and static files?**
No. Files served directly by the web server are not handled by WordPress.
Maintenance mode is not access control for private content.

## Development and testing

```sh
composer install
composer test           # PHPUnit on light WordPress stubs, no database needed
composer phpcs          # WordPress Coding Standards and PHP 7.4+ compatibility
composer lint           # php -l on every file
composer make-pot       # regenerate languages/wp-maintenance-lite.pot
```

Project layout:

```
wp-maintenance-lite.php    Plugin header and autoloader
uninstall.php              Deletes the option on every site
src/Plugin.php             Wiring, filters, capability, preview URL
src/Gate.php               Pure decision logic (who sees the page, status code)
src/Request.php            Snapshot of the current request
src/Decision.php           Result of the gate
src/Settings.php           The single option
src/Sanitizer.php          Settings sanitiser
src/Ip.php                 IPv4/IPv6 and CIDR matching
src/Token.php              Preview token and cookie
src/Frontend.php           Preview cookie, template_redirect, REST API, rendering
src/Admin/SettingsPage.php Settings API screen
src/Admin/AdminBar.php     Admin bar indicator
src/Cli/Command.php        wp maintenance-lite
templates/maintenance.php  Built-in page
tests/                     PHPUnit tests and WordPress/WP-CLI stubs
bin/make-pot.php           POT generator
```

CI runs on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4: `composer validate`, `php -l`,
phpcs, PHPUnit and the POT check. Publishing a GitHub release builds
`wp-maintenance-lite.zip` (files in `.distignore` left out), smoke-tests it
and attaches it to the release.

Tests run against stubs, not a real WordPress install: they cover the
decision rules, sanitising, headers, the template, the settings screen, the
CLI and uninstall. Test on a staging site before relying on it.

## Roadmap

Ideas, roughly in order. Items marked **good first issue** are small and
well scoped; see [CONTRIBUTING.md](CONTRIBUTING.md#good-first-issues) for
details.

- "Copy" button for the preview link - **good first issue**
- `wp maintenance-lite preview-url [--regenerate]` - **good first issue**
- Allow `robots.txt` and `favicon.ico` during maintenance - **good first issue**
- "Use my IP" button for the allowlist - **good first issue**
- Filter for the preview cookie lifetime - **good first issue**
- Colour contrast warning on the settings screen - **good first issue**
- Status note in the Plugins screen row - **good first issue**
- Media Library picker for the logo
- Network-wide switch for multisite (`wp maintenance-lite on --network`)
- Scheduled start time, not only an end time
- Integration tests against a real WordPress install (wp-env) in CI
- Screenshots and a WordPress.org release

## Contributing

Contributions of every size are welcome: bug reports, tests, docs,
translations and code. Start with [CONTRIBUTING.md](CONTRIBUTING.md), which
has the setup, the coding standards and a list of beginner tasks, and please
follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Security and license

- [SECURITY.md](SECURITY.md): please report vulnerabilities privately.
- [CHANGELOG.md](CHANGELOG.md).

Maintenance Lite is free software under the
[GPL-2.0-or-later](LICENSE). Copyright (c) 2026 Saizul Amin
([saizul.com](https://saizul.com)).
