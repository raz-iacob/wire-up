---
paths:
  - 'resources/views/layouts/**'
---

# Layouts

## Theme state lives in localStorage and survives a DB wipe
Two browser keys hold the colour scheme, and neither is touched by `config:clear`, `optimize:clear`, or wiping the database:

- `flux.appearance` — the admin (written by `@fluxAppearance`, defaults to `'system'` when absent)
- `wireup-scheme` — the public site (written by the inline script in `<x-site.head>`, only emitted when a dark palette is configured)

The admin additionally **re-seeds** `flux.appearance` from `users.metadata.appearance` on *every* admin page load (`layouts/admin.blade.php`), so a stored user preference keeps overwriting whatever the browser had — clearing localStorage alone will not stick for that user.

So "the theme is wrong on a fresh install" is usually stale browser state, not a bug. Before investigating, clear both keys and re-check; emulate the system preference rather than trusting your own machine. Verified 2026-08-23: with both keys cleared, `/welcome` and `/login` follow `prefers-color-scheme` correctly in both directions.

Do not reintroduce a hardcoded `class="dark"` on `<html>` in any layout (removed from the admin in `25dac0a`) — the server cannot know the client's preference, and it makes the first paint always dark. `AccountAppearanceTest` pins this.
