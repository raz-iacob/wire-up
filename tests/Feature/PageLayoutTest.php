<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;

function publishPageWithLayout(string $slug, array $layout): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en'], 'layout' => $layout],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ucfirst($slug),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('shows the header and footer by default', function (): void {
    publishPageWithLayout('with-chrome', []);

    $this->get(route('page', 'with-chrome'))
        ->assertOk()
        ->assertSee('<header', false)
        ->assertSee('<footer', false);
});

it('hides the header and footer when configured', function (): void {
    publishPageWithLayout('no-chrome', ['hideHeader' => true, 'hideFooter' => true]);

    $this->get(route('page', 'no-chrome'))
        ->assertOk()
        ->assertDontSee('<header', false)
        ->assertDontSee('<footer', false);
});

it('applies a background color', function (): void {
    publishPageWithLayout('styled', ['backgroundColor' => '#101820']);

    $this->get(route('page', 'styled'))
        ->assertOk()
        ->assertSee('background-color:#101820', false);
});

it('injects custom css and neutralizes style-tag breakout', function (): void {
    publishPageWithLayout('css', [
        'customCss' => '.promo { color: #bada55; } </style><script>alert(1)</script>',
    ]);

    $this->get(route('page', 'css'))
        ->assertOk()
        ->assertSee('.promo { color: #bada55; }', false)
        ->assertDontSee('</style><script>', false);
});

it('applies a fixed background image', function (): void {
    publishPageWithLayout('bg-image', [
        'backgroundImage' => ['source' => 'media/bg.jpg', 'crop' => []],
        'backgroundFixed' => true,
    ]);

    $this->get(route('page', 'bg-image'))
        ->assertOk()
        ->assertSee('background-image:url(', false)
        ->assertSee('media/bg.jpg', false)
        ->assertSee('background-attachment:fixed', false)
        ->assertSee('bg-cover', false);
});

it('includes the crop parameters in the background image url', function (): void {
    publishPageWithLayout('bg-cropped', [
        'backgroundImage' => [
            'source' => 'media/bg.jpg',
            'crop' => ['default' => ['crop_w' => 1600, 'crop_h' => 900, 'crop_x' => 10, 'crop_y' => 20]],
        ],
    ]);

    $this->get(route('page', 'bg-cropped'))
        ->assertOk()
        ->assertSee('crop=1600-900-10-20', false);
});

it('omits the fixed attachment when no image is set', function (): void {
    publishPageWithLayout('color-only', ['backgroundColor' => '#222222', 'backgroundFixed' => true]);

    $this->get(route('page', 'color-only'))
        ->assertOk()
        ->assertSee('background-color:#222222', false)
        ->assertDontSee('background-attachment:fixed', false);
});
