<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;

function dividerPage(string $slug, array $content): string
{
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);
    $page->blocks()->create(['type' => BlockType::DIVIDER, 'position' => 0, 'content' => $content]);

    $html = test()->get('/'.$slug)->assertOk()->getContent();

    preg_match('#<hr [^>]*style="([^"]*)"#', (string) $html, $line);

    return $line[1] ?? '';
}

it('draws a solid line in the theme divider colour by default', function (): void {
    expect(dividerPage('plain-divider', []))
        ->toContain('background-color:var(--wire-divider)')
        ->toContain('height:2px');
});

it('draws each line style', function (string $style, string $expected): void {
    expect(dividerPage('divider-'.$style, ['style' => $style, 'color' => '#38b6ff']))->toContain($expected);
})->with([
    'fade at both edges' => ['fade-edges', 'linear-gradient(to right, transparent, #38b6ff, transparent)'],
    'fade to the right' => ['fade-right', 'linear-gradient(to right, #38b6ff, transparent)'],
    'fade to the left' => ['fade-left', 'linear-gradient(to right, transparent, #38b6ff)'],
    'dotted' => ['dotted', 'border-top:2px dotted #38b6ff'],
    'dashed' => ['dashed', 'border-top:2px dashed #38b6ff'],
]);

it('takes its thickness from the size', function (string $size, string $expected): void {
    expect(dividerPage('divider-size-'.$size, ['size' => $size]))->toContain($expected);
})->with([
    'thin' => ['thin', 'height:1px'],
    'thick' => ['thick', 'height:3px'],
]);

it('falls back to a solid line for a style an agent invented', function (): void {
    expect(dividerPage('divider-unknown-style', ['style' => 'zigzag', 'color' => '#38b6ff']))
        ->toContain('background-color:#38b6ff')
        ->not->toContain('zigzag');
});

it('discards a colour that tries to smuggle in more declarations', function (): void {
    $line = dividerPage('divider-injection', ['color' => 'red;position:fixed;inset:0;z-index:99']);

    expect($line)->toContain('background-color:var(--wire-divider)')
        ->not->toContain('position:fixed');
});

it('drops the container so a full-width line runs edge to edge', function (string $width, bool $contained): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'divider-'.$width]);
    $page->blocks()->create(['type' => BlockType::DIVIDER, 'position' => 0, 'content' => ['width' => $width]]);

    $html = $this->get('/divider-'.$width)->assertOk()->getContent();

    preg_match('#data-block="divider"[^>]*>\s*<div class="([^"]*)"#', (string) $html, $wrapper);

    expect(str_contains($wrapper[1] ?? '', 'max-w-(--wire-container)'))->toBe($contained);
})->with([
    'content width' => ['normal', true],
    'full width' => ['full', false],
]);
