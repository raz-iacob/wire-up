<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;

function richTextPage(string $slug, array $content): Page
{
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);
    $page->blocks()->create([
        'type' => BlockType::RICH_TEXT,
        'position' => 0,
        'content' => [...$content, 'body' => ['en' => '<p>Body copy.</p>']],
    ]);

    return $page;
}

it('keeps a narrow column flush left', function (): void {
    $this->get('/'.richTextPage('flush-left', ['width' => 'narrow', 'align' => 'left'])->getSlug())
        ->assertOk()
        ->assertSee('max-w-2xl', false)
        ->assertDontSee('max-w-2xl mx-auto', false)
        ->assertDontSee('max-w-2xl ms-auto', false);
});

it('centres a narrow column', function (): void {
    $this->get('/'.richTextPage('centred', ['width' => 'narrow', 'align' => 'center'])->getSlug())
        ->assertOk()
        ->assertSee('max-w-2xl mx-auto', false);
});

it('pushes a narrow column to the right', function (): void {
    $this->get('/'.richTextPage('right', ['width' => 'narrow', 'align' => 'right'])->getSlug())
        ->assertOk()
        ->assertSee('max-w-2xl ms-auto', false);
});

it('never centres the text itself, which the editor toolbar handles', function (): void {
    $this->get('/'.richTextPage('no-text-center', ['width' => 'normal', 'align' => 'center'])->getSlug())
        ->assertOk()
        ->assertSee('<div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">', false);
});

it('keeps toolbar text alignment in the body', function (): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'toolbar-align']);
    $page->updateBlocks([[
        'id' => 'new-1',
        'type' => 'rich-text',
        'content' => ['body' => ['en' => '<p style="text-align: center">Centred by the toolbar.</p>']],
    ]]);

    $this->get('/toolbar-align')->assertOk()->assertSee('text-align: center', false);
});

it('falls back to a left column for an alignment it does not know', function (): void {
    $this->get('/'.richTextPage('unknown-align', ['width' => 'narrow', 'align' => ['not', 'a', 'string']])->getSlug())
        ->assertOk()
        ->assertDontSee('max-w-2xl mx-auto', false)
        ->assertDontSee('max-w-2xl ms-auto', false);
});
