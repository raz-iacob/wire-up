---
name: design-and-branding
description: Use when the owner wants to change how the site looks, such as the theme, colours, fonts, logo, dark mode or overall style, or to match a brand.
---

# Design and branding

Always call `get-settings` first so you change only what was asked and keep everything else.

## Look

`update-design` sets the whole visual system. Prefer a preset theme over custom colours:

- **Themes:** default, slate, blueprint, ocean, lagoon, sunset, rose, royal, mono and sand. Every theme has a light and a dark palette that already pass contrast checks.
- **Dark mode** is a single switch; visitors whose device prefers dark get the theme's dark palette.
- **Custom colours** only when the owner has exact brand colours. Pass the theme as `custom` with the colours, and keep body text clearly readable against its background in both light and dark.
- **Fonts:** a heading font and a body font, or `custom` with a Google font name. Pair one distinctive heading font with a plain, readable body font.
- **Shape:** corner radius, button radius, border width and text sizes.
- **Layout:** content width, block spacing, and the header and footer layouts, including a sticky or transparent header.
- **Logos and favicon:** import the file first, then pass its media id as `logo_header`, `logo_footer` or `favicon`. The `_dark` versions are used on dark backgrounds, so a dark header shows the light logo.

## Identity

`update-identity` sets the site title and description used in search results and link previews, and which page is the homepage.

## Good practice

- Change one thing at a time when the owner is unsure, and describe the result in plain words.
- After a larger change, `render-page` the homepage to check it looks right, at desktop and mobile sizes.
