# Changelog

Release notes for Wire-Up. Each release is a `## vX.Y.Z` section; the updater shows the sections newer than the installed version on **Settings → Updates**.

## Unreleased

- Fixed the admin sometimes loading with its interactive parts dead on a first visit — most visibly Settings → Design opening with an empty "Edit image crop" box over the page.
- The admin no longer assumes dark before its stylesheet decides, so the very first paint matches your device setting rather than briefly flashing dark. A theme chosen on your account still wins.
- Validation errors, sign-in messages and password-reset notices now read as real sentences. They were showing internal keys such as "validation.required" in the admin, the terminal and to connected agents.
- `wireup:admin` now takes `--name`, `--email` and `--password`, so an admin can be created from a script or a fresh-install routine without answering the prompts. Any detail you leave out is still asked for.
- Settings → Translations gains a "Form messages" group, so a site in another language can translate the wording of form errors — "is required", "must be a valid email address" and the rest — instead of being stuck with English.
- The admin stays usable while an update runs: Settings → Updates now shows live progress instead of the "Down for maintenance" page.
- Import files that are already on the server into the media library: a new `wireup:media:import` command for the terminal, and an `upload-media` tool so the AI assistant and other agents can do it too. Both handle SVG, HEIC and video, which importing from a URL could not.
- The AI assistant and other agents can now build a sidebar: a new `create-menu` tool makes menus beyond the header and footer, and `update-page` sets a page's layout — hiding the header or footer, a background colour or image, per-page CSS, and which sidebar menus appear beside the content.
- Agents and the AI assistant can now set the dark-mode logos, the favicon and the header light/dark toggle through `update-design` — previously admin-only.
- Agents and the AI assistant can create and list categories, so records can be grouped and a collection block can filter by category without anyone hand-picking ids.
- The block-types catalogue agents read now spells out which fields render escaped rather than as HTML, and the exact shapes the collection block accepts — three things that previously cost a debug cycle each.
- Agents and the AI assistant can delete pages, records and media rather than only drafting them — the assistant asks you to approve each deletion first. The homepage is protected, and a file still used anywhere is refused with a list of what uses it.
- Agents and the AI assistant can screenshot a published page, record or path at desktop, tablet or mobile size with the new `render-page` tool, so they can see their work instead of guessing. Needs a headless browser on the server; the error explains how to install one.
- Feature cards can now show an icon instead of an image — a choice of 48 built-in icons per card, sized by the block's image height and coloured to match the card text. The block always promised "an image or icon"; only the image half existed.

## v0.1.1 — 2026-08-22

- Test buttons on Settings → Integrations for Slack, e-mail and the AI assistant, checking the credentials you have typed before you save them.
- The Settings group in the admin sidebar now stays open while you move between pages, until you close it.
- A new block added in the middle of a page or record through the AI assistant or an MCP tool now stays where you put it, instead of silently jumping to the bottom.
- Site-wide custom CSS no longer applies to the login and other account pages, where it could make the form unreadable and lock you out of your own site.
- A content type's URL prefix may now match an existing page's web address, so that page can act as the landing page the breadcrumb links to.
- Menu item badges now actually appear on the site, in the colour you pick, and can be set on the header and footer menus rather than only custom ones.
- The columns footer layout now builds real grouped columns from group headings in the footer menu, which that menu can now contain.
- Bullet and numbered lists now render as lists in feature card and text-and-image bodies, instead of running together on one line.
- Rich text blocks gain a "Narrow (left)" width that keeps the text column on the same left edge as full-width blocks; the existing narrow option is now labelled "Narrow (centred)".
- Animated GIFs keep their animation instead of being flattened to a still image. They are served at their original size, so scale them before uploading.

## v0.1.0 — 2026-08-21

- Initial release.
- Pages with a block-based content editor: hero, text+image, gallery, video, audio, photo, feature cards, testimonials, sponsors, team, pricing, stats, accordion, location, contact form, collection, search, breadcrumb, code, buttons, downloads, rich text, divider and spacer blocks.
- Live page preview in desktop, tablet and mobile viewports, per-page layout overrides (background, chrome, custom CSS) and per-block spacing.
- Records with admin-defined content types: field blueprint builder, presets, money and boolean field types, polymorphic categories, per-type landing layouts, duplication and public detail pages with gallery lightbox.
- Media library: uploads with separate desktop/mobile crops, SVG sanitising, HEIC/HEIF conversion to JPEG, audio/video previews with duration, Pexels search and import, usage-aware delete guard, signed image URLs with a size-capped transform cache.
- Design settings: colour tokens with accent, light/dark schemes with per-theme logos, a 45-font catalogue plus custom Google fonts, content width, border and divider controls, site-wide custom CSS, and a live preview with a dark-scheme toggle.
- Named menus with sidebar regions, anchor links with smooth scrolling, icon-only header links with Lucide icons, a header language switcher and a responsive mobile menu.
- Multi-language support: per-locale page and record publishing, owner-editable interface translations, and a database translation loader.
- Users, roles and permissions: database-backed roles with per-content-type and per-action abilities, a roles builder, admin invitations, and two-factor authentication for staff and members.
- Member accounts: a `/account` page, an account menu-item type, members-only visibility for pages and records, and a configurable signups toggle with selectable auth layouts.
- Admin dashboard with KPIs, content breakdown, recent activity, inbox and online visitor counts.
- Inbox for contact-form submissions, with e-mail and Slack notification channels and spam protection.
- Analytics page backed by the GA4 Data API: KPIs, chart, countries and top pages.
- Integrations as connectable cards: Pexels, Google Analytics, Google Maps, Slack, SMTP e-mail with provider presets, and the AI assistant.
- AI assistant: an in-admin chat agent (Claude, OpenAI or Gemini) that builds and edits the site, with streaming, publish confirmation, and SSRF and rate-limit guards.
- MCP server for external agents: a `block-types` resource plus tools for pages, records, content types, media, settings, navigation, translations and site scaffolding, and a `replicate-site` prompt.
- SEO and AI discoverability: canonical URLs, Open Graph and Twitter cards, `hreflang` alternates, per-block Schema.org JSON-LD, `/sitemap.xml`, `/llms.txt`, `/llms-full.txt`, a dynamic `/robots.txt`, per-page and site-wide noindex toggles, and Markdown responses for agents via `Accept: text/markdown`.
- Branded transactional e-mail and themed error pages, with self-contained 500/503 pages that survive a broken app.
- Site export and import as a portable bundle, from the admin or the CLI.
- Server install (`wireup:install`) with a language prompt, and a self-update mechanism (`wireup:update`, Settings → Updates, optional auto-updates) with a pre-migration database backup and update-outcome e-mails.
