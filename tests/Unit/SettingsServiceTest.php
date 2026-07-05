<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;
use App\Services\SettingsService;

function headerMenu(array $items): SettingsService
{
    Settings::set(['menus' => [
        ['key' => 'header', 'name' => 'Header', 'builtin' => true, 'items' => ['en' => $items]],
    ]]);

    return new SettingsService;
}

function publishedPage(string $slug): Page
{
    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page->load('slugs');
}

it('returns the configured contact email', function (): void {
    Settings::set(['contact_email' => 'owner@example.com']);

    expect((new SettingsService)->contactEmail())->toBe('owner@example.com');
});

it('returns an empty string when no contact email is configured', function (): void {
    config()->set('site.contact_email');

    expect((new SettingsService)->contactEmail())->toBe('');
});

it('reports the discourage-search-engines flag', function (): void {
    Settings::set(['noindex' => true]);

    expect((new SettingsService)->noindex())->toBeTrue();
});

it('defaults the discourage-search-engines flag to false', function (): void {
    config()->set('site.noindex');

    expect((new SettingsService)->noindex())->toBeFalse();
});

it('returns the configured google analytics id', function (): void {
    Settings::set(['google_analytics_id' => 'G-ABC123']);

    expect((new SettingsService)->googleAnalyticsId())->toBe('G-ABC123');
});

it('returns an empty string when no google analytics id is configured', function (): void {
    config()->set('site.google_analytics_id');

    expect((new SettingsService)->googleAnalyticsId())->toBe('');
});

it('returns the trimmed head and body scripts', function (): void {
    Settings::set([
        'head_scripts' => '  <script>head()</script>  ',
        'body_scripts' => '  <script>body()</script>  ',
    ]);

    expect((new SettingsService)->headScripts())->toBe('<script>head()</script>')
        ->and((new SettingsService)->bodyScripts())->toBe('<script>body()</script>');
});

it('returns an empty string when no custom scripts are configured', function (): void {
    config()->set('site.head_scripts');
    config()->set('site.body_scripts');

    expect((new SettingsService)->headScripts())->toBe('')
        ->and((new SettingsService)->bodyScripts())->toBe('');
});

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
    $draft = Page::factory()->create(['status' => ContentStatus::DRAFT]);

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
    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);

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

it('resolves a group heading item alongside link items', function (): void {
    $menu = headerMenu([
        ['type' => 'heading', 'appearance' => 'link', 'target' => '_self', 'label' => 'Guides', 'page_id' => null, 'url' => ''],
        ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Install', 'page_id' => null, 'url' => 'https://example.com/install'],
    ])->menu('header');

    expect($menu)->toHaveCount(2)
        ->and($menu[0])->toMatchArray(['type' => 'heading', 'label' => 'Guides', 'url' => ''])
        ->and($menu[1])->toMatchArray(['type' => 'link', 'label' => 'Install']);
});

it('resolves a custom named menu by its key', function (): void {
    Settings::set(['menus' => [
        ['key' => 'docs-nav', 'name' => 'Docs', 'builtin' => false, 'items' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Guide', 'page_id' => null, 'url' => 'https://example.com/guide'],
        ]]],
    ]]);

    $menu = (new SettingsService)->menu('docs-nav');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('Guide');
});

it('skips stored menus that have no key', function (): void {
    $menus = SettingsService::normalizeMenus([
        ['name' => 'Missing key'],
        ['key' => 'docs', 'name' => 'Docs'],
    ]);

    expect(collect($menus)->pluck('key')->all())->toBe(['header', 'footer', 'docs']);
});

it('returns null from menuForDisplay for an unknown menu key', function (): void {
    expect((new SettingsService)->menuForDisplay('does-not-exist'))->toBeNull();
});

it('uses a 3rem desktop gutter for a small-spacing full-width layout', function (): void {
    Settings::set(['container' => 'full', 'block_spacing' => 'small']);

    expect((new SettingsService)->themeCss())
        ->toContain('@media(min-width:768px){:root{--wire-gutter:3rem}}');
});

it('emits the button border colours and border width tokens', function (): void {
    expect((new SettingsService)->themeCss())
        ->toContain('--wire-primary-border:#18181b')
        ->toContain('--wire-secondary-border:#e4e4e7')
        ->toContain('--wire-border-width:1px');
});

it('derives the accent token and Flux accent from the accent colour', function (): void {
    expect((new SettingsService)->themeCss())
        ->toContain('--wire-accent:#18181b')
        ->toContain('--color-accent:#18181b');

    Settings::set(['theme' => 'custom', 'colors' => array_merge(
        config()->array('theme.presets.default.colors'),
        ['accent' => '#abcdef'],
    )]);

    expect((new SettingsService)->themeCss())
        ->toContain('--wire-accent:#abcdef')
        ->toContain('--color-accent:#abcdef');

    Settings::set(['border_width' => 'thick']);

    expect((new SettingsService)->themeCss())->toContain('--wire-border-width:3px');
});

it('returns the trimmed site-wide custom css', function (): void {
    Settings::set(['custom_css' => '  body { color: red; }  ']);

    expect((new SettingsService)->customCss())->toBe('body { color: red; }');

    config()->set('site.custom_css');

    expect((new SettingsService)->customCss())->toBe('');
});

it('always exposes header and footer as built-in menus, even with none saved', function (): void {
    $menus = (new SettingsService)->allMenus();

    expect(collect($menus)->pluck('key')->all())->toBe(['header', 'footer'])
        ->and(collect($menus)->every(fn (array $menu): bool => $menu['builtin']))->toBeTrue();
});

it('keeps built-ins first and appends custom menus when listing all menus', function (): void {
    Settings::set(['menus' => [
        ['key' => 'docs-nav', 'name' => 'Docs', 'builtin' => false, 'items' => ['en' => []]],
        ['key' => 'footer', 'name' => 'Footer', 'builtin' => true, 'items' => ['en' => []]],
    ]]);

    $menus = (new SettingsService)->allMenus();

    expect(collect($menus)->pluck('key')->all())->toBe(['header', 'footer', 'docs-nav'])
        ->and(collect($menus)->firstWhere('key', 'docs-nav')['builtin'])->toBeFalse();
});

it('falls back to the default locale menu when the current locale has none', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    Settings::set(['menus' => [
        ['key' => 'header', 'name' => 'Header', 'builtin' => true, 'items' => [
            'en' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'English', 'page_id' => null, 'url' => 'https://example.com']],
            'nl' => [],
        ]],
    ]]);

    app()->setLocale('nl');

    $menu = (new SettingsService)->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('English');
});

it('prefers the current locale menu over the default', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    Settings::set(['menus' => [
        ['key' => 'header', 'name' => 'Header', 'builtin' => true, 'items' => [
            'en' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'English', 'page_id' => null, 'url' => 'https://example.com/en']],
            'nl' => [['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Nederlands', 'page_id' => null, 'url' => 'https://example.com/nl']],
        ]],
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

it('returns the saved block spacing', function (): void {
    Settings::set(['block_spacing' => 'large']);

    expect((new SettingsService)->blockSpacing())->toBe('large');
});

it('falls back to the default block spacing when unset or unknown', function (): void {
    expect((new SettingsService)->blockSpacing())->toBe('default');

    Settings::set(['block_spacing' => 'bogus']);

    expect((new SettingsService)->blockSpacing())->toBe('default');
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

it('returns no logo url when the stored item has no source', function (): void {
    Settings::set(['logo_header' => ['id' => 7]]);

    expect((new SettingsService)->logoUrl('logo_header'))->toBeNull();
});

it('builds a favicon url without a crop when none is stored', function (): void {
    Settings::set(['favicon' => ['source' => 'images/favicon.png']]);

    expect((new SettingsService)->faviconUrl())
        ->toBeString()
        ->toContain('images/favicon.png')
        ->toContain('fm=png')
        ->not->toContain('crop=');
});

it('builds a favicon url applying a stored crop', function (): void {
    Settings::set(['favicon' => [
        'source' => 'images/favicon.png',
        'crop' => ['default' => ['crop_w' => 256, 'crop_h' => 256, 'crop_x' => 4, 'crop_y' => 8]],
    ]]);

    expect((new SettingsService)->faviconUrl())
        ->toBeString()
        ->toContain('/img/')
        ->toContain('images/favicon.png')
        ->toContain('crop=256-256-4-8')
        ->toContain('fm=png');
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
    $draft = Page::factory()->create(['status' => ContentStatus::DRAFT]);
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

it('reports whether public registration is allowed', function (): void {
    Settings::set(['allow_registration' => true]);

    expect((new SettingsService)->allowsRegistration())->toBeTrue();
});

it('defaults public registration to disabled', function (): void {
    config()->set('site.allow_registration');

    expect((new SettingsService)->allowsRegistration())->toBeFalse();
});

it('returns the configured auth layout', function (): void {
    Settings::set(['auth_layout' => 'split']);

    expect((new SettingsService)->authLayout())->toBe('split');
});

it('falls back to the default auth layout for an unknown value', function (): void {
    Settings::set(['auth_layout' => 'nope']);

    expect((new SettingsService)->authLayout())->toBe(config()->string('theme.default_auth_layout'));
});

it('builds the auth side image url when configured', function (): void {
    Settings::set(['auth_image' => ['source' => 'images/auth.jpg']]);

    expect((new SettingsService)->authImageUrl())
        ->toBeString()
        ->toContain('images/auth.jpg');
});

it('returns null when no auth side image is configured', function (): void {
    config()->set('site.auth_image');

    expect((new SettingsService)->authImageUrl())->toBeNull();
});

it('returns the configured auth image side', function (): void {
    Settings::set(['auth_image_side' => 'right']);

    expect((new SettingsService)->authImageSide())->toBe('right');
});

it('defaults the auth image side to left', function (): void {
    config()->set('site.auth_image_side');

    expect((new SettingsService)->authImageSide())->toBe('left');
});

it('offers register as a linkable auth page only when sign-ups are enabled', function (): void {
    config()->set('site.allow_registration', false);
    expect((new SettingsService)->authPageOptions())->toBe(['auth:login' => __('Login')]);

    config()->set('site.allow_registration', true);
    expect((new SettingsService)->authPageOptions())->toHaveKeys(['auth:login', 'auth:register']);
});

it('resolves auth link sentinels to their route urls', function (): void {
    config()->set('site.allow_registration', true);

    expect((new SettingsService)->resolveAuthLink('auth:login'))->toBe(route('login'))
        ->and((new SettingsService)->resolveAuthLink('auth:register'))->toBe(route('register'))
        ->and((new SettingsService)->resolveAuthLink('auth:unknown'))->toBeNull();

    config()->set('site.allow_registration', false);
    expect((new SettingsService)->resolveAuthLink('auth:register'))->toBeNull();
});

it('resolves an auth page menu item to its route url', function (): void {
    $menu = headerMenu([
        ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'Sign in', 'page_id' => 'auth:login', 'url' => ''],
    ])->menu('header');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['url'])->toBe(route('login'));
});

it('drops a register menu item when sign-ups are disabled', function (): void {
    config()->set('site.allow_registration', false);

    $menu = headerMenu([
        ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'Join', 'page_id' => 'auth:register', 'url' => ''],
    ])->menu('header');

    expect($menu)->toBeEmpty();
});
