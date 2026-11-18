# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-18

### Added

- Maintenance mode that answers with **HTTP 503 and a Retry-After header**
  (seconds until the end time, or a configurable fallback), so search engines
  keep rankings and come back later.
- Coming-soon mode that answers with HTTP 200, with an optional `noindex`
  meta tag and `X-Robots-Tag` header.
- Bypass rules: logged-in users with chosen roles, an IP allowlist with IPv4
  and IPv6 addresses and CIDR ranges, and a secret preview link that sets a
  7-day cookie (an HMAC of the token, not the token itself). A "Regenerate
  preview link" button invalidates old links and cookies.
- Always allowed: wp-login.php, wp-admin, admin-ajax.php, wp-cron.php,
  WP-CLI and the REST API for authenticated users. Anonymous REST requests get
  a 503 `WP_Error`.
- Optional end time with a live countdown on the page and an option to switch
  maintenance mode off automatically.
- Customisable headline, message (basic HTML), logo URL, background and text
  colours; built-in page without external requests.
- Theme template override with `maintenance-lite.php` in the (child) theme.
- Admin bar indicator while the mode is active.
- Settings > Maintenance Lite screen built on the Settings API with nonces,
  capability checks, sanitising and escaping.
- WP-CLI command `wp maintenance-lite on|off|status` with `--mode`, `--until`,
  `--auto-off` and `--format=json`.
- Filters: `maintenance_lite_should_block`, `maintenance_lite_allowed_paths`,
  `maintenance_lite_client_ip`, `maintenance_lite_retry_after`,
  `maintenance_lite_template`, `maintenance_lite_view`,
  `maintenance_lite_capability`; action `maintenance_lite_before_render`.
- Emergency switch `WP_MAINTENANCE_LITE_DISABLE` for wp-config.php.
- Translation template, uninstall clean-up on every site of a network.
- PHPUnit tests on WordPress stubs, phpcs (WordPress Coding Standards), CI on
  PHP 7.4 to 8.4, POT check and a release workflow that builds the plugin zip.

[Unreleased]: https://github.com/saizul/wp-maintenance-lite/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/saizul/wp-maintenance-lite/releases/tag/v0.1.0
