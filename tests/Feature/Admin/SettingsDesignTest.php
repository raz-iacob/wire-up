<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Settings;
use App\Models\User;
use App\Services\SettingsService;
use Livewire\Livewire;

it('can render the design settings screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-design'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-design');
});

it('redirects authenticated non-admin users away from design settings', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'admin' => false]);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.settings-design'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from design settings', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-design'))
        ->assertRedirectToRoute('login');
});

it('hydrates the form with the default preset when nothing is saved', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('theme', config('theme.default'))
        ->assertSet('colors.background', config('theme.presets.'.config('theme.default').'.colors.background'))
        ->assertSet('radius', config('theme.default_radius'))
        ->assertSet('header_layout', config('theme.default_header_layout'))
        ->assertSet('footer_layout', config('theme.default_footer_layout'));
});

it('hydrates a preset palette from settings on mount', function (): void {
    Settings::set(['theme' => 'ocean']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('theme', 'ocean')
        ->assertSet('colors.primary_bg', config('theme.presets.ocean.colors.primary_bg'));
});

it('hydrates a custom palette from settings on mount', function (): void {
    Settings::set(['theme' => 'custom', 'colors' => ['primary_bg' => '#123456']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('theme', 'custom')
        ->assertSet('colors.primary_bg', '#123456');
});

it('loads a preset palette when the theme changes', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'forest')
        ->assertSet('colors.background', config('theme.presets.forest.colors.background'));
});

it('persists a preset choice without storing the palette', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'slate')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('theme'))->toBe('slate')
        ->and(Settings::cached())->not->toHaveKey('colors');
});

it('persists a custom palette', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'custom')
        ->set('colors.primary_bg', '#abcdef')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('theme'))->toBe('custom')
        ->and(Settings::get('colors')['primary_bg'])->toBe('#abcdef');
});

it('validates the theme is a known preset or custom', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'not-a-theme')
        ->call('update')
        ->assertHasErrors(['theme']);
});

it('validates custom colours are hex values', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'custom')
        ->set('colors.background', 'not-a-colour')
        ->call('update')
        ->assertHasErrors(['colors.background']);
});

it('validates fonts, sizes and radius are known keys', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('heading_font', 'comic-sans')
        ->set('heading_size', 'gigantic')
        ->set('radius', 'pill')
        ->call('update')
        ->assertHasErrors(['heading_font', 'heading_size', 'radius']);
});

it('hydrates header and footer layouts from settings on mount', function (): void {
    Settings::set(['header_layout' => 'centered', 'footer_layout' => 'columns']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('header_layout', 'centered')
        ->assertSet('footer_layout', 'columns');
});

it('persists header and footer layout', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('header_layout', 'split')
        ->set('footer_layout', 'centered')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('header_layout'))->toBe('split')
        ->and(Settings::get('footer_layout'))->toBe('centered');
});

it('persists the header logo and navigation sizes', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('header_logo_size', config('theme.default_header_logo_size'))
        ->assertSet('header_nav_size', config('theme.default_header_nav_size'))
        ->assertSet('header_nav_hover', config('theme.default_header_nav_hover'))
        ->set('header_logo_size', 'lg')
        ->set('header_nav_size', 'sm')
        ->set('header_nav_hover', 'underline')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('header_logo_size'))->toBe('lg')
        ->and(Settings::get('header_nav_size'))->toBe('sm')
        ->and(Settings::get('header_nav_hover'))->toBe('underline');
});

it('validates the header logo, navigation size and hover are known values', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('header_logo_size', 'huge')
        ->set('header_nav_size', 'tiny')
        ->set('header_nav_hover', 'sparkle')
        ->call('update')
        ->assertHasErrors(['header_logo_size', 'header_nav_size', 'header_nav_hover']);
});

it('hydrates header transparent and sticky flags from settings on mount', function (): void {
    Settings::set(['header_transparent' => true, 'header_sticky' => true, 'footer_transparent' => true]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('header_transparent', true)
        ->assertSet('header_sticky', true)
        ->assertSet('footer_transparent', true);
});

it('persists header transparent, sticky, and footer transparent', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('header_transparent', true)
        ->set('header_sticky', true)
        ->set('footer_transparent', true)
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('header_transparent'))->toBeTrue()
        ->and(Settings::get('header_sticky'))->toBeTrue()
        ->and(Settings::get('footer_transparent'))->toBeTrue();
});

it('validates header and footer layout are known keys', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('header_layout', 'mega-header')
        ->set('footer_layout', 'floating')
        ->call('update')
        ->assertHasErrors(['header_layout', 'footer_layout']);
});

it('stores the header and footer logo items on update', function (): void {
    $header = Media::factory()->create();
    $footer = Media::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('logo_header', ['id' => $header->id, 'source' => $header->source])
        ->set('logo_footer', ['id' => $footer->id, 'source' => $footer->source])
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('logo_header')['id'])->toBe($header->id)
        ->and(Settings::get('logo_footer')['id'])->toBe($footer->id);
});

it('resolves the palette for a preset theme', function (): void {
    Settings::set(['theme' => 'ocean']);

    expect((new SettingsService)->themeColors())->toEqual(config('theme.presets.ocean.colors'));
});

it('resolves the palette for a custom theme', function (): void {
    Settings::set(['theme' => 'custom', 'colors' => ['primary_bg' => '#0f0f0f']]);

    expect((new SettingsService)->themeColors())->toEqual(['primary_bg' => '#0f0f0f']);
});

it('resolves an empty palette when no theme is set', function (): void {
    expect((new SettingsService)->themeColors())->toBe([]);
});

it('resolves an empty palette when custom colours are malformed', function (): void {
    Settings::set(['theme' => 'custom', 'colors' => 'not-an-array']);

    expect((new SettingsService)->themeColors())->toBe([]);
});

it('emits palette and accent css vars for a preset', function (): void {
    Settings::set(['theme' => 'ocean']);

    expect((new SettingsService)->themeCss())
        ->toContain('--wire-body-bg:#f0f9ff')
        ->toContain('--wire-card-border:#bae6fd')
        ->toContain('--wire-card-text:#0c4a6e')
        ->toContain('--wire-header-bg:#0c4a6e')
        ->toContain('--color-accent:#0ea5e9')
        ->toContain('--color-accent-foreground:#ffffff');
});

it('emits radius and font declarations from settings', function (): void {
    Settings::set([
        'theme' => 'slate',
        'radius' => 'large',
        'body_font' => 'inter',
        'heading_font' => 'poppins',
        'heading_size' => 'large',
        'body_size' => 'small',
    ]);

    expect((new SettingsService)->themeCss())
        ->toContain('--radius-lg:1rem')
        ->toContain('--font-sans:"Inter", sans-serif')
        ->toContain('--wire-heading-font:"Poppins", sans-serif')
        ->toContain('--wire-body-font:"Inter", sans-serif')
        ->toContain('--wire-heading-size:1.875rem')
        ->toContain('--wire-body-size:0.875rem');
});

it('always emits the default palette when nothing is configured', function (): void {
    expect((new SettingsService)->themeCss())
        ->toContain('--wire-body-bg:#ffffff')
        ->toContain('--wire-header-bg:#ffffff');
});

it('builds a google fonts url for the chosen fonts', function (): void {
    Settings::set(['heading_font' => 'inter', 'body_font' => 'inter']);

    expect((new SettingsService)->googleFontsUrl())
        ->toBeString()
        ->toContain('family=Inter');
});

it('builds no google fonts url for system fonts', function (): void {
    Settings::set(['heading_font' => 'system', 'body_font' => 'system']);

    expect((new SettingsService)->googleFontsUrl())->toBeNull();
});

it('builds no google fonts url when fonts are unset', function (): void {
    Settings::set(['theme' => 'ocean']);

    expect((new SettingsService)->googleFontsUrl())->toBeNull();
});
