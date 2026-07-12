<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Settings;

it('keeps the dark class across wire:navigate when the system prefers dark', function (): void {
    $page = visit('/login')->inDarkMode();

    $page->assertScript("document.documentElement.classList.contains('dark')", true)
        ->click('Forgot your password?')
        ->assertPathIs('/forgot-password')
        ->assertScript("document.documentElement.classList.contains('dark')", true);
});

it('renders the public header and footer without javascript errors', function (): void {
    $home = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Chrome Home',
    ]);
    $home->slugs()->create(['locale' => 'en', 'slug' => 'chrome-home']);

    Settings::set([
        'home_page_id' => $home->id,
        'menus' => menusPayload([
            'header' => ['en' => [
                ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Docs', 'page_id' => null, 'url' => 'https://example.com/docs'],
                ['type' => 'link', 'appearance' => 'button', 'target' => '_self', 'label' => 'Sign up', 'page_id' => null, 'url' => 'https://example.com/signup'],
            ]],
            'footer' => ['en' => [
                ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Privacy', 'page_id' => null, 'url' => 'https://example.com/privacy'],
            ]],
        ]),
        'social' => ['facebook' => 'https://facebook.com/wireup', 'x' => 'https://x.com/wireup'],
    ]);

    $page = visit('/');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->assertPresent('[data-site-header]')
        ->assertPresent('[data-site-footer]')
        ->assertSee('Docs')
        ->assertSee('Made with Wire-Up');
});
