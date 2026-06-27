<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\MediaType;
use App\Enums\PageStatus;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;
use App\Services\SeoService;

function seoUnitPage(array $blocks = [], array $attributes = []): Page
{
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    foreach (array_values($blocks) as $position => $block) {
        $page->blocks()->create([
            'type' => $block['type'],
            'position' => $position,
            'content' => $block['content'],
        ]);
    }

    return $page->load('blocks');
}

it('harvests plain text from a block across prose fields and strips tags', function (): void {
    $page = seoUnitPage([
        ['type' => BlockType::RICH_TEXT->value, 'content' => [
            'heading' => ['en' => 'Our Mission'],
            'body' => ['en' => '<p>We build <strong>great</strong> things.</p>'],
        ]],
    ]);

    $block = $page->blocks->first();

    expect($block->plainText('en'))->toBe('Our Mission We build great things.');
});

it('concatenates block text into a page excerpt and limits length', function (): void {
    $page = seoUnitPage([
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'Alpha'], 'body' => ['en' => 'Beta gamma delta.']]],
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'Epsilon'], 'body' => ['en' => '']]],
    ]);

    expect($page->plainText('en'))->toBe('Alpha Beta gamma delta. Epsilon')
        ->and($page->textExcerpt(10))->toBe('Alpha Beta');
});

it('resolves the description from the stored value first', function (): void {
    $page = seoUnitPage([
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'From a block']]],
    ]);

    expect(SeoService::current()->description($page, 'Stored description'))->toBe('Stored description');
});

it('falls back to a block-derived excerpt when no description is stored', function (): void {
    $page = seoUnitPage([
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'Derived from the block content']]],
    ]);

    expect(SeoService::current()->description($page, ''))->toBe('Derived from the block content');
});

it('falls back to the site description when a page has no usable text', function (): void {
    Settings::set(['description' => ['en' => 'Site level tagline']]);

    $page = seoUnitPage();

    expect(SeoService::current()->description($page, ''))->toBe('Site level tagline');
});

it('reports noindex robots when the site is discouraged', function (): void {
    Settings::set(['noindex' => true]);

    expect(SeoService::current()->robots(null))->toBe('noindex, nofollow');
});

it('reports noindex robots when the page is discouraged', function (): void {
    $page = seoUnitPage(attributes: ['metadata' => ['noindex' => true]]);

    expect(SeoService::current()->robots($page))->toBe('noindex, nofollow');
});

it('reports an indexable robots directive by default', function (): void {
    $page = seoUnitPage();

    expect(SeoService::current()->robots($page))->toBe('index, follow, max-image-preview:large');
});

it('falls back to the site default share image when a page has none', function (): void {
    Settings::set(['default_og_image' => ['source' => 'share.jpg', 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]]]);

    $page = seoUnitPage();

    expect(SeoService::current()->ogImageUrl($page))->toContain('share.jpg');
});

it('returns no share image when neither the page nor the site provides one', function (): void {
    $page = seoUnitPage();

    expect(SeoService::current()->ogImageUrl($page))->toBeNull();
});

it('prefers the page own og_image over the site default', function (): void {
    Settings::set(['default_og_image' => ['source' => 'fallback.jpg', 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]]]);

    $page = seoUnitPage();
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $page->media()->attach($media->id, [
        'role' => 'og_image',
        'locale' => 'en',
        'position' => 0,
        'crop' => ['desktop' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]],
    ]);
    $page->load('media');

    expect(SeoService::current()->ogImageUrl($page))
        ->toContain($media->source)
        ->not->toContain('fallback.jpg');
});

it('includes the organization logo in the json-ld graph', function (): void {
    Settings::set(['logo_header' => ['source' => 'media/logo.png', 'crop' => ['default' => ['crop_w' => 200, 'crop_h' => 60, 'crop_x' => 0, 'crop_y' => 0]]]]);

    $organization = SeoService::current()->jsonLd(null)['@graph'][0];

    expect($organization)->toHaveKey('logo')
        ->and($organization['logo'])->toContain('media/logo.png');
});
