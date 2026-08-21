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
