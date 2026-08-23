<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Enums\BlockType;
use App\Mcp\Support\Pages;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Resource;

#[Name('block-types')]
#[Description('Catalog of every Wire-Up block type: its key, purpose, default content shape, and the conventions for localized text, links, and media.')]
final class BlockTypesResource extends Resource
{
    protected string $uri = 'wire-up://block-types';

    protected string $mimeType = 'application/json';

    public function handle(Request $request): Response
    {
        return Pages::json([
            'conventions' => $this->conventions(),
            'blockTypes' => $this->blockTypes(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function conventions(): array
    {
        return [
            'blocks' => 'A page holds an ordered list of blocks: {"type": "<block key>", "content": {...}}. The defaultContent below shows every field a block supports; omitted fields fall back to sensible defaults.',
            'localizedText' => 'Text fields (heading, subheading, body, intro, title, quote, author, role, name, label, value, address, and similar) are objects keyed by locale code, e.g. {"en": "<p>Hello</p>"}. Rich-text fields accept HTML (p, h2-h4, ul/ol, a, strong, em).',
            'links' => 'Link objects are {"type": "url"|"anchor"|"page", "value": "<url, #anchor, or page id>", "newTab": bool}. CTA objects wrap a link with {"enabled": bool, "text": {locale map}, "link": {...}}.',
            'media' => 'Image and file fields are objects like {"source": "<media library path>", "metadata": {"alt": "...", "caption": "..."}}. Get source paths from list-media, import-media-from-url, or search-pexels + import-pexels-media.',
            'anchor' => 'Every block except spacer and divider takes an "anchor" string, rendered as the element id on the block\'s section: "services" renders id="services". Set it on the target block first, then link to it from anywhere on the same page with {"type": "anchor", "value": "#services"}. Use a lowercase slug of letters, numbers and hyphens, unique within the page.',
            'items' => 'Repeating blocks (accordion, testimonials, team, pricing, ...) hold an "items" array; give each item a unique string "id".',
            'escapedFields' => 'Most rich-text fields render as raw HTML, so class attributes and inline markup survive. These render escaped instead, and an HTML entity there shows literally: stats item "value" and "label", feature-cards item "title", menu item labels, and search placeholders. Write real characters in those — "—" not "&mdash;", "\'" not "&rsquo;".',
            'collectionSource' => 'The collection block\'s "source" accepts only "latest", "manual" or "category" — any other value silently falls back to "latest". A hand-picked list needs "source": "manual" plus "recordIds": [<record id>, ...]. Filtering by category needs "source": "category" plus a category id from list-categories.',
            'collectionFields' => 'The collection block\'s "fields" is a flat list of field-key strings, e.g. ["audience", "reading_time"] — not the field objects create-content-type takes. Non-string entries are dropped. The keys come from the content type blueprint in list-content-types.',
            'featureCardIcons' => 'Each feature-cards item shows either an image or an icon, chosen by its "media" key ("image" or "icon"). With "media": "icon", set "icon" to one of these names — any other value renders nothing: '.implode(', ', config()->array('menu.icons')).'. Icon size follows the block\'s "imageHeight", and the icon takes the card text colour.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blockTypes(): array
    {
        return array_map(fn (BlockType $type): array => [
            'key' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'defaultContent' => $this->defaultContent($type),
        ], BlockType::cases());
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultContent(BlockType $type): array
    {
        $content = $type->defaultContent();

        return $type->hasAnchor() ? [...$content, 'anchor' => ''] : $content;
    }
}
