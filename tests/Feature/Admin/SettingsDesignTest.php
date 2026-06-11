<?php

declare(strict_types=1);

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

it('shows the identity title in the preview', function (): void {
    Settings::current()->update(['title' => ['en' => 'My Brand Co']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')->assertSee('My Brand Co');
});

it('falls back to the app name in the preview when no title is set', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')->assertSee(config('app.name'));
});

it('hydrates the form with config defaults when no design is saved', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('theme', config('theme.default'))
        ->assertSet('heading_font', config('theme.default_font'))
        ->assertSet('radius', config('theme.default_radius'));
});

it('hydrates the form from existing metadata on mount', function (): void {
    Settings::current()->update(['metadata' => ['theme' => 'rose', 'body_font' => 'inter', 'radius' => 'large']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->assertSet('theme', 'rose')
        ->assertSet('body_font', 'inter')
        ->assertSet('radius', 'large');
});

it('persists the design settings to metadata on update', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'emerald')
        ->set('heading_font', 'poppins')
        ->set('body_font', 'inter')
        ->set('heading_size', 'large')
        ->set('body_size', 'small')
        ->set('radius', 'none')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::current()->fresh()->metadata)->toMatchArray([
        'theme' => 'emerald',
        'heading_font' => 'poppins',
        'body_font' => 'inter',
        'heading_size' => 'large',
        'body_size' => 'small',
        'radius' => 'none',
    ]);
});

it('validates the theme is a known preset or custom', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'not-a-color')
        ->call('update')
        ->assertHasErrors(['theme']);
});

it('requires a custom accent when the theme is custom', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'custom')
        ->set('accent', '')
        ->call('update')
        ->assertHasErrors(['accent']);
});

it('validates the custom accent is a hex colour', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-design')
        ->set('theme', 'custom')
        ->set('accent', 'blue')
        ->call('update')
        ->assertHasErrors(['accent']);
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

it('emits the per-shade accent vars for a preset theme', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'sky']]);

    expect($settings->fresh()->themeCss())
        ->toContain('--color-accent:var(--color-sky-600)')
        ->toContain('--color-accent:var(--color-sky-500)');
});

it('emits the raw hex for a custom theme', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'custom', 'accent' => '#ff0000']]);

    expect($settings->fresh()->themeCss())->toContain('--color-accent:#ff0000');
});

it('emits radius, font and size declarations from metadata', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => [
        'theme' => 'sky',
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

it('picks a dark foreground for a light custom accent', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'custom', 'accent' => '#eeeeee']]);

    expect($settings->fresh()->themeCss())->toContain('--color-accent-foreground:#18181b');
});

it('falls back to a white foreground for a malformed custom accent', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'custom', 'accent' => '#fff']]);

    expect($settings->fresh()->themeCss())->toContain('--color-accent-foreground:#ffffff');
});

it('emits no theme css when nothing is configured', function (): void {
    expect(Settings::current()->themeCss())->toBeNull();
});

it('builds no google fonts url when no fonts are set', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['theme' => 'sky']]);

    expect($settings->fresh()->googleFontsUrl())->toBeNull();
});

it('builds a google fonts url for the chosen fonts', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['heading_font' => 'inter', 'body_font' => 'inter']]);

    $url = $settings->fresh()->googleFontsUrl();

    expect($url)
        ->toBeString()
        ->toContain('family=Inter');
});

it('builds no google fonts url for system/unset fonts', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['heading_font' => 'system', 'body_font' => 'system']]);

    expect($settings->fresh()->googleFontsUrl())->toBeNull();
});
