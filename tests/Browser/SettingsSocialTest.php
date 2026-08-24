<?php

declare(strict_types=1);

it('renders the social settings screen with all platform fields and no js errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-social'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Facebook URL')
        ->assertSee('LinkedIn URL')
        ->assertSee('X (Twitter) URL')
        ->assertSee('YouTube URL')
        ->assertSee('Instagram URL')
        ->assertSee('TikTok URL');
});

it('enables the open-in-a-new-tab arrow on a url that was already saved', function (): void {
    config(['site.social' => ['github' => 'https://github.com/raz-iacob/wire-up']]);

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-social'));

    $enabled = "(() => [...document.querySelectorAll('button[aria-label=\"Open in a new tab\"]')].filter(b => ! b.disabled).length)()";

    expect($page->script($enabled))->toBe(1);
});

it('keeps the open-in-a-new-tab arrow disabled until a url field holds a url', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-social'));

    $enabled = "(() => [...document.querySelectorAll('button[aria-label=\"Open in a new tab\"]')].filter(b => ! b.disabled).length)()";
    $shown = "(() => document.querySelectorAll('button[aria-label=\"Open in a new tab\"]').length)()";

    $set = fn (string $value): string => "(() => { const i = document.querySelector('input[name=\"links.facebook\"]'); i.value = '{$value}'; i.dispatchEvent(new Event('input', { bubbles: true })); })()";

    expect($page->script($shown))->toBe(7)
        ->and($page->script($enabled))->toBe(0);

    $page->script($set('https://facebook.com/wireup'));
    $page->wait(1);

    expect($page->script($enabled))->toBe(1);

    $page->script($set('not a url'));
    $page->wait(1);

    expect($page->script($enabled))->toBe(0);
});
