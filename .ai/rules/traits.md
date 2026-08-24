---
paths:
  - app/Traits/HasBlocks.php
---

# Traits

## Block content is sanitised on save, not on render
HasBlocks::updateBlocks() runs every block content string through BlockHtmlSanitizer::content(). That is the only choke point shared by the admin editor, the MCP tools and the AI assistant, so it is where the guard belongs — the editor's cleanPastedHtml is client-side only and Pages::blockRules() validates content as ['array'] with no HTML rules.

Site block views echo those fields with {!! !!} (66 places), so without this an agent-written <script> executed on the public page. Verified by mutation: drop the call and the raw script tag renders.

The sanitiser is removal-only and must stay that way — clean input comes back byte-identical, with class and id preserved. wire-up.dev's whole design language is wu-* classes written by MCP tools, and block anchors are ids, so a sanitiser that stripped or re-encoded attributes would break the dogfood site on its next save. Never make it entity-encode; repeated saves must be idempotent.
