<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Locale;
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
        ->assertScript("JSON.parse(JSON.stringify(window.Livewire.all().find(c => c.\$wire.get('header') !== undefined).\$wire.get('header'))).en[0].page_id === null", true);
});

it('stays clean when only the edit-in locale is switched, but dirties on a real change', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));
    $page->wait(0.4);

    $beforeunloadPrevented = "(() => { const e = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; })()";
    $comp = "window.Livewire.all().find(c => c.\$wire.get('header') !== undefined)";

    $page->assertScript($beforeunloadPrevented, false);

    $page->script("$comp.\$wire.set('locale', 'nl'); void 0");
    $page->wait(0.6);
    $page->assertScript($beforeunloadPrevented, false);

    $page->script("$comp.\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.6);
    $page->assertNoJavascriptErrors()
        ->assertScript($beforeunloadPrevented, true);
});

it('opens a confirmation modal before removing a menu item', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $comp = "window.Livewire.all().find(c => c.\$wire.get('header') !== undefined)";
    $page->script("$comp.\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.4);
    $page->script("$comp.\$wire.call('confirmRemove', 'header', 0); void 0");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertSee('Remove menu item?');
});

it('reveals a hidden error by switching to its tab and locale and expanding the item', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));
    $page->wait(0.4);

    $comp = "window.Livewire.all().find(c => c.\$wire.get('header') !== undefined)";

    $page->script("
        $comp.\$wire.set('footer.nl', [{ _key: '99', type: 'link', appearance: 'link', target: '_self', label: 'Bad', page_id: null, url: 'nope', open: false }]);
        void 0;
    ");
    $page->wait(0.4);

    $page->script("$comp.\$wire.call('update'); void 0");
    $page->wait(0.6);

    $page->assertNoJavascriptErrors()
        ->assertScript("$comp.\$wire.get('tab')", 'footer')
        ->assertScript("$comp.\$wire.get('locale')", 'nl')
        ->assertScript("document.querySelector('[data-flux-tab-panel][name=footer]')?.offsetParent !== null", true)
        ->assertScript("document.querySelector('[data-flux-tab-panel][name=footer] [x-show=\"open\"]')?.offsetParent !== null", true);
});

it('reorders header items through the wire:sort handler the way a real drag does', function (): void {
    Settings::set(['header_menu' => ['en' => [
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Alpha', 'url' => 'https://a.test'],
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Bravo', 'url' => 'https://b.test'],
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
