<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Settings;

it('renders a toggleable sidebar menu without javascript errors', function (): void {
    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => true, 'position' => 'left', 'sticky' => false, 'mobile' => 'toggle'],
        'items' => ['en' => [
            ['type' => 'heading', 'appearance' => 'link', 'target' => '_self', 'label' => 'Guides', 'url' => ''],
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => 'https://example.com/install'],
        ]],
    ]]]);

    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en'], 'layout' => ['sidebar' => ['menus' => ['docs-nav']]]],
        'title' => 'Handbook',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'handbook']);

    $visit = visit(route('page', 'handbook'));
    $visit->wait(0.4)
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Installation');
});
