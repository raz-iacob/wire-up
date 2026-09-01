<?php

declare(strict_types=1);

use App\Services\SettingsService;

function tokenCss(array $settings = []): string
{
    foreach ($settings as $key => $value) {
        config()->set("site.{$key}", $value);
    }

    return (new SettingsService)->themeCss();
}

it('falls back to the text colour when no heading colour is chosen', function (): void {
    expect(tokenCss())->toContain('--wire-heading:var(--wire-body-text)');
});

it('uses a chosen heading colour, and still falls back in dark mode', function (): void {
    $css = tokenCss([
        'theme' => 'custom',
        'colors' => ['background' => '#ffffff', 'text' => '#111111', 'heading' => '#727d5f', 'accent' => '#727d5f', 'primary_text' => '#ffffff'],
        'colors_dark' => ['background' => '#000000', 'text' => '#eeeeee', 'accent' => '#aabb88', 'primary_text' => '#000000'],
    ]);

    expect($css)->toContain('--wire-heading:#727d5f')
        ->and($css)->toContain('--wire-heading:var(--wire-body-text)');
});

it('lets the button radius follow the general radius by default', function (): void {
    expect(tokenCss())->toContain('--wire-btn-radius:var(--wire-radius)');
});

it('gives buttons and inputs their own radius when asked', function (): void {
    expect(tokenCss(['button_radius' => 'full']))->toContain('--wire-btn-radius:9999px');
});

it('keeps the gutter in rem until a pixel value is set', function (): void {
    expect(tokenCss())->toContain('--wire-gutter:1.5rem');
    expect(tokenCss(['gutter' => 40]))->toContain('--wire-gutter:40px');
});

it('ignores a gutter outside the allowed range', function (): void {
    expect(tokenCss(['gutter' => 500]))->toContain('--wire-gutter:1.5rem');
});

it('takes a numeric container width only for the custom preset', function (): void {
    expect(tokenCss(['container' => 'custom', 'container_width' => 1380]))->toContain('--wire-container:1380px');
    expect(tokenCss(['container' => 'medium', 'container_width' => 1380]))->toContain('--wire-container:72rem');
});

it('falls back to the default width when a custom container has no number', function (): void {
    expect(tokenCss(['container' => 'custom']))
        ->toContain('--wire-container:'.config()->integer('theme.default_container_width').'px');
});

it('emits the body line height and list indent', function (): void {
    expect(tokenCss())->toContain('--wire-body-leading:1.625')->toContain('--wire-list-indent:1.5rem');
    expect(tokenCss(['body_leading' => 'tight', 'list_indent' => 'large']))
        ->toContain('--wire-body-leading:1.4')
        ->toContain('--wire-list-indent:2rem');
});

it('leaves the header and logo heights unset until they are chosen', function (): void {
    expect(tokenCss())->not->toContain('--wire-header-height')
        ->and(tokenCss())->not->toContain('--wire-logo-height');
});

it('emits the header and logo heights when set', function (): void {
    expect(tokenCss(['header_height' => 88, 'header_logo_height' => 40]))
        ->toContain('--wire-header-height:88px')
        ->toContain('--wire-logo-height:40px');
});

it('ignores header sizes outside the allowed range', function (): void {
    expect(tokenCss(['header_height' => 9, 'header_logo_height' => 900]))
        ->not->toContain('--wire-header-height')
        ->and(tokenCss(['header_height' => 9]))->not->toContain('--wire-logo-height');
});

it('gives the logo an exact height on the public header', function (): void {
    config()->set('site.header_logo_height', 40);

    expect((new SettingsService)->headerLogoHeight())->toBe(40);
});

it('treats a cleared heading colour as unset rather than emitting an empty value', function (): void {
    $css = tokenCss([
        'theme' => 'custom',
        'colors' => ['background' => '#ffffff', 'text' => '#111111', 'heading' => '', 'accent' => '#111111', 'primary_text' => '#ffffff'],
        'colors_dark' => [],
    ]);

    expect($css)->toContain('--wire-heading:var(--wire-body-text)')
        ->and($css)->not->toContain('--wire-heading:;');
});
