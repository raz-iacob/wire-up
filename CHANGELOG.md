# Changelog

Release notes for Wire-Up. Each release is a `## vX.Y.Z` section; the updater shows the sections newer than the installed version on **Settings → Updates**.

## Unreleased

- Fixed `composer install` failing on a fresh checkout with a database cache store: the app read the cache table while booting, before the first migration had created it.
- Sunset, Rose, Royal, Mono and Sand are redrawn in both light and dark: a neutral base carrying one confident accent, rather than the hue washed across the page, the header and the body text. Slate's and Blueprint's link and button colours are corrected too, so all ten themes now clear WCAG AA on every text pair in both palettes.
- The rich text editor gains a text colour control. Pick Accent, Muted or Default and the colour is stored as a theme token, so it follows your palette and flips correctly in dark mode; a custom colour is there when you need one. This is how a two-tone heading is made — previously a colour typed into the source view was silently thrown away when the block was next opened.
- The Forest theme is replaced by **Lagoon**, a teal-and-deep-navy palette in both light and dark. Sites using Forest should pick a new theme; nothing else changes.
- Themes now carry their own hero colour. The hero used to borrow the header's, which for most themes was the same as the page — in dark mode every single theme rendered the hero as an invisible band. Each theme now sets a Hero background and text, and the custom colour editor gains a Hero group. A palette saved before this falls back to the header colours, so nothing changes until you set them.
- Your logo now follows the bar it sits on rather than the colour scheme. Themes like Ocean, Lagoon and Mono put a dark header on a light page, where the dark-ink logo used to disappear; the light version is used there instead, and the header and footer are judged separately.
- The Ocean theme is redrawn in both light and dark. Its link colour and primary button label failed contrast checks before (2.6:1 and 2.8:1 against a 4.5:1 minimum); every text pair in both palettes now clears WCAG AA, the muted tone reads as a real step below body text, and cards separate properly from the page.
- Every colour theme now ships a matching dark palette, so dark mode is a single switch instead of a second theme to pick. Choose Ocean and visitors on a dark device get Ocean's dark palette. The Midnight theme is replaced by **Blueprint** — the palette this site uses — which carries both a light and a dark half. Sites that had picked a separate dark theme keep dark mode switched on; a custom theme still gets its own dark colours.
- The "Allow this user to sign in" switch is now locked when turning it off would lock you out of your own account, or leave the site with no full-access user who can still sign in — with the reason shown next to it. The check that protects the last full-access role now also ignores accounts that are switched off.
- A header or footer menu item styled as a button no longer picks up the underline hover effect meant for links — it fades slightly instead, like every other button on the site.
- The divider block gains a line style, a colour and a width: solid, faded at both edges, faded to the left or right, dotted or dashed; any colour you like or the theme's own; and content width or edge to edge.
- The rich text editor gains a style picker: any paragraph can become Heading 1, 2 or 3, in block text and in record fields alike. Headings in block body copy are sized on the public site, and headings now survive a paste instead of being flattened to plain paragraphs.
- Every URL field in the admin now carries the same open-in-a-new-tab arrow the page's web address has — on social links, menu items, block buttons, sponsor and team links, the video URL and your own URL fields. The arrow stays greyed out until the field holds a web address.
- Code blocks now render as a terminal window by default: a darker panel tinted with your accent colour, a soft drop shadow, and the filename bar carrying the three window dots.
- More icons to pick from for menu items and feature cards: search, eye, palette, refresh, download, inbox, speaker, film, key, table, chat, server, credit card, tick, cube, sliders and language, on top of the existing set.
- Inline `code` in block text now renders as a small accent-tinted chip on the public site, matching what the editor's code shortcut (⌘E) produces.
- Custom CSS can now target blocks directly: every block on a page carries `data-block="hero"`, `data-block="rich-text"` and so on, and a record page carries `data-record="<content type>"`. Styling one kind of block no longer depends on where it happens to sit on the page.
- Share a draft before it goes live: open Preview on an unpublished page or record and use the share button in its top bar to copy a signed web address anyone can open without signing in, good for 7 days by default. Sharing saves the draft first, so the link shows exactly what you were previewing. Preview links are never indexed by search engines, and they work for the homepage too.
- New block options: an icon instead of an image on feature cards, a "Frame the image" switch on the photo block, a "Related" source on collection blocks, a new "Previous / Next" block for records, and any video aspect ratio including the uploaded file's own.
- Pages and records without a share image of their own now get one generated automatically: a 1200x630 card with the title, your accent colour and your logo, in your heading font. It is only ever a fallback — an image you upload or pick still wins, and the card never appears in your media library. Needs a headless browser on the server; without one nothing changes. `wireup:og:generate` rebuilds them all, and `WIREUP_OG_IMAGES=false` turns the whole thing off.
- Admin pages do noticeably less work. The permission list, the content types and the homepage lookup were each rebuilt many times per page; list screens fetched every row's title one row at a time; and the dashboard counted records once per content type. The roles screen went from 31 database queries to 3, the dashboard from 35 to 27, and most settings screens from 6 to 1.
- Fixed the sidebar showing two content types side by side on one row instead of each on its own.
- A content type can be named per language. On a site running more than one language, Settings → Content types gains a name field for each of the others, and the public breadcrumb on a record now reads in the visitor's language instead of always showing the name you first typed.
- Search the whole admin from the header, or with ⌘K: it finds your pages, records, categories and users by name, and jumps to any admin screen — including individual settings pages, matched on what they do, so "logo" finds Design. It only ever shows what your role can reach.
- Duplicate a block from its ⋯ menu in the page and record editors. The copy lands directly below the original with all its content, and an anchor on the copy is made unique when you save.
- The rich text block's Width and Alignment no longer overlap: Width is just Normal or Narrow, and Alignment moves the narrow column left, centre or right. If you used the old "Narrow (left)" width, set the block to Narrow with Alignment on the left. Alignment used to centre the text instead, which the editor toolbar already does per paragraph — so centre text there from now on.
- Import files that are already on the server into the media library, from the terminal with `wireup:media:import` or through agents with `upload-media` and `list-import-files`. SVG, HEIC and video all work, which importing from a URL could not.
- Form messages now read as real sentences and name fields in plain words, instead of showing internal keys such as "validation.required" or paths such as "title.en". Settings → Translations gains a "Form messages" group so a site in another language can translate them.
- The admin stays usable while an update runs: Settings → Updates shows live progress instead of the "Down for maintenance" page.
- Importing a site through the admin no longer leaves the uploaded bundle sitting in temporary storage afterwards — it was kept for up to a day, doubling the disk a large bundle needed. A bundle you import from a server path is still left untouched.
- Fixed the admin sometimes loading with its interactive parts dead on a first visit, most visibly Settings → Design opening with an empty "Edit image crop" box. The first paint now also matches your device theme rather than briefly flashing dark; a theme chosen on your account still wins.
- `wireup:admin` now takes `--name`, `--email` and `--password`, so an admin can be created from a script or a fresh-install routine. Anything you leave out is still asked for.
- Agents and the AI assistant can now build a sidebar with a new `create-menu` tool, and set a page's layout through `update-page` — hiding the header or footer, a background colour or image, per-page CSS, and which menus appear beside the content.
- Agents and the AI assistant can now set the dark-mode logos, the favicon and the header light/dark toggle through `update-design`, and can create and list categories so records can be grouped and collections can filter by them.
- Agents and the AI assistant can screenshot a page, record or path at desktop, tablet or mobile size with the new `render-page` tool, drafts included, so they can see their work instead of guessing. Needs a headless browser on the server; the error explains how to install one.
- Agents and the AI assistant can delete pages, records and media rather than only drafting them. The assistant asks you to approve each deletion in the chat, an outside agent such as Claude Desktop has to confirm in the call itself, the homepage is protected, and a file still in use is refused with a list of what uses it.
- The block-types catalogue agents read now spells out which fields render escaped rather than as HTML, and the exact shapes the collection block accepts.
- Block content written by an agent or the AI assistant can no longer put working JavaScript on your public pages: script, iframe and style tags, `onclick`-style attributes and `javascript:` links are stripped when a block is saved, whoever saved it. Ordinary formatting, classes and anchors are untouched.
- Fixed server errors on published pages and records built by an agent or the AI assistant — a block saved without a layout, or a field written in the wrong shape such as a list where a piece of text belongs. Blocks now fall back to their defaults instead of taking the page down.

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
