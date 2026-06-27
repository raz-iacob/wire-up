<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;

function seoFeaturePage(string $slug, array $attributes = [], array $blocks = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    foreach (array_values($blocks) as $position => $block) {
        $page->blocks()->create([
            'type' => $block['type'],
            'position' => $position,
            'content' => $block['content'],
        ]);
    }

    return $page->refresh();
}

it('lists published pages in the sitemap and omits drafts', function (): void {
    seoFeaturePage('about', ['title' => ['en' => 'About Us']]);
    seoFeaturePage('draft-page', ['status' => PageStatus::DRAFT, 'published_at' => null]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/about', false)
        ->assertDontSee('/draft-page', false);
});

it('empties the sitemap when the site discourages search engines', function (): void {
    seoFeaturePage('about', ['title' => ['en' => 'About Us']]);
    Settings::set(['noindex' => true]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<urlset', false)
        ->assertDontSee('/about', false);
});

it('excludes a page-level noindex page from the sitemap', function (): void {
    seoFeaturePage('public-page', ['title' => ['en' => 'Public']]);
    seoFeaturePage('hidden-page', ['title' => ['en' => 'Hidden'], 'metadata' => ['published_locales' => ['en'], 'noindex' => true]]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/public-page', false)
        ->assertDontSee('/hidden-page', false);
});

it('lists pages in llms.txt and dumps full content in llms-full.txt', function (): void {
    seoFeaturePage('story', ['title' => ['en' => 'Our Story']], [
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'Once upon a time'], 'body' => ['en' => '<p>We started in a garage.</p>']]],
    ]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Our Story')
        ->assertSee('/story');

    $this->get('/llms-full.txt')
        ->assertOk()
        ->assertSee('We started in a garage.');
});

it('emits canonical, Open Graph and JSON-LD on a public page', function (): void {
    seoFeaturePage('contact', ['title' => ['en' => 'Contact'], 'description' => ['en' => 'Reach our team.']]);

    $this->get('/contact')
        ->assertOk()
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('<meta property="og:title" content="Contact">', false)
        ->assertSee('<meta property="og:description" content="Reach our team.">', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"WebPage"', false);
});

it('uses a block-derived description when a page has none stored', function (): void {
    seoFeaturePage('team', ['title' => ['en' => 'Team']], [
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'Meet the crew behind the work']]],
    ]);

    $this->get('/team')
        ->assertOk()
        ->assertSee('Meet the crew behind the work', false);
});

it('uses the site default share image when a page has none', function (): void {
    Settings::set(['default_og_image' => ['source' => 'default-share.jpg', 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]]]);
    seoFeaturePage('pricing', ['title' => ['en' => 'Pricing']]);

    $this->get('/pricing')
        ->assertOk()
        ->assertSee('default-share.jpg', false);
});

it('emits hreflang alternates in the sitemap for a multi-locale site', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $page = seoFeaturePage('about', [
        'metadata' => ['published_locales' => ['en', 'nl']],
        'title' => ['en' => 'About', 'nl' => 'Over ons'],
    ]);
    $page->slugs()->create(['locale' => 'nl', 'slug' => 'over-ons']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('hreflang="en"', false)
        ->assertSee('hreflang="nl"', false)
        ->assertSee('hreflang="x-default"', false);
});

it('skips published pages with no slug in the llms exports', function (): void {
    Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Slugless Page'],
    ]);

    $this->get('/llms.txt')->assertOk()->assertDontSee('Slugless Page');
    $this->get('/llms-full.txt')->assertOk()->assertDontSee('Slugless Page');
});

it('includes the site tagline in the llms index', function (): void {
    Settings::set(['title' => ['en' => 'Acme'], 'description' => ['en' => 'Building better things']]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('Building better things');
});

it('renders a noindex robots tag for a page-level noindex page only', function (): void {
    seoFeaturePage('indexable', ['title' => ['en' => 'Indexable']]);
    seoFeaturePage('private-page', ['title' => ['en' => 'Private'], 'metadata' => ['published_locales' => ['en'], 'noindex' => true]]);

    $this->get('/private-page')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->get('/indexable')
        ->assertOk()
        ->assertSee('content="index, follow', false)
        ->assertDontSee('noindex', false);
});
