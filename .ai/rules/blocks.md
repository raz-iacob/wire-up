---
paths:
  - 'resources/views/components/site/blocks/**'
---

# Blocks

## Guard every icon name before passing it to flux:icon
`<flux:icon :name="$name" />` throws "Flux component [icon.foo] does not exist" for an unknown name, which on a public block render is a 500 — not a silently missing icon.

Block content is only validated as `['array']` by `Pages::blockRules()`, so an MCP tool or the AI assistant can write any string into an icon field. The admin select constrains it; agents do not.

So resolve icon names against `config('menu.icons')` at render and fall back to '' — see the feature-cards block. Mutation-verified: dropping the `in_array` check turns a bad agent-written name into a 500 on the live page.
