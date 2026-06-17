<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Locale;
use App\Models\Page;

function publishPage(string $slug, array $attributes = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('can render the page screen', function (): void {
    publishPage('about');

    $response = $this->get(route('page', ['slug' => 'about']));

    $response->assertOk()
        ->assertSeeLivewire('pages::page');
});

it('returns 404 if the page does not exist', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('page', ['slug' => 'non-existent-page']));

    $response->assertNotFound();
});

it('renders the published page title and description', function (): void {
    publishPage('about', ['title' => 'About Us', 'description' => 'Who we are.']);

    $this->get(route('page', 'about'))
        ->assertOk()
        ->assertSee('About Us')
        ->assertSee('Who we are.');
});

it('returns 404 for a draft page', function (): void {
    publishPage('secret', ['status' => PageStatus::DRAFT, 'published_at' => null]);

    $this->get(route('page', 'secret'))->assertNotFound();
});

it('returns 404 for a scheduled page whose publish date is in the future', function (): void {
    publishPage('coming-soon', ['published_at' => now()->addWeek()]);

    $this->get(route('page', 'coming-soon'))->assertNotFound();
});

it('returns 404 for a page that is not published in the current locale', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    cache()->forget('site-locales');

    publishPage('hidden', ['metadata' => ['published_locales' => ['fr']]]);

    $this->get(route('page', 'hidden'))->assertNotFound();
});

it('renders a page that is published in the current locale', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    cache()->forget('site-locales');

    publishPage('visible', ['title' => 'Visible', 'metadata' => ['published_locales' => ['en']]]);

    $this->get(route('page', 'visible'))
        ->assertOk()
        ->assertSee('Visible');
});

it('does not gate by locale when the site has a single language', function (): void {
    publishPage('single', ['title' => 'Single', 'metadata' => ['published_locales' => ['fr']]]);

    $this->get(route('page', 'single'))
        ->assertOk()
        ->assertSee('Single');
});
