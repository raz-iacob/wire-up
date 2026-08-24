<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Block;
use App\Models\Page;

function pageWithBlock(string $slug, BlockType $type, array $content): Page
{
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);
    $page->blocks()->create(['type' => $type, 'position' => 0, 'content' => $content]);

    return $page;
}

/**
 * @return array<int, array{BlockType, string, string}>
 */
function itemFieldCombinations(): array
{
    $combinations = [];

    foreach (BlockType::cases() as $type) {
        foreach ($type->defaultContent() as $listKey => $listValue) {
            if (! is_array($listValue) || ! array_is_list($listValue) || ! is_array($listValue[0] ?? null)) {
                continue;
            }

            foreach (array_keys($listValue[0]) as $field) {
                $combinations[] = [$type, $listKey, (string) $field];
            }
        }
    }

    return $combinations;
}

it('renders every block type an agent left empty', function (): void {
    $failed = [];

    foreach (BlockType::cases() as $index => $type) {
        $slug = 'empty-block-'.$index;
        pageWithBlock($slug, $type, []);

        if ($this->get('/'.$slug)->getStatusCode() !== 200) {
            $failed[] = $type->value;
        }
    }

    expect($failed)->toBe([]);
});

it('renders every repeatable item an agent left without keys', function (): void {
    $failed = [];

    foreach (itemFieldCombinations() as $index => [$type, $listKey, $field]) {
        $slug = 'keyless-item-'.$index;
        pageWithBlock($slug, $type, [$listKey => [[]]]);

        if ($this->get('/'.$slug)->getStatusCode() !== 200) {
            $failed[] = $type->value.'.'.$listKey;
        }
    }

    expect($failed)->toBe([]);
});

it('renders when an agent puts an array where an item field expects a string', function (): void {
    $failed = [];

    foreach (itemFieldCombinations() as $index => [$type, $listKey, $field]) {
        $slug = 'array-field-'.$index;
        pageWithBlock($slug, $type, [$listKey => [[$field => ['a', 'b']]]]);

        if ($this->get('/'.$slug)->getStatusCode() !== 200) {
            $failed[] = $type->value.'.'.$listKey.'.'.$field;
        }
    }

    expect($failed)->toBe([]);
});

it('renders when an agent puts an array where a top-level field expects a string', function (): void {
    $failed = [];
    $index = 0;

    foreach (BlockType::cases() as $type) {
        foreach (array_keys($type->defaultContent()) as $field) {
            foreach ([['a', 'b'], ['k' => 'v']] as $hostile) {
                $slug = 'top-level-'.$index++;
                pageWithBlock($slug, $type, [$field => $hostile]);

                if ($this->get('/'.$slug)->getStatusCode() !== 200) {
                    $failed[] = $type->value.'.'.$field;
                }
            }
        }
    }

    expect($failed)->toBe([]);
});

it('renders when an agent shapes a repeatable list as a map instead of a list', function (): void {
    $failed = [];
    $index = 0;

    foreach (BlockType::cases() as $type) {
        foreach ($type->defaultContent() as $listKey => $listValue) {
            if (! is_array($listValue) || ! array_is_list($listValue) || ! is_array($listValue[0] ?? null)) {
                continue;
            }

            $slug = 'map-list-'.$index++;
            pageWithBlock($slug, $type, [$listKey => ['first' => $listValue[0]]]);

            if ($this->get('/'.$slug)->getStatusCode() !== 200) {
                $failed[] = $type->value.'.'.$listKey;
            }
        }
    }

    expect($failed)->toBe([]);
});

it('renders when an agent nests an array inside a translatable field', function (array $heading): void {
    pageWithBlock('nested-translatable', BlockType::RICH_TEXT, ['heading' => $heading]);

    $this->get('/nested-translatable')->assertOk();
})->with([
    'empty array' => [['en' => []]],
    'map' => [['en' => ['k' => 'v']]],
    'list' => [['en' => ['a', 'b']]],
]);

it('reads a scalar block field and falls back for anything else', function (): void {
    $block = new Block(['content' => [
        'items' => [
            ['variant' => 'ghost'],
            ['variant' => ['not', 'a', 'string']],
            ['variant' => 7],
        ],
    ]]);

    expect($block->plain('items.0.variant', 'primary'))->toBe('ghost')
        ->and($block->plain('items.1.variant', 'primary'))->toBe('primary')
        ->and($block->plain('items.2.variant', 'primary'))->toBe('7')
        ->and($block->plain('items.9.variant', 'primary'))->toBe('primary')
        ->and($block->plain('items.0.missing'))->toBe('');
});

it('returns an empty string when a translatable field holds an array', function (): void {
    $block = new Block(['content' => ['heading' => ['en' => ['nested' => 'value']], 'intro' => ['en' => 'Fine']]]);

    expect($block->text('heading'))->toBe('')
        ->and($block->text('intro'))->toBe('Fine');
});
