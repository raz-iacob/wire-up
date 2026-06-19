<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;
use App\Services\SettingsService;

function headerMenu(array $items): SettingsService
{
    Settings::set(['header_menu' => ['en' => $items]]);

    return new SettingsService;
}

function publishedPage(string $slug): Page
{
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page->load('slugs');
}

it('resolves a page item to its localized url', function (): void {
    $page = publishedPage('about');

    $menu = headerMenu([
        ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'About', 'page_id' => $page->id, 'url' => ''],
    ])->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0])->toMatchArray([
            'label' => 'About',
            'url' => route('page', 'about'),
            'target' => '_self',
            'appearance' => 'link',
        ]);
});

it('passes through a custom link item', function (): void {
    $menu = headerMenu([
        ['type' => 'link', 'appearance' => 'button', 'target' => '_blank', 'label' => 'Docs', 'page_id' => null, 'url' => 'https://example.com/docs'],
    ])->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0])->toMatchArray([
            'label' => 'Docs',
            'url' => 'https://example.com/docs',
            'target' => '_blank',
            'appearance' => 'button',
        ]);
});

it('skips items whose page is missing or unpublished', function (): void {
    $draft = Page::factory()->create(['status' => PageStatus::DRAFT]);

    $menu = headerMenu([
        ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'Draft', 'page_id' => $draft->id, 'url' => ''],
        ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'Gone', 'page_id' => 999999, 'url' => ''],
    ])->menu('header');

    expect($menu)->toBeEmpty();
});

it('skips link items without a url and items without a label', function (): void {
    $menu = headerMenu([
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'No URL', 'page_id' => null, 'url' => ''],
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => '', 'page_id' => null, 'url' => 'https://example.com'],
    ])->menu('header');

    expect($menu)->toBeEmpty();
});

it('returns an empty array when no menu is saved', function (): void {
    expect((new SettingsService)->menu('header'))->toBeEmpty()
        ->and((new SettingsService)->menu('footer'))->toBeEmpty();
});

it('returns an empty array when the current default-locale menu has no items', function (): void {
    expect(headerMenu([])->menu('header'))->toBeEmpty();
});

it('skips malformed (non-array) menu entries', function (): void {
    $menu = headerMenu([
        'not-an-array',
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Docs', 'page_id' => null, 'url' => 'https://example.com'],
    ])->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('Docs');
});

it('skips a page item whose page has no slug for the current locale', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    $menu = headerMenu([
        ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'No slug', 'page_id' => $page->id, 'url' => ''],
    ])->menu('header');

    expect($menu)->toBeEmpty();
});

it('returns an empty array for an unknown location', function (): void {
    expect(headerMenu([
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Docs', 'page_id' => null, 'url' => 'https://example.com'],
    ])->menu('sidebar'))->toBeEmpty();
});

it('falls back to the default locale menu when the current locale has none', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    Settings::set(['header_menu' => [
        'en' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'English', 'page_id' => null, 'url' => 'https://example.com']],
        'nl' => [],
    ]]);

    app()->setLocale('nl');

    $menu = (new SettingsService)->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('English');
});

it('prefers the current locale menu over the default', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    Settings::set(['header_menu' => [
        'en' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'English', 'page_id' => null, 'url' => 'https://example.com/en']],
        'nl' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Nederlands', 'page_id' => null, 'url' => 'https://example.com/nl']],
    ]]);

    app()->setLocale('nl');

    $menu = (new SettingsService)->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('Nederlands');
});

it('returns only the social platforms that are set, in config order', function (): void {
    Settings::set(['social' => [
        'x' => 'https://x.com/handle',
        'facebook' => 'https://facebook.com/page',
        'linkedin' => '',
    ]]);

    expect((new SettingsService)->socialLinks())->toBe([
        'facebook' => 'https://facebook.com/page',
        'x' => 'https://x.com/handle',
    ]);
});

it('returns no social links when none are saved', function (): void {
    expect((new SettingsService)->socialLinks())->toBeEmpty();
});

it('returns the saved social icon variant', function (): void {
    Settings::set(['social_icon_variant' => 'outline']);

    expect((new SettingsService)->socialIconVariant())->toBe('outline');
});

it('falls back to the default social icon variant when unset or unknown', function (): void {
    expect((new SettingsService)->socialIconVariant())->toBe('solid');

    Settings::set(['social_icon_variant' => 'bogus']);

    expect((new SettingsService)->socialIconVariant())->toBe('solid');
});

it('resolves the title and tagline for the current locale', function (): void {
    Settings::set(['title' => ['en' => 'Wire-Up'], 'description' => ['en' => 'A tagline']]);

    expect((new SettingsService)->title())->toBe('Wire-Up')
        ->and((new SettingsService)->description())->toBe('A tagline');
});

it('falls back to the fallback locale title when the current locale has none', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    Settings::set(['title' => ['en' => 'English title']]);
    app()->setLocale('nl');

    expect((new SettingsService)->title())->toBe('English title');
});

it('falls back to any non-empty title when neither the current nor fallback locale match', function (): void {
    Settings::set(['title' => ['de' => 'Deutscher Titel']]);

    expect((new SettingsService)->title())->toBe('Deutscher Titel');
});

it('returns an empty string for the title and tagline when none are saved', function (): void {
    expect((new SettingsService)->title())->toBe('')
        ->and((new SettingsService)->description())->toBe('');
});

it('returns an empty title when every saved locale value is blank', function (): void {
    Settings::set(['title' => ['en' => '']]);

    expect((new SettingsService)->title())->toBe('');
});

it('builds a logo url that caps height without constraining width', function (): void {
    Settings::set(['logo_header' => [
        'source' => 'images/logo.jpg',
        'crop' => ['default' => ['crop_w' => 600, 'crop_h' => 600, 'crop_x' => 10, 'crop_y' => 20]],
    ]]);

    expect((new SettingsService)->logoUrl('logo_header'))
        ->toBeString()
        ->toContain('/img/')
        ->toContain('images/logo.jpg')
        ->toContain('h=320')
        ->toContain('crop=600-600-10-20')
        ->not->toContain('w=');
});

it('builds a logo url without a crop when none is stored', function (): void {
    Settings::set(['logo_header' => ['source' => 'images/logo.jpg']]);

    expect((new SettingsService)->logoUrl('logo_header'))
        ->toBeString()
        ->toContain('/img/')
        ->toContain('images/logo.jpg')
        ->toContain('h=320')
        ->not->toContain('crop=')
        ->not->toContain('w=');
});

it('builds a logo url for an svg even without a crop', function (): void {
    Settings::set(['logo_header' => ['source' => 'media/brand-logo.svg', 'crop' => []]]);

    expect((new SettingsService)->logoUrl('logo_header'))
        ->toBeString()
        ->toContain('/img/')
        ->toContain('media/brand-logo.svg');
});

it('falls back to the seeded home page when no homepage is configured', function (): void {
    $home = (new SettingsService)->homePage();

    expect($home)->not->toBeNull()
        ->and($home->slug)->toBe('home');
});

it('uses the configured page as the homepage when set', function (): void {
    $page = publishedPage('landing');
    Settings::set(['home_page_id' => $page->id]);

    expect((new SettingsService)->homePageId())->toBe($page->id);
});

it('falls back to the seeded home page when the configured homepage is not published', function (): void {
    $draft = Page::factory()->create(['status' => PageStatus::DRAFT]);
    Settings::set(['home_page_id' => $draft->id]);

    expect((new SettingsService)->homePage()->slug)->toBe('home');
});

it('returns null when no published homepage can be resolved', function (): void {
    Page::query()->delete();

    expect((new SettingsService)->homePage())->toBeNull()
        ->and((new SettingsService)->homePageId())->toBeNull();
});

it('resolves a theme slot color from the active preset', function (): void {
    config()->set('site.theme', 'midnight');

    expect((new SettingsService)->color('header_bg'))->toBe('#0a0a0a');
});

it('resolves a custom theme slot color', function (): void {
    config()->set('site.theme', 'custom');
    config()->set('site.colors', ['header_bg' => '#123456']);

    expect((new SettingsService)->color('header_bg'))->toBe('#123456');
});

it('falls back to the default preset color when no theme is set', function (): void {
    config()->set('site.theme', '');

    expect((new SettingsService)->color('header_bg'))->toBe('#ffffff');
});

it('returns null for an unknown color slot', function (): void {
    config()->set('site.theme', '');

    expect((new SettingsService)->color('not_a_slot'))->toBeNull();
});
