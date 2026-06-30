<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;

$findComponent = "window.Livewire.all().find(c => c.\$wire.get('menus') !== undefined)";

it('renders the menus screen and reactively shows an added item with no js errors', function () use ($findComponent): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Header')
        ->assertSee('Footer');

    $page->script("$findComponent.\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertScript("document.querySelectorAll('[wire\\\\:sort\\\\:item]').length >= 1", true);
});

it('shows the page-picker placeholder for a new item instead of a phantom first page', function () use ($findComponent): void {
    Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $page->script("$findComponent.\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertSee('Choose a page...')
        ->assertScript("JSON.parse(JSON.stringify($findComponent.\$wire.get('menus'))).find(m => m.key === 'header').items.en[0].page_id === null", true);
});

it('lets an admin add a custom menu and save it under the menus key', function () use ($findComponent): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));
    $page->wait(0.4);

    $page->script("$findComponent.\$wire.call('addMenu'); void 0");
    $page->wait(0.4);

    $page->script("
        const menus = $findComponent.\$wire.get('menus');
        $findComponent.\$wire.set('menus.' + (menus.length - 1) + '.name', 'Docs nav');
        void 0;
    ");
    $page->wait(0.4);
    $page->script("$findComponent.\$wire.call('update'); void 0");
    $page->wait(0.6);

    $page->assertNoJavascriptErrors();

    $menus = Settings::get('menus');

    expect(collect($menus)->pluck('name'))->toContain('Docs nav')
        ->and(collect($menus)->firstWhere('name', 'Docs nav'))
        ->toMatchArray(['builtin' => false]);
});

it('updates the item title live as the label is typed, before saving', function () use ($findComponent): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $page->script("$findComponent.\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.4);

    $page->script("
        const input = Array.from(document.querySelectorAll('input')).find(i => (i.getAttribute('wire:model') || '').endsWith('.label'));
        input.value = 'Live label';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        void 0;
    ");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertScript("Array.from(document.querySelectorAll('[data-test=menu-item-title]')).some(e => e.textContent.trim() === 'Live label')", true);
});

it('stays clean when only the edit-in locale is switched, but dirties on a real change', function () use ($findComponent): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));
    $page->wait(0.4);

    $beforeunloadPrevented = "(() => { const e = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; })()";

    $page->assertScript($beforeunloadPrevented, false);

    $page->script("$findComponent.\$wire.set('locale', 'nl'); void 0");
    $page->wait(0.6);
    $page->assertScript($beforeunloadPrevented, false);

    $page->script("$findComponent.\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.6);
    $page->assertNoJavascriptErrors()
        ->assertScript($beforeunloadPrevented, true);
});

it('opens a confirmation modal before removing a menu item', function () use ($findComponent): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $page->script("$findComponent.\$wire.call('addItem', 'header'); void 0");
    $page->wait(0.4);
    $page->script("$findComponent.\$wire.call('confirmRemove', 'header', 0); void 0");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertSee('Remove menu item?');
});

it('reveals a hidden error by switching to its locale and expanding the item', function () use ($findComponent): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));
    $page->wait(0.4);

    $page->script("
        $findComponent.\$wire.set('menus.1.items.nl', [{ _key: '99', type: 'link', appearance: 'link', target: '_self', label: 'Bad', page_id: null, url: 'nope', open: false }]);
        void 0;
    ");
    $page->wait(0.4);

    $page->script("$findComponent.\$wire.call('update'); void 0");
    $page->wait(0.6);

    $page->assertNoJavascriptErrors()
        ->assertScript("$findComponent.\$wire.get('locale')", 'nl')
        ->assertScript("Array.from(document.querySelectorAll('input')).some(i => i.value === 'nope' && i.offsetParent !== null)", true);
});

it('keeps the menus nav pill selected after the editing locale changes', function () use ($findComponent): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));
    $page->wait(0.4);

    $menuPillSelected = "Array.from(document.querySelectorAll('[data-flux-tab][data-selected]')).some(e => e.textContent.trim() === 'Menus')";

    $page->assertScript($menuPillSelected, true);

    $page->script("$findComponent.\$wire.dispatch('change-locale'); void 0");
    $page->wait(0.6);

    $page->assertNoJavascriptErrors()
        ->assertScript("$findComponent.\$wire.get('locale')", 'nl')
        ->assertScript($menuPillSelected, true);
});

it('keeps each item\'s field values matched to its row after a drag reorder', function () use ($findComponent): void {
    Settings::set(['menus' => [
        ['key' => 'header', 'name' => 'Header', 'builtin' => true, 'title' => [], 'display' => [], 'items' => ['en' => []]],
        ['key' => 'footer', 'name' => 'Footer', 'builtin' => true, 'title' => [], 'display' => [], 'items' => ['en' => []]],
        ['key' => 'sidebar', 'name' => 'Sidebar', 'builtin' => false, 'title' => [], 'display' => [], 'items' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => 'https://a.test', 'icon' => '', 'badge' => '', 'badgeColor' => 'zinc'],
            ['type' => 'heading', 'appearance' => 'link', 'target' => '_self', 'label' => 'Getting Started', 'url' => '', 'icon' => '', 'badge' => '', 'badgeColor' => 'zinc'],
        ]]],
    ]]);

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));
    $page->wait(0.5);

    $page->drag('[wire\\:sort\\:item="1"] [wire\\:sort\\:handle]', '[wire\\:sort\\:item="0"]');
    $page->wait(0.9);

    $page->assertNoJavascriptErrors()
        ->assertScript("$findComponent.\$wire.get('menus').find(m => m.key === 'sidebar').items.en.map(i => i.type).join(',')", 'heading,link')
        ->assertScript("document.querySelector('[wire\\\\:sort\\\\:item=\"1\"]').querySelector('select').value", 'heading')
        ->assertScript("document.querySelector('[wire\\\\:sort\\\\:item=\"0\"]').querySelector('select').value", 'link');
});

it('reorders header items through the wire:sort handler the way a real drag does', function () use ($findComponent): void {
    Settings::set(['menus' => menusPayload(['header' => ['en' => [
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Alpha', 'url' => 'https://a.test'],
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Bravo', 'url' => 'https://b.test'],
    ]]])]);

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-menus'));

    $titles = "Array.from(document.querySelectorAll('[data-test=menu-item-title]')).map(e => e.textContent.trim()).join(',')";

    $page->assertNoJavascriptErrors()
        ->assertScript($titles, 'Alpha,Bravo');

    $page->script("
        const el = document.querySelectorAll('[wire\\\\:sort\\\\:item]')[1];
        $findComponent.\$wire.reorder(el._x_sort_key, 0);
        void 0;
    ");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertScript($titles, 'Bravo,Alpha');
});
