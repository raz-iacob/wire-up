---
paths:
  - 'tests/Browser/**'
---

# Browser

## Browser tests can flake under composer test:unit:fresh
`composer test:unit:fresh` runs `--parallel`, and browser tests there sometimes die with `file_get_contents(vendor/pestphp/pest-plugin-browser/.temp/playwright-server.json): Failed to open stream` — several PagesEditTest / SettingsMenusTest cases at once.

It is a startup race on the shared Playwright server file, not a code failure: `php artisan test tests/Browser/PagesEditTest.php` on its own passes, and the very next `test:unit:fresh` then passes too.

So if only browser tests fail with that message, run one browser file serially to warm the server, then re-run. Do not go hunting in your diff — but do confirm `composer test` (the pre-commit gate) is green, since that is what actually blocks the commit.
