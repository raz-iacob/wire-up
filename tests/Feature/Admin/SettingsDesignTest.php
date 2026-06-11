<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Settings;
use App\Models\User;
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
        ->assertSet('radius', config('theme.default_radius'));
});

it('hydrates a preset palette from metadata on mount', function (): void {
    Settings::current()->update(['metadata' => ['theme' => 'ocean']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('theme', 'ocean')
        ->assertSet('colors.primary_bg', config('theme.presets.ocean.colors.primary_bg'));
});

it('hydrates a custom palette from metadata on mount', function (): void {
    Settings::current()->update(['metadata' => ['theme' => 'custom', 'colors' => ['primary_bg' => '#123456']]]);

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

    $metadata = Settings::current()->fresh()->metadata;

    expect($metadata)->toMatchArray(['theme' => 'slate'])
        ->and($metadata)->not->toHaveKey('colors');
});

it('persists a custom palette to metadata', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'custom')
        ->set('colors.primary_bg', '#abcdef')
        ->call('update')
        ->assertHasNoErrors();

    $metadata = Settings::current()->fresh()->metadata;

    expect($metadata['theme'])->toBe('custom')
        ->and($metadata['colors']['primary_bg'])->toBe('#abcdef');
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

it('attaches the header and footer logos on update', function (): void {
    $settings = Settings::current();
    $header = Media::factory()->create(['type' => MediaType::IMAGE]);
    $footer = Media::factory()->create(['type' => MediaType::IMAGE]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('logo_header', ['id' => $header->id])
        ->set('logo_footer', ['id' => $footer->id])
        ->call('update')
        ->assertHasNoErrors();

    foreach (['logo_header' => $header, 'logo_footer' => $footer] as $role => $media) {
        $this->assertDatabaseHas('mediables', [
            'media_id' => $media->id,
            'mediable_id' => $settings->id,
            'mediable_type' => 'settings',
            'role' => $role,
            'locale' => resolve('localization')->getDefaultLocale(),
        ]);
    }
});

it('rejects a logo referencing a non-existent media id', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('logo_header', ['id' => 999999])
        ->call('update')
        ->assertHasErrors(['logo_header.id']);
});

it('resolves the palette for a preset theme', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'ocean']]);

    expect($settings->fresh()->themeColors())->toEqual(config('theme.presets.ocean.colors'));
});

it('resolves the palette for a custom theme', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'custom', 'colors' => ['primary_bg' => '#0f0f0f']]]);

    expect($settings->fresh()->themeColors())->toEqual(['primary_bg' => '#0f0f0f']);
});

it('resolves an empty palette when no theme is set', function (): void {
    expect(Settings::current()->themeColors())->toBe([]);
});

it('resolves an empty palette when custom colours are malformed', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'custom', 'colors' => 'not-an-array']]);

    expect($settings->fresh()->themeColors())->toBe([]);
});

it('emits palette and accent css vars for a preset', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'ocean']]);

    expect($settings->fresh()->themeCss())
        ->toContain('--site-background:#f0f9ff')
        ->toContain('--site-card-border:#bae6fd')
        ->toContain('--site-card-text:#0c4a6e')
        ->toContain('--site-header-bg:#0c4a6e')
        ->toContain('--color-accent:#0ea5e9')
        ->toContain('--color-accent-foreground:#ffffff');
});

it('emits radius and font declarations from metadata', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => [
        'theme' => 'slate',
        'radius' => 'large',
        'body_font' => 'inter',
        'heading_font' => 'poppins',
        'heading_size' => 'large',
        'body_size' => 'small',
    ]]);

    expect($settings->fresh()->themeCss())
        ->toContain('--radius-lg:1rem')
        ->toContain('--font-sans:"Inter", sans-serif')
        ->toContain('h1,h2,h3,h4,h5,h6,[data-flux-heading]{font-family:"Poppins", sans-serif}')
        ->toContain('--site-heading-size:1.875rem')
        ->toContain('--site-body-size:0.8125rem');
});

it('emits no theme css when nothing is configured', function (): void {
    expect(Settings::current()->themeCss())->toBeNull();
});

it('builds a google fonts url for the chosen fonts', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['heading_font' => 'inter', 'body_font' => 'inter']]);

    expect($settings->fresh()->googleFontsUrl())
        ->toBeString()
        ->toContain('family=Inter');
});

it('builds no google fonts url for system fonts', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['heading_font' => 'system', 'body_font' => 'system']]);

    expect($settings->fresh()->googleFontsUrl())->toBeNull();
});

it('builds no google fonts url when fonts are unset', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'ocean']]);

    expect($settings->fresh()->googleFontsUrl())->toBeNull();
});
