<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;

function publishPage(string $slug, array $attributes = []): Page
{
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('can render the page screen', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('page', ['slug' => 'home']));

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
