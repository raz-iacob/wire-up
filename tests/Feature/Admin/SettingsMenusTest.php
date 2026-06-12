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
        'open' => true,
    ], $overrides);
}

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

it('starts with an empty menu for each active locale when nothing is saved', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->assertSet('header', ['en' => []])
        ->assertSet('footer', ['en' => []]);
});

it('hydrates the locale menus from metadata on mount', function (): void {
    Settings::current()->update(['metadata' => [
        'header_menu' => ['en' => [['type' => 'link', 'appearance' => 'button', 'target' => '_blank', 'label' => 'Docs', 'url' => 'https://example.com']]],
    ]]);

    $this->actingAsAdmin();

    $header = Livewire::test('pages::admin.settings-menus')->get('header');

    expect($header['en'])->toHaveCount(1)
        ->and($header['en'][0])->toMatchArray([
            'type' => 'link',
            'appearance' => 'button',
            'target' => '_blank',
            'label' => 'Docs',
            'url' => 'https://example.com',
        ]);
});

it('adds a header menu item to the current locale', function (): void {
    $this->actingAsAdmin();

    $header = Livewire::test('pages::admin.settings-menus')
        ->call('addItem', 'header')
        ->get('header');

    expect($header['en'])->toHaveCount(1)
        ->and($header['en'][0]['type'])->toBe('page');
});

it('keeps menus separate per language', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $header = Livewire::test('pages::admin.settings-menus')
        ->call('addItem', 'header')
        ->get('header');

    expect($header['en'])->toHaveCount(1)
        ->and($header['nl'])->toHaveCount(0);
});

it('confirms before removing a menu item', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['_key' => '0'])]])
        ->assertSet('showRemoveModal', false)
        ->call('confirmRemove', 'header', 0)
        ->assertSet('showRemoveModal', true)
        ->assertSet('removeMenu', 'header')
        ->assertSet('removeIndex', 0);
});

it('removes a menu item from the current locale once confirmed', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['_key' => '0']), menuItem(['_key' => '1', 'label' => 'About'])]])
        ->call('confirmRemove', 'header', 0)
        ->call('removeConfirmed')
        ->assertSet('showRemoveModal', false);

    $header = $component->get('header');

    expect($header['en'])->toHaveCount(1)
        ->and($header['en'][0]['label'])->toBe('About');
});

it('persists header and footer menus per locale to metadata', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['page_id' => $page->id])]])
        ->set('footer', ['en' => [menuItem(['type' => 'link', 'label' => 'Privacy', 'url' => 'https://example.com/privacy'])]])
        ->call('update')
        ->assertHasNoErrors();

    $metadata = Settings::current()->fresh()->metadata;

    expect($metadata['header_menu']['en'][0])->toMatchArray(['type' => 'page', 'page_id' => $page->id, 'label' => 'Home'])
        ->and($metadata['footer_menu']['en'][0])->toMatchArray(['type' => 'link', 'url' => 'https://example.com/privacy']);
});

it('strips ui-only keys when saving', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['page_id' => $page->id])]])
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::current()->fresh()->metadata['header_menu']['en'][0])
        ->not->toHaveKey('_key')
        ->not->toHaveKey('open');
});

it('validates type, appearance and target are known values', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['type' => 'bogus', 'appearance' => 'bogus', 'target' => 'bogus'])]])
        ->call('update')
        ->assertHasErrors(['header.en.0.type', 'header.en.0.appearance', 'header.en.0.target']);
});

it('requires a label', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['page_id' => $page->id, 'label' => ''])]])
        ->call('update')
        ->assertHasErrors(['header.en.0.label']);
});

it('requires a url when the item type is a custom link', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['type' => 'link', 'url' => ''])]])
        ->call('update')
        ->assertHasErrors(['header.en.0.url']);
});

it('requires a page to be selected when the item type is page', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['type' => 'page', 'page_id' => null])]])
        ->call('update')
        ->assertHasErrors(['header.en.0.page_id']);
});

it('requires an existing page when the item type is page', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['type' => 'page', 'page_id' => 999999])]])
        ->call('update')
        ->assertHasErrors(['header.en.0.page_id']);
});

it('reorders header menu items via the drag handle', function (): void {
    $this->actingAsAdmin();

    $header = Livewire::test('pages::admin.settings-menus')
        ->set('header', ['en' => [menuItem(['_key' => '0']), menuItem(['_key' => '1', 'label' => 'About'])]])
        ->call('reorderHeader', '1', 0)
        ->get('header');

    expect($header['en'][0]['_key'])->toBe('1')
        ->and($header['en'][1]['_key'])->toBe('0');
});
