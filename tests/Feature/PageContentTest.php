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
        ->assertDontSee('background-image', false);
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
