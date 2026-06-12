<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Settings;

it('renders the menus screen and reactively shows an added item with no js errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Header')
        ->assertSee('Footer');

    $page->script("window.Livewire.all().find(c => c.\$wire.get('header') !== undefined).\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertScript("document.querySelectorAll('[wire\\\\:sort\\\\:item]').length >= 1", true);
});

it('shows the page-picker placeholder for a new item instead of a phantom first page', function (): void {
    Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $page->script("window.Livewire.all().find(c => c.\$wire.get('header') !== undefined).\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertSee('Choose a page...')
        ->assertScript("window.Livewire.all().find(c => c.\$wire.get('header') !== undefined).\$wire.get('header')[0].page_id === null", true);
});

it('reorders header items through the wire:sort handler the way a real drag does', function (): void {
    Settings::current()->update(['metadata' => ['header_menu' => [
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => ['en' => 'Alpha'], 'url' => 'https://a.test'],
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => ['en' => 'Bravo'], 'url' => 'https://b.test'],
    ]]]);

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $titles = "Array.from(document.querySelectorAll('[data-test=menu-item-title]')).map(e => e.textContent.trim()).join(',')";

    $page->assertNoJavascriptErrors()
        ->assertScript($titles, 'Alpha,Bravo');

    $page->script("
        const el = document.querySelectorAll('[wire\\\\:sort\\\\:item]')[1];
        window.Livewire.all().find(c => c.\$wire.get('header') !== undefined).\$wire.reorderHeader(el._x_sort_key, 0);
        void 0;
    ");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertScript($titles, 'Bravo,Alpha');
});
