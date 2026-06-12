<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Settings;

it('renders the cropped header logo in the live preview', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::IMAGE,
        'source' => 'images/logo-test.jpg',
    ]);

    Settings::current()->media()->attach($media->id, [
        'role' => 'logo_header',
        'locale' => app()->getLocale(),
        'crop' => ['default' => [
            'w' => 480, 'h' => 160,
            'crop_w' => 300, 'crop_h' => 100, 'crop_x' => 10, 'crop_y' => 20,
            'q' => 80, 'fm' => 'jpg',
        ]],
    ]);

    $this->actingAsAdmin();

    $page = visit(route('admin.settings-design'));

    $page->assertNoJavascriptErrors()
        ->assertPresent('img[src*="/img/w=480,h=160,crop=300-100-10-20"]');
});

it('preloads the preview web fonts so selections render', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-design'));

    $page->assertNoJavascriptErrors()
        ->assertPresent('link#design-preview-fonts[href*="fonts.googleapis.com"]');
});

it('updates the preview live from a deferred property change without a server round-trip', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-design'));

    $page->assertScript("getComputedStyle(document.querySelector('[data-test=preview-body]')).backgroundColor", 'rgb(255, 255, 255)');

    $page->script("window.Livewire.all().find(c => c.\$wire.get('theme') !== undefined).\$wire.set('theme', 'midnight', false); void 0");
    $page->wait(0.3);

    $page->assertNoJavascriptErrors()
        ->assertScript("getComputedStyle(document.querySelector('[data-test=preview-body]')).backgroundColor", 'rgb(10, 10, 10)');
});

it('confines preview font selection to the preview and leaves the admin chrome untouched', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-design'));
    $page->wait(0.4);

    $page->assertScript("getComputedStyle(document.body).getPropertyValue('--font-sans').includes('Lato')", false);

    $page->script("window.Livewire.all().find(c => c.\$wire.get('heading_font') !== undefined).\$wire.set('heading_font', 'lato', false); void 0");
    $page->wait(0.4);

    $page->assertNoJavascriptErrors()
        ->assertScript("getComputedStyle(document.querySelector('[data-test=preview-body] h1')).fontFamily.includes('Lato')", true)
        ->assertScript("getComputedStyle(document.body).getPropertyValue('--font-sans').includes('Lato')", false);
});

it('keeps exactly one header and footer variant visible when other fields change', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-design'));

    $countVisible = fn (string $marker): string => "Array.from(document.querySelectorAll('[data-test={$marker}]')).filter(el => el.offsetHeight > 0).length";

    $page->assertScript($countVisible('header-variant'), 1)
        ->assertScript($countVisible('footer-variant'), 1);

    $page->script("window.Livewire.all().find(c => c.\$wire.get('heading_font') !== undefined).\$wire.set('heading_font', 'lato', false); void 0");
    $page->wait(0.3);

    $page->assertNoJavascriptErrors()
        ->assertScript($countVisible('header-variant'), 1)
        ->assertScript($countVisible('footer-variant'), 1);
});
