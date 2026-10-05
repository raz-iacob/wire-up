---
paths:
  - 'tests/**'
---

# Tests

## SQLite ignores foreign-key pragmas inside a transaction
Schema::withoutForeignKeyConstraints() is a silent no-op on SQLite whenever a transaction is already open — and LazilyRefreshDatabase keeps one open for every test. MySQL honours SET FOREIGN_KEY_CHECKS=0 either way, so code that relies on suppressing FKs passes on dev and fails only under test, or vice versa.

Don't suppress foreign keys to make bulk writes work. Order them instead: delete children before parents, insert parents before children. Malformed data then fails loudly rather than importing broken rows. See App\Services\SiteImporter, which deletes in reverse SiteBundle::TABLES order and inserts in forward order.

## Re-anchor the TIA baseline in its own run, or coverage lies
`composer test:unit` runs pest with `--tia --coverage`. Pest skips TIA whenever a coverage report is active — it says so on stdout — and silently reuses whatever baseline is on disk. After you edit a file, the baseline has no edges for the new lines, so exactly the files you touched report as partly uncovered while everything else sits at 100%. That shape is the tell: a handful of just-edited files at 50-70%, not scattered noise.

Adding `--fresh` to the same command cannot fix it, because TIA is skipped before `--fresh` means anything. The re-anchor has to be its own run, with no `--coverage`:

    vendor/bin/pest --tia --fresh --parallel

`composer test:unit:fresh` now does exactly that and then runs the coverage check. Run it after editing anything and before committing, because the pre-commit hook runs `composer test` and a stale baseline blocks an otherwise clean commit.

To confirm a number is real rather than a TIA artifact, measure without TIA at all — `vendor/bin/pest --parallel --coverage`. If that says 100% and the gate does not, the baseline is stale, not your code.

## A cached config lets a parallel run drop your dev database
`bootstrap/cache/config.php` makes Laravel skip both `config/*.php` and phpunit.xml's `<env>`, so `DB_CONNECTION=sqlite` is ignored and the suite points at the real dev database. It has wiped the dev DB twice (2026-07-13, 2026-08-23 — the second time losing the local wire-up.dev content and both export bundles).

The per-test driver check in `Tests\TestCase::assertTestDatabaseIsIsolated()` cannot save you under `--parallel`: the parallel harness provisions and migrates each worker's database before any TestCase exists, so by the time the guard throws, every table is already gone. That is exactly what happened — you see the guard's error AND an empty database.

`tests/Pest.php` therefore refuses at bootstrap if the cached config file exists, which runs in every worker before the app boots. Do not weaken or move that check below the `pest()->extend()` call. If a run aborts with it, run `php artisan config:clear` (or `optimize:clear`) — do not treat it as noise, and check whether the dev DB survived before continuing.

Nothing in normal development needs a cached config; if something keeps creating one, find that instead of clearing it repeatedly.
