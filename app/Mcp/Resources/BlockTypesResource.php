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
            'localizedText' => 'Text fields (heading, subheading, body, intro, title, quote, author, role, name, label, value, and similar) are objects keyed by locale code, e.g. {"en": "<p>Hello</p>"}. Rich-text fields accept HTML (p, h2-h4, ul/ol, a, strong, em).',
            'links' => 'Link objects are {"type": "url"|"anchor"|"page", "value": "<url, #anchor, or page id>", "newTab": bool}. CTA objects wrap a link with {"enabled": bool, "text": {locale map}, "link": {...}}.',
            'media' => 'Image and file fields are objects like {"source": "<media library path>", "metadata": {"alt": "...", "caption": "..."}}. Reference existing media library paths; media import tools are not part of this server yet.',
            'items' => 'Repeating blocks (accordion, testimonials, team, pricing, ...) hold an "items" array; give each item a unique string "id".',
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
            'defaultContent' => $type->defaultContent(),
        ], BlockType::cases());
    }
}
