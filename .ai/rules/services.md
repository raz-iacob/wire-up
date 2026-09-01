---
paths:
  - 'app/Services/*.php'
  - app/Services/SiteImporter.php
---

# Services

## Never serialise media preview or crop_src
Media items (App\Services\MediaItem::fromMedia) carry `preview` and `crop_src`. Both are computed accessors on the Media model that call ImageService::url(), which signs with URL::signedRoute — so the value is bound to this install's APP_KEY and host and 403s anywhere else.

They are derived cache values, not source data, and regenerate on read. Strip them from anything that leaves this install (bundles, API payloads, fixtures). They hide inside JSON columns: settings.value for favicon/logo_*/default_og_image/auth_image, blocks.content for any media field, records.data, and pages.metadata.layout.backgroundImage. App\Services\SiteExporter scrubs them recursively — reuse that rather than hand-rolling.

## Importing a bundle wipes every secret setting
`SiteImporter::replace()` deletes every row of each table in `SiteBundle::TABLES` — settings included — and reinserts only what the bundle carries. There is no merge mode. `SiteExporter` strips the 13 keys in `SiteBundle::SECRET_SETTINGS` unless `--with-secrets` is passed.

So a normal export/import cycle silently destroys the target's mail credentials, AI provider and key, Pexels key, Slack webhook, Google Analytics service-account credentials and property id, and the Google Maps key. Nothing warns about it, and the site keeps working until someone submits a form or opens the assistant.

Before importing into a live site, either write the secrets down to re-enter afterwards, or export with `--with-secrets` and treat the bundle as a credential file that must not leave the machine. `--dry-run` reports what the bundle holds without writing.

Note `google_analytics_id` (the public measurement id) is deliberately NOT secret and does travel; it is the GA credentials and property id that do not.
