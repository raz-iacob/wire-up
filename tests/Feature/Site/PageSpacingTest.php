<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;

/**
 * @param  array<int, array{type: BlockType, content: array<string, mixed>}>  $blocks
 */
function spacingPage(string $slug, array $blocks): string
{
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    foreach ($blocks as $position => $block) {
        $page->blocks()->create([
            'type' => $block['type'],
            'position' => $position,
            'content' => $block['content'],
        ]);
    }

    return (string) test()->get('/'.$slug)->assertOk()->getContent();
}

function firstBlockClasses(string $html): string
{
    preg_match('~<div\s+data-block="[^"]*"\s*class="([^"]*)"~', $html, $match);

    return $match[1] ?? '';
}

it('leaves space above a first block that is not a full-width hero', function (): void {
    $html = spacingPage('space-above', [
        ['type' => BlockType::RICH_TEXT, 'content' => ['heading' => ['en' => '<p>About</p>'], 'body' => ['en' => '<p>Copy</p>']]],
    ]);

    expect(firstBlockClasses($html))->toContain('pt-16');
});

it('does not space a page that opens with a full-width hero', function (): void {
    $html = spacingPage('hero-first', [
        ['type' => BlockType::HERO, 'content' => ['heading' => ['en' => '<p>Welcome</p>'], 'width' => 'full']],
    ]);

    expect(firstBlockClasses($html))->not->toContain('pt-16');
});

it('spaces a page whose hero is held to the container', function (): void {
    $html = spacingPage('contained-hero-first', [
        ['type' => BlockType::HERO, 'content' => ['heading' => ['en' => '<p>Welcome</p>'], 'width' => 'container']],
    ]);

    expect(firstBlockClasses($html))->toContain('pt-16');
});

it('sizes the space from the block spacing setting', function (string $spacing, string $expected): void {
    config()->set('site.block_spacing', $spacing);

    expect(firstBlockClasses(spacingPage('space-'.$spacing, [
        ['type' => BlockType::RICH_TEXT, 'content' => ['heading' => ['en' => '<p>About</p>'], 'body' => ['en' => '<p>Copy</p>']]],
    ])))->toContain($expected);
})->with([
    'small' => ['small', 'pt-12'],
    'large' => ['large', 'pt-20'],
]);

it('leaves the space off when the setting is off', function (): void {
    config()->set('site.block_space_top', false);

    $html = spacingPage('no-space-above', [
        ['type' => BlockType::RICH_TEXT, 'content' => ['heading' => ['en' => '<p>About</p>'], 'body' => ['en' => '<p>Copy</p>']]],
    ]);

    expect(firstBlockClasses($html))->not->toContain('pt-16');
});

it('butts the block after a full-width hero against it by default', function (): void {
    $html = spacingPage('flush-hero', [
        ['type' => BlockType::HERO, 'content' => ['heading' => ['en' => '<p>Welcome</p>'], 'width' => 'full']],
        ['type' => BlockType::RICH_TEXT, 'content' => ['heading' => ['en' => '<p>About</p>'], 'body' => ['en' => '<p>Copy</p>']]],
    ]);

    expect(firstBlockClasses($html))->toContain('-mb-16');
});

it('keeps the usual spacing below a hero with flush switched off', function (): void {
    $html = spacingPage('unflush-hero', [
        ['type' => BlockType::HERO, 'content' => ['heading' => ['en' => '<p>Welcome</p>'], 'width' => 'full', 'flush' => false]],
        ['type' => BlockType::RICH_TEXT, 'content' => ['heading' => ['en' => '<p>About</p>'], 'body' => ['en' => '<p>Copy</p>']]],
    ]);

    expect(firstBlockClasses($html))->not->toContain('-mb-16');
});

it('still skips the top space for a hero with flush switched off', function (): void {
    $html = spacingPage('unflush-hero-first', [
        ['type' => BlockType::HERO, 'content' => ['heading' => ['en' => '<p>Welcome</p>'], 'width' => 'full', 'flush' => false]],
    ]);

    expect(firstBlockClasses($html))->not->toContain('pt-16');
});
