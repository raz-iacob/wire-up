<?php

declare(strict_types=1);

use App\Models\Settings;

it('renders the public header and footer without javascript errors', function (): void {
    Settings::set([
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
