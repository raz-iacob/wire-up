<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\BlockHeading;

/**
 * @param  array<int, array{type: BlockType, content: array<string, mixed>}>  $blocks
 */
function headingPage(string $slug, array $blocks): string
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

/**
 * @return array<int, string>
 */
function outlineOf(string $html): array
{
    $main = mb_strstr($html, '<main') ?: $html;

    preg_match_all('~<(h[1-6])[^>]*>(.*?)</\1>~s', $main, $matches, PREG_SET_ORDER);

    return array_map(
        fn (array $match): string => mb_strtoupper($match[1]).' '.mb_trim(strip_tags($match[2])),
        $matches,
    );
}

it('promotes a paragraph heading field to a real heading element', function (): void {
    expect(BlockHeading::html('<p>Our services</p>'))->toBe('<h2>Our services</h2>');
});

it('wraps a heading field that holds bare text', function (): void {
    expect(BlockHeading::html('Our services'))->toBe('<h2>Our services</h2>');
});

it('keeps the attributes of the paragraph it promotes', function (): void {
    expect(BlockHeading::html('<p style="text-align: center">Centred</p>'))
        ->toBe('<h2 style="text-align: center">Centred</h2>');
});

it('keeps inline markup inside the heading', function (): void {
    expect(BlockHeading::html('<p>Read the <a href="/docs">docs</a></p>'))
        ->toBe('<h2>Read the <a href="/docs">docs</a></h2>');

    expect(BlockHeading::html('Hello <strong>world</strong>'))
        ->toBe('<h2>Hello <strong>world</strong></h2>');
});

it('leaves a level the author chose in the editor alone', function (): void {
    expect(BlockHeading::html('<h3>Authored</h3>'))->toBe('<h3>Authored</h3>');
    expect(BlockHeading::html('<h3>Authored</h3><p>Second line</p>'))
        ->toBe('<h3>Authored</h3><p>Second line</p>');
});

it('promotes only the first line so a two-line heading stays one heading', function (): void {
    expect(BlockHeading::html('<p>Line one</p><p>Line two</p>'))
        ->toBe('<h2>Line one</h2><p>Line two</p>');
});

it('renders nothing for an empty heading field', function (string $heading): void {
    expect(BlockHeading::html($heading))->toBe('');
})->with(['empty' => '', 'whitespace' => '   ']);

it('leaves loose text that trails the heading outside it', function (): void {
    expect(BlockHeading::html('<p>Heading</p>trailing text'))
        ->toBe('<h2>Heading</h2>trailing text');
});

it('does not spend the heading on an empty first line', function (): void {
    expect(BlockHeading::html('<p> </p><p>The real heading</p>'))
        ->toBe('<p> </p><h2>The real heading</h2>');
});

it('falls back to the given level when the stored one is not a level', function (mixed $stored): void {
    expect(BlockHeading::level($stored))->toBe('h2');
    expect(BlockHeading::level($stored, 'h1'))->toBe('h1');
})->with(['unset' => null, 'empty' => '', 'unknown' => 'h9', 'not a string' => 7]);

it('falls back to h2 when the given level is not a level either', function (): void {
    expect(BlockHeading::level(null, 'h7'))->toBe('h2');
});

it('honours a stored level over the default', function (): void {
    expect(BlockHeading::level('h4', 'h1'))->toBe('h4');
    expect(BlockHeading::html('<p>Small</p>', 'h4'))->toBe('<h4>Small</h4>');
});

it('gives a page one h1 from the hero and h2 for every other block', function (): void {
    $html = headingPage('outline', [
        ['type' => BlockType::HERO, 'content' => ['heading' => ['en' => '<p>Welcome</p>']]],
        ['type' => BlockType::RICH_TEXT, 'content' => ['heading' => ['en' => '<p>About us</p>'], 'body' => ['en' => '<p>Copy</p>']]],
        ['type' => BlockType::STATS, 'content' => ['heading' => ['en' => '<p>By the numbers</p>'], 'items' => [['value' => ['en' => '10'], 'label' => ['en' => 'Years']]]]],
    ]);

    expect(outlineOf($html))->toBe(['H1 Welcome', 'H2 About us', 'H2 By the numbers']);
});

it('takes the heading level a block asks for', function (): void {
    $html = headingPage('chosen-level', [
        ['type' => BlockType::HERO, 'content' => ['headingLevel' => 'h2', 'heading' => ['en' => '<p>Welcome</p>']]],
        ['type' => BlockType::RICH_TEXT, 'content' => ['headingLevel' => 'h3', 'heading' => ['en' => '<p>A sub-section</p>'], 'body' => ['en' => '<p>Copy</p>']]],
    ]);

    expect(outlineOf($html))->toBe(['H2 Welcome', 'H3 A sub-section']);
});

it('leaves the record title as the only h1 when a hero sits on a record', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Install Wire-Up'],
        'data' => ['heading' => ['en' => 'Install Wire-Up']],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    $record->blocks()->create([
        'type' => BlockType::HERO,
        'position' => 0,
        'content' => ['heading' => ['en' => '<p>Get started</p>']],
    ]);

    $outline = outlineOf((string) test()->get('/guides/install-wire-up')->assertOk()->getContent());

    expect($outline)->toContain('H2 Get started')
        ->and(array_filter($outline, fn (string $line): bool => str_starts_with($line, 'H1 ')))
        ->toHaveCount(1);
});

it('keeps the heading wrapper class hook that custom CSS depends on', function (): void {
    $html = headingPage('css-hook', [
        ['type' => BlockType::RICH_TEXT, 'content' => ['heading' => ['en' => '<p>About us</p>'], 'body' => ['en' => '<p>Copy</p>']]],
    ]);

    expect($html)->toContain('text-(length:--wire-heading-size)');
});
