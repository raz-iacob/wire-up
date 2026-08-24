---
paths:
  - app/Traits/HasBlocks.php
  - app/Traits/HasPublishing.php
---

# Traits

## Block content is sanitised on save, not on render
HasBlocks::updateBlocks() runs every block content string through BlockHtmlSanitizer::content(). That is the only choke point shared by the admin editor, the MCP tools and the AI assistant, so it is where the guard belongs — the editor's cleanPastedHtml is client-side only and Pages::blockRules() validates content as ['array'] with no HTML rules.

Site block views echo those fields with {!! !!} (66 places), so without this an agent-written <script> executed on the public page. Verified by mutation: drop the call and the raw script tag renders.

The sanitiser is removal-only and must stay that way — clean input comes back byte-identical, with class and id preserved. wire-up.dev's whole design language is wu-* classes written by MCP tools, and block anchors are ids, so a sanitiser that stripped or re-encoded attributes would break the dogfood site on its next save. Never make it entity-encode; repeated saves must be idempotent.

## A saved-hook job runs inline on the sync queue
bootHasPublishing() dispatches GenerateOgImage on every save of live content. On QUEUE_CONNECTION=sync that job runs *inside* the save, and it launches a headless browser — the test suite went from 38s to 398s the moment the hook landed, because every test that publishes a page shelled out to Playwright for real.

So the hook is gated on config('wireup.og_images'), and phpunit.xml sets WIREUP_OG_IMAGES=false. Tests that want it on set config('wireup.og_images', true) in a beforeEach. Keep it that way; do not "fix" a slow suite by faking the queue in individual tests.

Two related traps:
- phpunit.xml <env> values arrive as strings, so `env('X', true)` returns "false" and config()->boolean() throws "must be a boolean, string given" — 928 tests failed on that. The config casts with filter_var(..., FILTER_VALIDATE_BOOLEAN).
- OgImageService caches by a fingerprint of the title plus the design tokens, so generate() is a no-op when nothing changed. That is what makes a per-save dispatch affordable. If you add a card field, include it in the fingerprint or stale cards will never rebuild.
