---
paths:
  - resources/js/editor.js
---

# Js

## The rich-text editor strips classes — only MCP tools can write class hooks
Content written through the admin rich-text editor can never carry a `class` attribute. Pasting into the editor body runs `cleanPastedHtml` (PASTE_ALLOWED_TAGS = P, BR, UL, OL, LI, A: headings and divs become paragraphs, everything else including `<strong>` is unwrapped to text, all attributes but `href` are dropped). Pasting into the source-view textarea is gentler but still round-trips through `setContent`, and TipTap drops attributes outside its schema.

So markup with class hooks — the wire-up.dev site's `wu-eyebrow` / `wu-h2` / `wu-prose` etc. — can only be written by the MCP tools or the AI assistant, which set block content JSON directly. An owner working in the admin cannot reproduce it.

When designing CSS for site content, key off structure instead of classes so the admin can author it: block wrappers carry `id="{anchor}"` (page-content.blade.php), and the rich-text heading wrapper is matchable as `[class*="--wire-heading-size"]`, whose first child is line one and last child is line two. Use `:first-child`/`:last-child`, not tag names — the editor may downgrade `<h2>` to `<p>`.
