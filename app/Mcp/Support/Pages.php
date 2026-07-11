<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Page;
use App\Services\SettingsService;
use Laravel\Mcp\Response;

final readonly class Pages
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function json(array $payload): Response
    {
        return Response::text(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Page $page): array
    {
        $page->loadMissing('slugs', 'translations');
        $slug = $page->getSlug();

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $slug,
            'url' => self::url($page, $slug),
            'status' => $page->status->value,
            'published_at' => $page->published_at?->toAtomString(),
            'published_locales' => $page->published_locales,
            'is_homepage' => self::isHomepage($page),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detailed(Page $page): array
    {
        $page->loadMissing('blocks');

        return [
            ...self::summary($page),
            'description' => $page->description,
            'slugs' => $page->getSlugsArray(),
            'layout' => $page->resolvedLayout(),
            'blocks' => $page->blocks
                ->map(fn (Block $block): array => [
                    'id' => $block->id,
                    'type' => $block->type->value,
                    'content' => $block->content ?? [],
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function blockRules(): array
    {
        return [
            'blocks' => ['array'],
            'blocks.*.id' => ['sometimes'],
            'blocks.*.type' => ['required', 'string', 'in:'.implode(',', BlockType::values())],
            'blocks.*.content' => ['array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockMessages(): array
    {
        return [
            'blocks.*.type.required' => 'Every block needs a "type". See the block-types resource for the available keys.',
            'blocks.*.type.in' => 'Unknown block type. Valid types are: '.implode(', ', BlockType::values()).'.',
            'blocks.*.content.array' => 'Block "content" must be an object shaped like the defaultContent in the block-types resource.',
        ];
    }

    private static function url(Page $page, string $slug): ?string
    {
        if (self::isHomepage($page)) {
            return route('home');
        }

        return $slug !== '' ? $page->getUrl() : null;
    }

    private static function isHomepage(Page $page): bool
    {
        return SettingsService::current()->homePageId() === $page->id;
    }
}
