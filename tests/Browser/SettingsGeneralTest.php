<?php

declare(strict_types=1);

it('renders the general settings screen with the languages pillbox and no js errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-general'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Languages');
});

it('lets the languages pillbox select multiple locales (appends rather than replaces)', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-general'));
    $page->wait(0.4);

    $selected = "JSON.stringify(window.Livewire.all().find(c => c.\$wire.get('languages') !== undefined).\$wire.get('languages'))";

    $page->assertScript($selected, '["en"]');

    $page->script("document.querySelector('ui-pillbox')?.click(); void 0");
    $page->wait(0.4);
    $page->script("[...document.querySelectorAll('ui-option')].find(o => o.getAttribute('value') === 'nl')?.click(); void 0");
    $page->wait(0.6);

    $page->assertNoJavascriptErrors()
        ->assertScript($selected, '["en","nl"]');
});
