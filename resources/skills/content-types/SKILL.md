---
name: content-types
description: Use when the owner has repeating content such as products, services, blog posts, events, team members, projects, jobs or a portfolio, or asks for a list, grid or archive of items.
---

# Content types and collections

Use a content type whenever there is more than one item of the same shape. Each item becomes a record with its own fields, its own blocks and, optionally, its own page at `/prefix/slug`. One-off pages are for unique content only.

## Create the type

1. Call `list-content-types` first; the type may exist already, and the list shows the available presets.
2. Prefer a preset with `create-content-type`: product, service, post, event, team-member, project or job. Each comes with sensible fields and a URL prefix.
3. For anything else, pass custom `fields`. Field types are text, textarea, rich-text, number, money, date, datetime, boolean, select, photo, video, audio, document, media-gallery, url, email, color, tags, multi-select and relationship. Tags and multi-select hold lists of strings; multi-select takes its choices from `options`. A relationship field links records to records of another type, such as related products or the speakers of an event: set `record_type` to that type's key and `multiple` to allow several, then fill it with record ids. Create the linked type first. Mark short fields that should appear in the admin list as `column`.
4. Each field's `placement` decides where it shows on a record's page: `main` (the wide column), `sidebar` (the narrow column beside it) or `hidden`. The field order is the order on the page. Put the photo, price and short facts in the sidebar and long text, galleries and videos in main. Hidden fields stay in the admin only.
5. Set `has_detail_page: false` when the records only feed cards, such as logos or testimonials, so they get no page of their own. Set `has_index_page: true` to publish an automatic listing at the prefix.

## Add the records

- `create-record` per item, with its field values and media ids. Records start as drafts.
- Group records with `list-categories` + `create-category`. Categories are shared across all content types.
- `publish-record` when the owner asks; it asks the owner to approve.

## Show them on a page

Add a collection block to a page with `update-page-blocks`:

- `recordTypeId` is the content type's id.
- `source` is `latest`, `manual` (with `recordIds`), `category` (with `categoryId`) or `related`. `related` only works on a record's own page.
- `fields` is a list of field keys to show on each card, for example `["current_price"]`, not field objects.
- `layout` is grid, list or carousel.

The block-types catalog has the full collection block shape.
