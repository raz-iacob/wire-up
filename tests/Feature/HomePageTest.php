<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Settings;

function publishHomeCandidate(string $slug, array $attributes = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('renders the seeded home page at the site root by default', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Welcome to our website!');
});

it('renders the configured homepage at the site root', function (): void {
    $page = publishHomeCandidate('landing', ['title' => 'Landing Page', 'description' => 'Our landing.']);
    Settings::set(['home_page_id' => $page->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Landing Page')
        ->assertSee('Our landing.');
});

it('returns 404 at the site root when no homepage can be resolved', function (): void {
    Page::query()->delete();

    $this->get(route('home'))->assertNotFound();
});

it('redirects the homepage slug to the site root with a 301', function (): void {
    $response = $this->get(route('page', ['slug' => 'home']));

    $response->assertRedirect(route('home'));
    expect($response->getStatusCode())->toBe(301);
});

it('redirects the configured homepage slug, and leaves the old one reachable', function (): void {
    $page = publishHomeCandidate('landing', ['title' => 'Landing Page']);
    Settings::set(['home_page_id' => $page->id]);

    $this->get(route('page', ['slug' => 'landing']))
        ->assertStatus(301)
        ->assertRedirect(route('home'));

    $this->get(route('page', ['slug' => 'home']))->assertOk();
});

it('does not redirect a normal page', function (): void {
    publishHomeCandidate('about', ['title' => 'About Us']);

    $this->get(route('page', ['slug' => 'about']))
        ->assertOk()
        ->assertSee('About Us');
});
