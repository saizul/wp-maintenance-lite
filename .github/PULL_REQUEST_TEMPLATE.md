## What does this change?

<!-- One or two sentences. Link the issue: "Closes #123". -->

## Type

- [ ] Bug fix
- [ ] Access rules (gate, bypass, allowlist, preview link)
- [ ] Settings screen, maintenance page, WP-CLI or hooks
- [ ] Documentation or translations
- [ ] Tests or tooling

## Checklist

- [ ] Decision logic stays in `src/Gate.php` and has tests in `tests/Unit/GateTest.php`.
- [ ] New settings are sanitised in `src/Sanitizer.php` and tested.
- [ ] Output is escaped; forms check a capability and a nonce.
- [ ] No remote requests, no tracking, no new runtime dependencies.
- [ ] `composer test` and `composer phpcs` pass.
- [ ] `composer make-pot` run and `languages/wp-maintenance-lite.pot` committed (if strings changed).
- [ ] `CHANGELOG.md` updated under "Unreleased".

## How did you test it on a real site?

<!-- WordPress and PHP versions, theme, logged in / logged out, curl -I output if headers changed. -->
