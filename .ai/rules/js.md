---
paths:
  - resources/js/editor.js
---

# Js

## The rich-text editor strips classes — only MCP tools can write class hooks
Content written through the admin rich-text editor can never carry a `class` attribute. Pasting into the editor body runs `cleanPastedHtml` (PASTE_ALLOWED_TAGS = P, BR, UL, OL, LI, A, H1, H2, H3: `h4`-`h6` are lifted to `h3`, divs become paragraphs, everything else including `<strong>` is unwrapped to text, all attributes but `href` are dropped). Pasting into the source-view textarea is gentler but still round-trips through `setContent`, and TipTap drops attributes outside its schema.

So markup with class hooks can only be written by the MCP tools or the AI assistant, which set block content JSON directly. An owner working in the admin cannot reproduce it, and the hook dies silently the first time they edit the field. wire-up.dev used to be built this way (`wu-kicker` / `wu-h2` / `wu-prose` and a CSS icon set); it no longer is — that markup was replaced with block fields and the hooks below on 2026-08-24.

When designing CSS for site content, key off markup the app emits rather than classes in the content, so the admin can author it:

- `data-block="{type}"` on every block wrapper and `id="{anchor}"` when the block has an anchor (page-content.blade.php).
- `data-record="{content type key}"` on a record page root (pages/⚡record.blade.php). Do not use `main:has(section h1)` to mean "a record page" — pages carry an `h1` too whenever a hero heading uses one.
- The heading wrapper of any block is matchable as `[class*="--wire-heading-size"]`; its first child is line one and its last child is line two. Prefer `:first-child`/`:last-child` over tag names — the toolbar's style picker offers Text/H1/H2/H3, so the same line can come back as either a `<p>` or a heading.
- Body copy wrappers carry `.wire-prose` (7 of them, across rich-text, text-image, feature-card, accordion, pricing and location). That is where headings inside body text get their size — a heading FIELD is deliberately not `.wire-prose`, so a level chosen there is semantics only and the size stays `--wire-heading-size`.

Before writing CSS at all, check whether a block field already does it: feature-cards take an `icon` per item, photo takes `frame`, rich-text takes `width`/`align`, hero takes `width: container` and a gradient background, the footer `columns` layout builds columns from `heading` menu items, and the editor's badge tool emits `<span data-badge class="wire-badge">` — which is the supported way to author a pill/kicker.
