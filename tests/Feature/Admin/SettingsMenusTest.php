<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;
use App\Models\User;
use Livewire\Livewire;

function menuItem(array $overrides = []): array
{
    return array_merge([
        '_key' => '0',
        'type' => 'page',
        'appearance' => 'link',
        'target' => '_self',
        'label' => 'Home',
        'page_id' => null,
        'url' => '',
        'icon' => '',
        'badge' => '',
        'badgeColor' => 'zinc',
        'open' => true,
    ], $overrides);
}

function savedMenuItems(string $key, string $locale = 'en'): array
{
    return collect(Settings::get('menus'))->firstWhere('key', $key)['items'][$locale] ?? [];
}

it('keeps the locale from the query string instead of resetting to the default', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    Livewire::withQueryParams(['locale' => 'nl'])
        ->test('pages::admin.settings-menus')
        ->assertSet('locale', 'nl');
});

it('falls back to the default locale when the query string locale is not active', function (): void {
    $this->actingAsAdmin();

    Livewire::withQueryParams(['locale' => 'ro'])
        ->test('pages::admin.settings-menus')
        ->assertSet('locale', 'en');
});

it('cycles to the next active locale on the change-locale event', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->assertSet('locale', 'en')
        ->call('changeLocale')
        ->assertSet('locale', 'nl')
        ->call('changeLocale')
        ->assertSet('locale', 'en');
});

it('can render the menus settings screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-menus'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-menus');
});

it('redirects authenticated non-admin users away from menus settings', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'admin' => false]);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.settings-menus'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from menus settings', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-menus'))
        ->assertRedirectToRoute('login');
});

it('always exposes the header and footer built-ins, empty, when nothing is saved', function (): void {
    $this->actingAsAdmin();

    $menus = Livewire::test('pages::admin.settings-menus')->get('menus');

    expect(collect($menus)->pluck('key')->all())->toBe(['header', 'footer'])
        ->and($menus[0]['items']['en'])->toBe([])
        ->and($menus[1]['items']['en'])->toBe([]);
});

it('hydrates the locale menus from metadata on mount', function (): void {
    Settings::set(['menus' => menusPayload([
        'header' => ['en' => [['type' => 'link', 'appearance' => 'button', 'target' => '_blank', 'label' => 'Docs', 'url' => 'https://example.com']]],
    ])]);

    $this->actingAsAdmin();

    $menus = Livewire::test('pages::admin.settings-menus')->get('menus');

    expect($menus[0]['items']['en'])->toHaveCount(1)
        ->and($menus[0]['items']['en'][0])->toMatchArray([
            'type' => 'link',
            'appearance' => 'button',
            'target' => '_blank',
            'label' => 'Docs',
            'url' => 'https://example.com',
        ]);
});

it('adds a header menu item to the current locale', function (): void {
    $this->actingAsAdmin();

    $menus = Livewire::test('pages::admin.settings-menus')
        ->call('addItem', 'header')
        ->get('menus');

    expect($menus[0]['items']['en'])->toHaveCount(1)
        ->and($menus[0]['items']['en'][0]['type'])->toBe('page');
});

it('keeps menus separate per language', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $menus = Livewire::test('pages::admin.settings-menus')
        ->call('addItem', 'header')
        ->get('menus');

    expect($menus[0]['items']['en'])->toHaveCount(1)
        ->and($menus[0]['items']['nl'])->toHaveCount(0);
});

it('adds a custom menu with its own items for each active locale', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $menus = Livewire::test('pages::admin.settings-menus')
        ->call('addMenu')
        ->get('menus');

    expect($menus)->toHaveCount(3)
        ->and($menus[2]['builtin'])->toBeFalse()
        ->and($menus[2]['items'])->toHaveKeys(['en', 'nl']);
});

it('persists a named custom menu under the menus key', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->call('addMenu')
        ->set('menus.2.name', 'Docs nav')
        ->set('menus.2.items.en', [menuItem(['type' => 'link', 'label' => 'Guide', 'url' => 'https://example.com/guide'])])
        ->call('update')
        ->assertHasNoErrors();

    $custom = collect(Settings::get('menus'))->firstWhere('name', 'Docs nav');

    expect($custom)->not->toBeNull()
        ->and($custom['builtin'])->toBeFalse()
        ->and($custom['items']['en'][0])->toMatchArray(['type' => 'link', 'url' => 'https://example.com/guide']);
});

it('persists a group heading item in a custom menu', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-menus')
        ->call('addMenu')
        ->set('menus.2.name', 'Docs');

    $key = $component->get('menus')[2]['key'];

    $component->call('addItem', $key, 'heading')
        ->set('menus.2.items.en.0.label', 'Guides')
        ->call('update')
        ->assertHasNoErrors();

    $custom = collect(Settings::get('menus'))->firstWhere('name', 'Docs');

    expect($custom['items']['en'][0])->toMatchArray(['type' => 'heading', 'label' => 'Guides']);
});

it('requires a name for a custom menu', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->call('addMenu')
        ->set('menus.2.name', '')
        ->call('update')
        ->assertHasErrors(['menus.2.name'])
        ->assertSee('Give this menu a name.');
});

it('deletes a custom menu once confirmed but leaves built-ins intact', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-menus')
        ->call('addMenu')
        ->set('menus.2.name', 'Temporary');

    $key = $component->get('menus')[2]['key'];

    $component->call('confirmRemoveMenu', $key)
        ->assertSet('showRemoveMenuModal', true)
        ->call('removeMenuConfirmed')
        ->assertSet('showRemoveMenuModal', false);

    expect(collect($component->get('menus'))->pluck('key')->all())->toBe(['header', 'footer']);
});

it('ignores a request to delete a built-in menu', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->call('confirmRemoveMenu', 'header')
        ->assertSet('showRemoveMenuModal', false);
});

it('confirms before removing a menu item', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['_key' => '0'])])
        ->assertSet('showRemoveModal', false)
        ->call('confirmRemove', 'header', 0)
        ->assertSet('showRemoveModal', true)
        ->assertSet('removeMenuKey', 'header')
        ->assertSet('removeIndex', 0);
});

it('removes a menu item from the current locale once confirmed', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['_key' => '0']), menuItem(['_key' => '1', 'label' => 'About'])])
        ->call('confirmRemove', 'header', 0)
        ->call('removeConfirmed')
        ->assertSet('showRemoveModal', false);

    $items = $component->get('menus')[0]['items']['en'];

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('About');
});

it('persists header and footer menus per locale to metadata', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['page_id' => $page->id])])
        ->set('menus.1.items.en', [menuItem(['type' => 'link', 'label' => 'Privacy', 'url' => 'https://example.com/privacy'])])
        ->call('update')
        ->assertHasNoErrors();

    expect(savedMenuItems('header')[0])->toMatchArray(['type' => 'page', 'page_id' => $page->id, 'label' => 'Home'])
        ->and(savedMenuItems('footer')[0])->toMatchArray(['type' => 'link', 'url' => 'https://example.com/privacy']);
});

it('strips ui-only keys when saving', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['page_id' => $page->id])])
        ->call('update')
        ->assertHasNoErrors();

    expect(savedMenuItems('header')[0])
        ->not->toHaveKey('_key')
        ->not->toHaveKey('open');
});

it('validates type, appearance and target are known values', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'bogus', 'appearance' => 'bogus', 'target' => 'bogus'])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.type', 'menus.0.items.en.0.appearance', 'menus.0.items.en.0.target']);
});

it('requires a label', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['page_id' => $page->id, 'label' => ''])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.label']);
});

it('requires a url when the item type is a custom link', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'link', 'url' => ''])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.url']);
});

it('accepts anchor and path links for a custom link', function (string $url): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'link', 'label' => 'Contact', 'url' => $url])])
        ->call('update')
        ->assertHasNoErrors();

    expect(savedMenuItems('header')[0]['url'])->toBe($url);
})->with([
    'anchor' => '#contact',
    'path' => '/about',
    'path with anchor' => '/about#team',
    'absolute url' => 'https://example.com/privacy',
]);

it('rejects a url that is not a link, path or anchor', function (string $url): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'link', 'label' => 'Bad', 'url' => $url])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.url']);
})->with([
    'bare word' => 'contact',
    'spaces' => '/foo bar',
    'unsupported scheme' => 'javascript:alert(1)',
]);

it('switches to the errored locale and expands the offending item on save', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->assertSet('locale', 'en')
        ->set('menus.1.items.fr', [menuItem(['type' => 'link', 'label' => 'Bad', 'url' => 'nope', 'open' => false])])
        ->call('update')
        ->assertHasErrors(['menus.1.items.fr.0.url'])
        ->assertSet('locale', 'fr')
        ->assertSet('menus.1.items.fr.0.open', true)
        ->assertDispatched('menu-errors-revealed');
});

it('stays on the current locale when it already shows an error', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('locale', 'fr')
        ->set('menus.1.items.fr', [menuItem(['type' => 'link', 'label' => 'Bad', 'url' => 'nope'])])
        ->set('menus.0.items.en', [menuItem(['type' => 'link', 'label' => 'Also bad', 'url' => 'nope'])])
        ->call('update')
        ->assertHasErrors(['menus.1.items.fr.0.url', 'menus.0.items.en.0.url'])
        ->assertSet('locale', 'fr');
});

it('shows friendly validation messages instead of raw field paths', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'page', 'page_id' => null])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.page_id'])
        ->assertSee('Choose a page for this menu item.')
        ->assertDontSee('menus.0.items.en.0.page_id field is required');
});

it('shows a friendly message for an invalid custom link url', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'link', 'label' => 'Bad', 'url' => 'nope'])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.url'])
        ->assertSee('Enter a full URL');
});

it('requires a page to be selected when the item type is page', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'page', 'page_id' => null])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.page_id']);
});

it('requires an existing page when the item type is page', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'page', 'page_id' => 999999])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.page_id']);
});

it('persists the sidebar display settings for a menu', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->call('addMenu')
        ->set('menus.2.name', 'Docs nav')
        ->set('menus.2.display.background', false)
        ->set('menus.2.display.position', 'right')
        ->set('menus.2.display.sticky', true)
        ->set('menus.2.display.mobile', 'hide')
        ->call('update')
        ->assertHasNoErrors();

    $custom = collect(Settings::get('menus'))->firstWhere('name', 'Docs nav');

    expect($custom['display'])->toMatchArray([
        'background' => false,
        'position' => 'right',
        'sticky' => true,
        'mobile' => 'hide',
    ]);
});

it('persists an icon and badge for a menu item', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'link', 'label' => 'Docs', 'url' => 'https://example.com', 'icon' => 'book-open', 'badge' => 'New', 'badgeColor' => 'green'])])
        ->call('update')
        ->assertHasNoErrors();

    expect(savedMenuItems('header')[0])->toMatchArray([
        'icon' => 'book-open',
        'badge' => 'New',
        'badgeColor' => 'green',
    ]);
});

it('rejects an icon that is not in the allow-list', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['type' => 'link', 'label' => 'Docs', 'url' => 'https://example.com', 'icon' => 'definitely-not-an-icon'])])
        ->call('update')
        ->assertHasErrors(['menus.0.items.en.0.icon']);
});

it('reorders header menu items via the drag handle', function (): void {
    $this->actingAsAdmin();

    $menus = Livewire::test('pages::admin.settings-menus')
        ->set('menus.0.items.en', [menuItem(['_key' => '0']), menuItem(['_key' => '1', 'label' => 'About'])])
        ->call('reorder', '1', 0)
        ->get('menus');

    expect($menus[0]['items']['en'][0]['_key'])->toBe('1')
        ->and($menus[0]['items']['en'][1]['_key'])->toBe('0');
});
