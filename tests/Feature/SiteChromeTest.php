<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;
use Livewire\Livewire;

function setSiteMetadata(array $settings): void
{
    Settings::set($settings);
}

it('renders the header and footer on a public page', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-site-header', false)
        ->assertSee('data-site-footer', false)
        ->assertSee('Made with Wire-Up');
});

it('renders the current locale header menu items with their urls', function (): void {
    setSiteMetadata(['menus' => menusPayload(['header' => ['en' => [
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Documentation', 'page_id' => null, 'url' => 'https://example.com/docs'],
    ]]])]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Documentation')
        ->assertSee('https://example.com/docs', false);
});

it('renders the header menu for the active locale', function (): void {
    setSiteMetadata(['menus' => menusPayload(['header' => [
        'en' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'English item', 'page_id' => null, 'url' => 'https://example.com/en']],
        'nl' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Nederlands item', 'page_id' => null, 'url' => 'https://example.com/nl']],
    ]])]);

    app()->setLocale('nl');

    Livewire::test('site.header')
        ->assertSee('Nederlands item')
        ->assertDontSee('English item');
});

it('applies the configured logo and navigation sizes in the header', function (): void {
    setSiteMetadata([
        'header_logo_size' => 'lg',
        'header_nav_size' => 'lg',
        'header_nav_hover' => 'scale',
        'menus' => menusPayload(['header' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'About', 'page_id' => null, 'url' => 'https://example.com'],
        ]]]),
    ]);

    Livewire::test('site.header')
        ->assertSee('text-xl', false)
        ->assertSee('text-lg', false)
        ->assertSee('hover:scale-105', false);
});

it('shows the language switcher in the header when more than one locale is active', function (): void {
    Locale::query()->where('code', 'ro')->update(['active' => true]);
    cache()->forget('site-locales');

    Livewire::test('site.header')
        ->assertSee('data-flux-dropdown', false)
        ->assertSee('English')
        ->assertSee('Română');
});

it('omits the language switcher when only one locale is active', function (): void {
    Livewire::test('site.header')
        ->assertDontSee('data-flux-dropdown', false);
});

it('renders social links only for the platforms that are set', function (): void {
    setSiteMetadata(['social' => [
        'facebook' => 'https://facebook.com/wireup',
        'x' => 'https://x.com/wireup',
    ]]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('https://facebook.com/wireup', false)
        ->assertSee('https://x.com/wireup', false)
        ->assertDontSee('aria-label="Tiktok"', false)
        ->assertDontSee('aria-label="Youtube"', false);
});

it('reflects the selected layout variants and sticky header', function (): void {
    setSiteMetadata([
        'header_layout' => 'centered',
        'header_sticky' => true,
        'footer_layout' => 'columns',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-layout="centered"', false)
        ->assertSee('data-layout="columns"', false)
        ->assertSee('sticky top-0', false);
});

it('overlays a transparent header', function (): void {
    setSiteMetadata([
        'header_layout' => 'simple',
        'header_transparent' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('absolute inset-x-0 top-0', false)
        ->assertDontSee('bg-(--wire-header-bg)', false);
});

it('skips menu items whose page is missing or unpublished', function (): void {
    $draft = Page::factory()->create(['status' => PageStatus::DRAFT]);

    setSiteMetadata(['menus' => menusPayload(['header' => ['en' => [
        ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'Hidden draft', 'page_id' => $draft->id, 'url' => ''],
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Visible link', 'page_id' => null, 'url' => 'https://example.com'],
    ]]])]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Visible link')
        ->assertDontSee('Hidden draft');
});

it('renders the published page menu item with its localized url', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'get-started']);

    setSiteMetadata(['menus' => menusPayload(['header' => ['en' => [
        ['type' => 'page', 'appearance' => 'button', 'target' => '_self', 'label' => 'Get started', 'page_id' => $page->id, 'url' => ''],
    ]]])]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee(route('page', 'get-started'), false);
});
