---
paths:
  - 'app/Mcp/**'
---

# Mcp

## Block content shapes the MCP tools do not document
Building blocks through `update-page-blocks` / `create-record` hits three undocumented shapes:

- Rich fields (`heading`, `body`, `intro`, `subheading`, contact-form `description`/`successMessage`) render raw HTML, so class hooks survive. But `stats` value/label, `feature-cards` item titles, menu labels and search placeholders are `{{ }}`-escaped — an HTML entity there renders literally. Use real characters (— ’) in those.
- `collection.fields` is a list of plain field-key strings (`["audience","reading_time"]`), not the field objects `create-content-type` takes.
- `collection.source` only accepts `latest|manual|category` (see `RecordCollectionQuery`); anything else silently falls back to `latest`. Hand-picked lists need `source: "manual"` plus `recordIds`.

The `block-types` resource states none of this; consider adding it there.

## A block without an id gets reordered to the end (bug + workaround)
`update-page-blocks` and `update-record` silently move any block you insert mid-list to the bottom of the page.

Cause: `Pages::blockRules()` declares `blocks.*.id` before `blocks.*.type`, and Laravel's `validated()` rebuilds the array attribute by attribute. Items that have no `id` key are skipped on the `id` pass and only get created on the `type` pass, so they end up appended to the validated array. `HasBlocks::updateBlocks()` then assigns `position` from `array_values()` of that reordered array. Proven: sending hero(id 8) → photo(no id) → stats(id 9) validates as hero → stats → photo.

Workaround until it is fixed: give every item an id — real ids for existing blocks, `"id": "new-1"` for new ones (`updateBlocks` treats a `new-` prefix as a create).

Real fix: `ksort()` the validated blocks before use, or list the `id` rule after `type`/`content`.
