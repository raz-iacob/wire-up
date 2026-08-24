---
paths:
  - 'resources/views/components/site/blocks/**'
---

# Blocks

## Guard every icon name before passing it to flux:icon
`<flux:icon :name="$name" />` throws "Flux component [icon.foo] does not exist" for an unknown name, which on a public block render is a 500 — not a silently missing icon.

Block content is only validated as `['array']` by `Pages::blockRules()`, so an MCP tool or the AI assistant can write any string into an icon field. The admin select constrains it; agents do not.

So resolve icon names against `config('menu.icons')` at render and fall back to '' — see the feature-cards block. Mutation-verified: dropping the `in_array` check turns a bad agent-written name into a 500 on the live page.

## Never cast block content to string with (string)
`(string) data_get($item, 'x', '')` reads as guarded but is not: block content is validated only as ['array'], so an agent or the assistant can put a list where a string belongs, and casting an array raises "Array to string conversion" — an ErrorException, so a 500 on the live page. data_get's default only covers a missing key, never a wrong type.

Use $block->plain('items.0.field', 'default') for plain scalar fields and $block->text('field') for translatable ones. Both go through Block::asString(), which returns the default for anything non-scalar.

This bit four places at once, found by rendering every block type with hostile content rather than by reading the views: Block::text() itself (so every heading/intro/body on every block), sponsors items.link and items.tier, and buttons items.variant. tests/Feature/Site/AgentWrittenBlockContentTest.php now sweeps every block type and every repeatable item field, so a new block is covered automatically — keep it passing rather than special-casing it.

Note withBlockDefaults() runs ONLY when the admin editor loads blocks. It never runs on save and never on the MCP path, so no view may assume a key exists or has the advertised type.
