<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;
use App\Services\SettingsService;

function present(?Settings $settings): SettingsService
{
    return new SettingsService($settings);
}

function headerMenu(array $items): SettingsService
{
    $settings = Settings::current();
    $settings->update(['metadata' => ['header_menu' => ['en' => $items]]]);

    return present($settings->fresh());
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
    expect(present(Settings::current())->menu('header'))->toBeEmpty()
        ->and(present(Settings::current())->menu('footer'))->toBeEmpty();
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

    $settings = Settings::current();
    $settings->update(['metadata' => ['header_menu' => [
        'en' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'English', 'page_id' => null, 'url' => 'https://example.com']],
        'nl' => [],
    ]]]);

    app()->setLocale('nl');

    $menu = present($settings->fresh())->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('English');
});

it('prefers the current locale menu over the default', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $settings = Settings::current();
    $settings->update(['metadata' => ['header_menu' => [
        'en' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'English', 'page_id' => null, 'url' => 'https://example.com/en']],
        'nl' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Nederlands', 'page_id' => null, 'url' => 'https://example.com/nl']],
    ]]]);

    app()->setLocale('nl');

    $menu = present($settings->fresh())->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('Nederlands');
});

it('returns only the social platforms that are set, in config order', function (): void {
    $settings = Settings::current();
    $settings->update(['metadata' => ['social' => [
        'x' => 'https://x.com/handle',
        'facebook' => 'https://facebook.com/page',
        'linkedin' => '',
    ]]]);

    expect(present($settings->fresh())->socialLinks())->toBe([
        'facebook' => 'https://facebook.com/page',
        'x' => 'https://x.com/handle',
    ]);
});

it('returns no social links when none are saved', function (): void {
    expect(present(Settings::current())->socialLinks())->toBeEmpty();
});
