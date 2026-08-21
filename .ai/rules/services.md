---
paths:
  - 'app/Services/*.php'
---

# Services

## Never serialise media preview or crop_src
Media items (App\Services\MediaItem::fromMedia) carry `preview` and `crop_src`. Both are computed accessors on the Media model that call ImageService::url(), which signs with URL::signedRoute — so the value is bound to this install's APP_KEY and host and 403s anywhere else.

They are derived cache values, not source data, and regenerate on read. Strip them from anything that leaves this install (bundles, API payloads, fixtures). They hide inside JSON columns: settings.value for favicon/logo_*/default_og_image/auth_image, blocks.content for any media field, records.data, and pages.metadata.layout.backgroundImage. App\Services\SiteExporter scrubs them recursively — reuse that rather than hand-rolling.
