<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;

function publishPageWithBlocks(string $slug, array $blocks): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ucfirst($slug),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);
    $page->updateBlocks($blocks);

    return $page;
}

it('renders all block types on the public page', function (): void {
    publishPageWithBlocks('home-blocks', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Welcome aboard'],
            'subheading' => ['en' => 'Glad to have you'],
            'align' => 'center',
        ]],
        ['id' => 'new-2', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>Some <strong>rich</strong> copy</p>'],
        ]],
        ['id' => 'new-3', 'type' => 'spacer', 'content' => ['size' => 'large']],
    ]);

    $this->get(route('page', 'home-blocks'))
        ->assertOk()
        ->assertSee('Welcome aboard')
        ->assertSee('Glad to have you')
        ->assertSee('<strong>rich</strong>', false)
        ->assertSee('aria-hidden="true"', false);
});

it('renders block text in the current locale', function (): void {
    publishPageWithBlocks('localized', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'English heading', 'fr' => 'Titre francais'],
        ]],
    ]);

    $this->get(route('page', 'localized'))
        ->assertOk()
        ->assertSee('English heading')
        ->assertDontSee('Titre francais');
});

it('renders text blocks gracefully when no image is selected', function (): void {
    publishPageWithBlocks('no-image', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Imageless hero']]],
        ['id' => 'new-2', 'type' => 'text-image', 'content' => ['body' => ['en' => '<p>Just words</p>']]],
    ]);

    $this->get(route('page', 'no-image'))
        ->assertOk()
        ->assertSee('Imageless hero')
        ->assertSee('Just words', false)
        ->assertDontSee('<img', false)
        ->assertDontSee('background-image', false)
        ->assertSee('background-color:var(--wire-header-bg)', false);
});

it('renders an image when a block has one', function (): void {
    publishPageWithBlocks('with-image', [
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>Look</p>'],
            'image' => ['source' => 'uploads/photo.jpg', 'crop' => [], 'alt_text' => 'A photo'],
        ]],
    ]);

    $this->get(route('page', 'with-image'))
        ->assertOk()
        ->assertSee('<img', false)
        ->assertSee('uploads/photo.jpg', false)
        ->assertSee('A photo', false);
});

it('renders a hero color gradient background', function (): void {
    publishPageWithBlocks('gradient-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Gradient hero'],
            'background' => ['type' => 'color', 'gradient' => ['start' => '#ff0000', 'end' => '#0000ff', 'direction' => 'to-r']],
        ]],
    ]);

    $this->get(route('page', 'gradient-hero'))
        ->assertOk()
        ->assertSee('linear-gradient(to right, #ff0000, #0000ff)', false)
        ->assertDontSee('<img', false);
});

it('falls back to theme colors for an inherited hero gradient', function (): void {
    publishPageWithBlocks('inherit-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Inherited hero'],
            'background' => ['type' => 'color', 'gradient' => ['direction' => 'to-b']],
        ]],
    ]);

    $this->get(route('page', 'inherit-hero'))
        ->assertOk()
        ->assertSee('linear-gradient(to bottom, var(--wire-header-bg), var(--wire-header-bg))', false)
        ->assertSee('color:var(--wire-header-text)', false);
});

it('sizes hero heading and subheading relative to the theme typography', function (): void {
    publishPageWithBlocks('typo-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Sized heading'],
            'subheading' => ['en' => '<p>Sized sub</p>'],
        ]],
    ]);

    $this->get(route('page', 'typo-hero'))
        ->assertOk()
        ->assertSee('calc(var(--wire-heading-size, 1.5rem) * 1.5)', false)
        ->assertSee('calc(var(--wire-body-size, 0.875rem) * 1.25)', false);
});

it('applies separate heading and subheading colors and renders an h2', function (): void {
    publishPageWithBlocks('two-color-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Coloured heading'],
            'subheading' => ['en' => '<p>Coloured sub</p>'],
            'headingColor' => '#ff0000',
            'subheadingColor' => '#00ff00',
        ]],
    ]);

    $this->get(route('page', 'two-color-hero'))
        ->assertOk()
        ->assertSee('<h2', false)
        ->assertSee('color:#ff0000', false)
        ->assertSee('color:#00ff00', false)
        ->assertSee('Coloured heading')
        ->assertSee('Coloured sub');
});

it('renders hero CTA buttons with resolved links', function (): void {
    $target = publishPageWithBlocks('cta-target', [
        ['id' => 'new-1', 'type' => 'spacer', 'content' => ['size' => 'small']],
    ]);

    publishPageWithBlocks('cta-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'CTA hero'],
            'ctaPrimary' => ['enabled' => true, 'text' => ['en' => 'Get started'], 'link' => ['type' => 'anchor', 'value' => 'contact']],
            'ctaSecondary' => ['enabled' => true, 'text' => ['en' => 'Visit page'], 'link' => ['type' => 'page', 'value' => (string) $target->id]],
        ]],
    ]);

    $this->get(route('page', 'cta-hero'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('href="#contact"', false)
        ->assertSee('Visit page')
        ->assertSee($target->getUrl(), false);
});

it('omits hero CTA buttons that are disabled or incomplete', function (): void {
    publishPageWithBlocks('no-cta', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'No CTA'],
            'ctaPrimary' => ['enabled' => false, 'text' => ['en' => 'Hidden cta'], 'link' => ['type' => 'anchor', 'value' => 'x']],
            'ctaSecondary' => ['enabled' => true, 'text' => ['en' => 'Linkless cta'], 'link' => ['type' => 'url', 'value' => '']],
        ]],
    ]);

    $this->get(route('page', 'no-cta'))
        ->assertOk()
        ->assertDontSee('Hidden cta')
        ->assertDontSee('Linkless cta');
});

it('renders a full-screen hero image as a cover <img> with alt text', function (): void {
    publishPageWithBlocks('img-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Imaged hero'],
            'height' => 'screen',
            'background' => ['type' => 'image', 'image' => ['source' => 'uploads/hero.jpg', 'crop' => [], 'metadata' => ['alt' => 'Mountain vista']]],
        ]],
    ]);

    $this->get(route('page', 'img-hero'))
        ->assertOk()
        ->assertSee('<img', false)
        ->assertSee('uploads/hero.jpg', false)
        ->assertSee('Mountain vista', false)
        ->assertSee('object-cover', false)
        ->assertSee('min-h-svh', false)
        ->assertSee('Imaged hero');
});

it('renders a fit-content hero image inline and boxed for container width', function (): void {
    publishPageWithBlocks('boxed-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Boxed hero'],
            'width' => 'container', 'height' => 'auto',
            'background' => ['type' => 'image', 'image' => ['source' => 'uploads/box.jpg', 'crop' => [], 'metadata' => ['alt' => 'Boxed art']]],
        ]],
    ]);

    $this->get(route('page', 'boxed-hero'))
        ->assertOk()
        ->assertSee('<img', false)
        ->assertSee('Boxed art', false)
        ->assertSee('max-w-7xl', false)
        ->assertSee('my-12', false)
        ->assertSee('block w-full', false);
});

it('applies hero height and alignment options', function (): void {
    publishPageWithBlocks('full-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Tall hero'],
            'height' => 'screen', 'align' => 'left', 'verticalAlign' => 'bottom',
        ]],
    ]);

    $this->get(route('page', 'full-hero'))
        ->assertOk()
        ->assertSee('min-h-svh', false)
        ->assertSee('justify-end', false)
        ->assertSee('items-start', false);
});

it('wraps a block with an anchor id target', function (): void {
    publishPageWithBlocks('anchored', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Jump here'], 'anchor' => 'contact']],
    ]);

    $this->get(route('page', 'anchored'))
        ->assertOk()
        ->assertSee('id="contact"', false);
});

it('renders a page with no blocks without error', function (): void {
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Empty',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'empty']);

    $this->get(route('page', 'empty'))->assertOk();
});
