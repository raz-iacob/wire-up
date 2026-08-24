<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Settings;
use App\Services\SettingsService;

function logoSettings(): array
{
    $light = Media::factory()->create(['source' => 'media/logo-light.png', 'mime_type' => 'image/png']);
    $dark = Media::factory()->create(['source' => 'media/logo-dark.png', 'mime_type' => 'image/png']);

    return [
        'logo_header' => ['id' => $light->id, 'source' => $light->source],
        'logo_header_dark' => ['id' => $dark->id, 'source' => $dark->source],
    ];
}

it('keeps the light-ink logo on a theme whose header stays light', function (): void {
    Settings::set([...logoSettings(), 'theme' => 'blueprint', 'theme_dark' => 'on']);

    $logos = (new SettingsService)->logosForSurface('logo_header', 'header_bg');

    expect($logos['light'])->toContain('logo-light')
        ->and($logos['dark'])->toContain('logo-dark');
});

it('uses the dark-background logo in light mode when the theme has a dark header', function (): void {
    Settings::set([...logoSettings(), 'theme' => 'ocean', 'theme_dark' => 'on']);

    $logos = (new SettingsService)->logosForSurface('logo_header', 'header_bg');

    expect($logos['light'])->toContain('logo-dark')
        ->and($logos['dark'])->toContain('logo-dark');
});

it('keeps the light-ink logo for dark-scheme visitors when dark mode is off', function (): void {
    Settings::set([...logoSettings(), 'theme' => 'blueprint', 'theme_dark' => 'none']);

    $logos = (new SettingsService)->logosForSurface('logo_header', 'header_bg');

    expect($logos['light'])->toContain('logo-light')
        ->and($logos['dark'])->toContain('logo-light');
});

it('reads the footer surface separately from the header', function (): void {
    $settings = logoSettings();
    Settings::set([
        ...$settings,
        'logo_footer' => $settings['logo_header'],
        'logo_footer_dark' => $settings['logo_header_dark'],
        'theme' => 'sand',
        'theme_dark' => 'on',
    ]);

    $service = new SettingsService;

    expect($service->logosForSurface('logo_header', 'header_bg')['light'])->toContain('logo-light')
        ->and($service->logosForSurface('logo_footer', 'footer_bg')['light'])->toContain('logo-dark');
});
