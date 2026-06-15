<?php

declare(strict_types=1);

use App\Models\Locale;

it('renders the identity settings screen with no js errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-identity'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Title')
        ->assertSee('Tagline');
});

it('switches to the failing locale and shows the error when a hidden locale title is missing', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-identity'));
    $page->wait(0.4);

    $comp = "window.Livewire.all().find(c => c.\$wire.get('locale') !== undefined)";

    $page->assertScript("$comp.\$wire.get('locale')", 'en')
        ->assertPresent('input[name="title.en"]');

    $page->script("$comp.\$wire.set('title.en', 'Valid Title'); $comp.\$wire.call('update'); void 0");
    $page->wait(0.8);

    $page->assertNoJavascriptErrors()
        ->assertScript("$comp.\$wire.get('locale')", 'nl')
        ->assertPresent('input[name="title.nl"]')
        ->assertSee('Enter a title for the selected language.');
});
