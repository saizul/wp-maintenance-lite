# Contributing

Thanks for helping improve Maintenance Lite. Bug reports, fixes, tests,
documentation and translations are all welcome, and first-time contributors
are especially welcome: the code base is small and every part has tests.

By taking part you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Ways to help

- **Pick a beginner task** from the list below or from issues labelled
  [`good first issue`](https://github.com/saizul/wp-maintenance-lite/labels/good%20first%20issue).
  Comment on the issue first so two people do not work on the same thing.
- **Report a bug** with the "Bug report" template. Tell us who saw the page
  (logged out, which role, which IP type) and the HTTP status code
  (`curl -I https://example.org/`).
- **Test with your cache or CDN** and tell us what needed configuring. Those
  notes go into the README FAQ.
- **Translate** the plugin: `languages/wp-maintenance-lite.pot` is the
  template.

Security problems go to the private channel in [SECURITY.md](SECURITY.md),
not to public issues.

## Good first issues

Each task is small, has a clear "done" and touches one or two files. Tests go
in `tests/Unit/`.

1. **Add a "Copy" button next to the preview link.** In
   `src/Admin/SettingsPage.php::render()`, add a button that copies the URL
   with `navigator.clipboard.writeText()` (inline script, no build step), with
   a translatable "Copied" message. Keep the read-only input as a fallback.
2. **Show the current mode in the Plugins screen row.** Add a "Maintenance
   mode on" / "Coming soon mode on" note with the `plugin_row_meta` filter
   while the mode is active. Test that it only appears for this plugin's
   basename and only when active.
3. **Add `wp maintenance-lite preview-url [--regenerate]`.** A new subcommand
   in `src/Cli/Command.php` that prints the preview URL and, with
   `--regenerate`, creates a new token first. Add tests to
   `tests/Unit/CliTest.php` and document it in the README.
4. **Allow `robots.txt` and `favicon.ico` during maintenance.** Add both to
   the always-allowed rules in `src/Gate.php::allowed_path()` (path match,
   reason `path:robots` / `path:favicon`) with data-provider cases in
   `tests/Unit/GateTest.php`. Explain in the PR why it helps crawlers.
5. **Add a "Use my IP" button to the allowlist field.** In
   `SettingsPage::field()` (`ip_allowlist` case), add a small button that
   appends the address shown in the description to the textarea. Plain inline
   script; escape the IP with `esc_js()`.
6. **Add a `maintenance_lite_preview_cookie_lifetime` filter.** Replace the
   fixed `Token::LIFETIME` in `Frontend::handle_preview_link()` with a
   filtered value (clamped to at least one hour), test it in
   `tests/Unit/FrontendTest.php` and add it to the README hooks table.
7. **Dark/light text contrast hint.** When the background and text colours
   have a contrast ratio below 4.5:1, show a warning under the colour fields.
   Put the ratio calculation in a small pure helper with unit tests.
8. **Translate the plugin into your language** with Poedit or Loco Translate
   from `languages/wp-maintenance-lite.pot`, and open a PR with the `.po`
   file.

## Development setup

```bash
git clone https://github.com/saizul/wp-maintenance-lite.git
cd wp-maintenance-lite
composer install
composer test           # PHPUnit on WordPress stubs
composer phpcs          # WordPress Coding Standards + PHPCompatibilityWP
composer phpcbf         # fix what phpcs can fix automatically
composer lint           # php -l
composer make-pot       # regenerate the translations template
```

PHP 7.4 or newer is required. Dependencies are resolved for PHP 7.4
(`config.platform`), so the same lock file works across the CI matrix. There
is no JavaScript build.

To try the plugin in a real site, symlink or copy the folder into
`wp-content/plugins/` of a local WordPress install (wp-env, LocalWP, DDEV or
similar) and activate it. Check the response with
`curl -I http://localhost:8888/` in a private window or without cookies.

## Where things go

- `src/Gate.php` - **all** decision logic (who sees the page and with which
  status). It is a pure function of settings, a `Request` snapshot and the
  time; keep it that way so it stays easy to test.
- `src/Request.php` - reads `$_SERVER`, cookies and the current user into a
  snapshot. `src/Decision.php` - the result.
- `src/Sanitizer.php`, `src/Settings.php` - the single option and its
  sanitiser.
- `src/Ip.php`, `src/Token.php` - IP/CIDR matching and the preview link.
- `src/Frontend.php` - preview cookie, `template_redirect`, REST API, headers
  and rendering. `templates/maintenance.php` - the built-in page.
- `src/Admin/SettingsPage.php`, `src/Admin/AdminBar.php` - admin UI.
- `src/Cli/Command.php` - `wp maintenance-lite`.
- `tests/Stubs/` - light WordPress and WP-CLI stubs. Add a stub when you use a
  new WordPress function; keep it minimal and record calls in `WpStubs` so
  tests can assert them.

## Coding standards

- WordPress Coding Standards (`composer phpcs` must pass) and PHP 7.4
  compatibility: no union types, no `match`, no named arguments, no
  constructor promotion, and no `...$args` unpacking of arrays with string
  keys.
- Escape all output, sanitise all input, check capabilities and nonces.
- No remote requests, no tracking, no upsells, no new runtime dependencies.
- Translatable strings use the `wp-maintenance-lite` text domain.
- Keep pull requests focused. Update `CHANGELOG.md` under "Unreleased".

## Pull requests

1. Fork the repository and create a branch (`feature/copy-preview-link`,
   `fix/rest-retry-after`).
2. Make your change with tests.
3. Run `composer test`, `composer phpcs` and, if strings changed,
   `composer make-pot`, and commit the regenerated file.
4. Open the pull request and fill in the checklist.

CI must pass before a pull request is merged.

## Releases

Maintainers bump the version in `wp-maintenance-lite.php` (header and
`WP_MAINTENANCE_LITE_VERSION`), `src/Plugin.php` (`VERSION`), `readme.txt`
(`Stable tag`) and `CHANGELOG.md`, then publish a GitHub release tagged
`vX.Y.Z`. The release workflow checks that the versions match, builds
`wp-maintenance-lite.zip` and attaches it to the release.
