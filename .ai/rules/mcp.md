---
paths:
  - 'app/Mcp/**'
---

# Mcp

## Block content shapes that bite when writing blocks
Three shapes are easy to get wrong through `update-page-blocks` / `create-record`:

- Rich fields (`heading`, `body`, `intro`, `subheading`, contact-form `description`/`successMessage`) render raw HTML, so class hooks survive. But `stats` value/label, `feature-cards` item titles, menu labels and search placeholders are `{{ }}`-escaped — an HTML entity there renders literally. Use real characters (— ’) in those.
- `collection.fields` is a list of plain field-key strings (`["audience","reading_time"]`), not the field objects `create-content-type` takes.
- `collection.source` only accepts `latest|manual|category|related` (see `RecordCollectionQuery`); anything else silently falls back to `latest`. Hand-picked lists need `source: "manual"` plus `recordIds`. `related` resolves against the record the block sits on — it renders nothing on a page, and `recordTypeId` is optional there.

All three are now documented in the `block-types` resource under `conventions` (`escapedFields`, `collectionSource`, `collectionFields`), so an agent that reads the catalog first will not hit them. Keep that resource in step when block content shapes change — it is the only thing agents read before writing blocks.

## Validated block order is not input order — normalise it
`Pages::blockRules()` declares `blocks.*.id` before `blocks.*.type`, and Laravel's `validated()` rebuilds the array attribute by attribute. Items with no `id` key are skipped on the `id` pass and only created on the `type` pass, so they land at the END of the validated array. Sending hero(id) → divider(no id) → stats(id) validates as hero → stats → divider, and `HasBlocks::updateBlocks()` assigns `position` from `array_values()` of that.

Fixed by `Pages::orderedBlocks()`, which `ksort()`s on the original input keys. Every tool that feeds validated blocks into `updateBlocks` or an action MUST pass them through it — `create-page`, `update-page-blocks`, `create-record` and `update-record` all do.

Do NOT move the `ksort` into `HasBlocks::updateBlocks()` instead. The admin editor keys `$this->blocks` by block id and `HasBlockBuilder::reorderBlocks()` rebuilds that array in the user's chosen order while keeping the id keys, so sorting by key there would re-sort by block id and silently undo every manual drag-reorder.
