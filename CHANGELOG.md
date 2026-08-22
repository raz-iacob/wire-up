# Changelog

Release notes for Wire-Up. Each release is a `## vX.Y.Z` section; the updater shows the sections newer than the installed version on **Settings → Updates**.

## Unreleased

- Test buttons on Settings → Integrations for Slack, e-mail and the AI assistant, checking the credentials you have typed before you save them.
- The Settings group in the admin sidebar now stays open while you move between pages, until you close it.
- Fixed a new block added in the middle of a page or record through the AI assistant or an MCP tool silently jumping to the bottom.
- Site-wide custom CSS no longer applies to the login and other account pages, where it could make the form unreadable and lock you out of your own site.
- A content type's URL prefix may now match an existing page's web address, so that page can act as the landing page the breadcrumb links to.
- Menu item badges now actually appear on the site, in the colour you pick, and can be set on the header and footer menus rather than only custom ones.
- The columns footer layout now builds real grouped columns from group headings in the footer menu, which that menu can now contain.

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
