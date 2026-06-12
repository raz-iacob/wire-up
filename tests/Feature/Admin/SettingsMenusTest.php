<?php

declare(strict_types=1);

use App\Enums\PageStatus;
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
        'label' => ['en' => 'Home'],
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

it('starts with empty header and footer menus when nothing is saved', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->assertSet('header', [])
        ->assertSet('footer', []);
});

it('hydrates menus from metadata on mount', function (): void {
    Settings::current()->update(['metadata' => [
        'header_menu' => [['type' => 'link', 'appearance' => 'button', 'target' => '_blank', 'label' => ['en' => 'Docs'], 'url' => 'https://example.com']],
    ]]);

    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-menus');

    expect($component->get('header'))->toHaveCount(1)
        ->and($component->get('header')[0])->toMatchArray([
            'type' => 'link',
            'appearance' => 'button',
            'target' => '_blank',
            'url' => 'https://example.com',
        ])
        ->and($component->get('header')[0]['label']['en'])->toBe('Docs');
});

it('adds a header menu item', function (): void {
    $this->actingAsAdmin();

    $header = Livewire::test('pages::admin.settings-menus')
        ->call('addItem', 'header')
        ->get('header');

    expect($header)->toHaveCount(1)
        ->and($header[0]['type'])->toBe('page');
});

it('removes a menu item', function (): void {
    $this->actingAsAdmin();

    $header = Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['_key' => '0']), menuItem(['_key' => '1', 'label' => ['en' => 'About']])])
        ->call('removeItem', 'header', 0)
        ->get('header');

    expect($header)->toHaveCount(1)
        ->and($header[0]['label']['en'])->toBe('About');
});

it('persists header and footer menus to metadata', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['page_id' => $page->id])])
        ->set('footer', [menuItem(['type' => 'link', 'label' => ['en' => 'Privacy'], 'url' => 'https://example.com/privacy'])])
        ->call('update')
        ->assertHasNoErrors();

    $metadata = Settings::current()->fresh()->metadata;

    expect($metadata['header_menu'][0])->toMatchArray(['type' => 'page', 'page_id' => $page->id])
        ->and($metadata['footer_menu'][0])->toMatchArray(['type' => 'link', 'url' => 'https://example.com/privacy']);
});

it('strips ui-only keys when saving', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['page_id' => $page->id])])
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::current()->fresh()->metadata['header_menu'][0])
        ->not->toHaveKey('_key')
        ->not->toHaveKey('open');
});

it('validates type, appearance and target are known values', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['type' => 'bogus', 'appearance' => 'bogus', 'target' => 'bogus'])])
        ->call('update')
        ->assertHasErrors(['header.0.type', 'header.0.appearance', 'header.0.target']);
});

it('requires a label in the default locale', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['page_id' => $page->id, 'label' => ['en' => '']])])
        ->call('update')
        ->assertHasErrors(['header.0.label.en']);
});

it('requires a url when the item type is a custom link', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['type' => 'link', 'url' => ''])])
        ->call('update')
        ->assertHasErrors(['header.0.url']);
});

it('requires a page to be selected when the item type is page', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['type' => 'page', 'page_id' => null])])
        ->call('update')
        ->assertHasErrors(['header.0.page_id']);
});

it('requires an existing page when the item type is page', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['type' => 'page', 'page_id' => 999999])])
        ->call('update')
        ->assertHasErrors(['header.0.page_id']);
});

it('reorders header menu items via the drag handle', function (): void {
    $this->actingAsAdmin();

    $header = Livewire::test('pages::admin.settings-menus')
        ->set('header', [menuItem(['_key' => '0']), menuItem(['_key' => '1', 'label' => ['en' => 'About']])])
        ->call('reorderHeader', '1', 0)
        ->get('header');

    expect($header[0]['_key'])->toBe('1')
        ->and($header[1]['_key'])->toBe('0');
});
