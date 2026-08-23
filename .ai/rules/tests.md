---
paths:
  - 'tests/**'
---

# Tests

## SQLite ignores foreign-key pragmas inside a transaction
Schema::withoutForeignKeyConstraints() is a silent no-op on SQLite whenever a transaction is already open — and LazilyRefreshDatabase keeps one open for every test. MySQL honours SET FOREIGN_KEY_CHECKS=0 either way, so code that relies on suppressing FKs passes on dev and fails only under test, or vice versa.

Don't suppress foreign keys to make bulk writes work. Order them instead: delete children before parents, insert parents before children. Malformed data then fails loudly rather than importing broken rows. See App\Services\SiteImporter, which deletes in reverse SiteBundle::TABLES order and inserts in forward order.

## Refresh the TIA baseline before trusting a coverage number
`composer test:unit` runs pest with --tia --coverage. TIA cannot record while a coverage report is active, so it silently reuses whatever baseline is on disk. After editing any file that baseline is stale and coverage under-reports — you get phantom uncovered lines, often with a blank or missing line number as the tell.

Run `composer test:unit:fresh` to get a true reading. This also matters before committing: the pre-commit hook runs `composer test`, so a stale baseline can block an otherwise clean commit. Priming with :fresh first avoids it.

## A cached config lets a parallel run drop your dev database
`bootstrap/cache/config.php` makes Laravel skip both `config/*.php` and phpunit.xml's `<env>`, so `DB_CONNECTION=sqlite` is ignored and the suite points at the real dev database. It has wiped the dev DB twice (2026-07-13, 2026-08-23 — the second time losing the local wire-up.dev content and both export bundles).

The per-test driver check in `Tests\TestCase::assertTestDatabaseIsIsolated()` cannot save you under `--parallel`: the parallel harness provisions and migrates each worker's database before any TestCase exists, so by the time the guard throws, every table is already gone. That is exactly what happened — you see the guard's error AND an empty database.

`tests/Pest.php` therefore refuses at bootstrap if the cached config file exists, which runs in every worker before the app boots. Do not weaken or move that check below the `pest()->extend()` call. If a run aborts with it, run `php artisan config:clear` (or `optimize:clear`) — do not treat it as noise, and check whether the dev DB survived before continuing.

Nothing in normal development needs a cached config; if something keeps creating one, find that instead of clearing it repeatedly.
