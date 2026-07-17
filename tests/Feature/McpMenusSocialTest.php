<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\GetMenusTool;
use App\Mcp\Tools\GetSettingsTool;
use App\Mcp\Tools\UpdateMenuTool;
use App\Mcp\Tools\UpdateSocialTool;
use App\Models\Page;
use App\Models\Settings;
use App\Services\SettingsService;
use Laravel\Mcp\Server\Testing\TestResponse;

function menuPage(string $slug, string $title): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => $title,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('advertises the menu and social tools with their names and schemas', function (): void {
    $advertised = collect([GetMenusTool::class, UpdateMenuTool::class, UpdateSocialTool::class])
        ->map(fn (string $tool): array => resolve($tool)->toArray());

    expect($advertised->pluck('name')->all())->toBe(['get-menus', 'update-menu', 'update-social'])
        ->and($advertised[1]['inputSchema']['required'])->toBe(['menu', 'items'])
        ->and($advertised[2]['inputSchema']['required'])->toBe(['links']);
});

it('lists the builtin menus', function (): void {
    WireUpServer::tool(GetMenusTool::class)
        ->assertOk()
        ->assertSee('"key":"header"')
        ->assertSee('"key":"footer"')
        ->assertSee('update-menu');
});

it('replaces the header menu items and resolves them on the site', function (): void {
    $about = menuPage('about-menu', 'About');

    $response = WireUpServer::tool(UpdateMenuTool::class, [
        'menu' => 'header',
        'items' => [
            ['type' => 'page', 'label' => 'About us', 'page' => $about->id],
            ['type' => 'link', 'label' => 'Docs', 'url' => 'https://docs.example.com', 'target' => '_blank', 'appearance' => 'button', 'badge' => 'New', 'badgeColor' => 'lime'],
            ['type' => 'heading', 'label' => 'More'],
        ],
        'display' => ['sticky' => true, 'position' => 'right'],
    ]);

    $response->assertOk()
        ->assertSee('About us')
        ->assertSee('"sticky":true');

    $menu = SettingsService::current()->menuForDisplay('header');

    expect($menu['items'])->toHaveCount(3)
        ->and($menu['items'][0]['url'])->toContain('about-menu')
        ->and($menu['items'][1]['target'])->toBe('_blank')
        ->and($menu['items'][1]['appearance'])->toBe('button')
        ->and($menu['items'][1]['badge'])->toBe('New')
        ->and($menu['items'][2]['type'])->toBe('heading')
        ->and($menu['display']['sticky'])->toBeTrue()
        ->and($menu['display']['position'])->toBe('right');
});

it('accepts page_id as an alias and a locale argument', function (): void {
    $page = menuPage('localized-target', 'Target');

    WireUpServer::tool(UpdateMenuTool::class, [
        'menu' => 'footer',
        'locale' => 'en',
        'items' => [['type' => 'page', 'label' => 'Target', 'page_id' => $page->id]],
    ])->assertOk();

    $menus = SettingsService::current()->allMenus();
    $footer = collect($menus)->firstWhere('key', 'footer');

    expect($footer['items']['en'][0]['page_id'])->toBe($page->id);
});

it('clears a menu with an empty item list', function (): void {
    $page = menuPage('temp-nav', 'Temp');

    WireUpServer::tool(UpdateMenuTool::class, ['menu' => 'header', 'items' => [['type' => 'page', 'label' => 'Temp', 'page' => $page->id]]])->assertOk();
    WireUpServer::tool(UpdateMenuTool::class, ['menu' => 'header', 'items' => []])->assertOk();

    expect(SettingsService::current()->menuForDisplay('header'))->toBeNull();
});

it('rejects unknown menus and invalid locales', function (): void {
    WireUpServer::tool(UpdateMenuTool::class, ['menu' => 'sidebar-9000', 'items' => []])
        ->assertHasErrors()
        ->assertSee('No menu with key "sidebar-9000"');

    WireUpServer::tool(UpdateMenuTool::class, ['menu' => 'header', 'items' => [], 'locale' => 'xx'])
        ->assertHasErrors()
        ->assertSee('Unknown locale');
});

it('rejects invalid menu items with the item position in the message', function (): void {
    $page = menuPage('valid-target', 'Valid');

    $bad = fn (mixed $item): TestResponse => WireUpServer::tool(UpdateMenuTool::class, ['menu' => 'header', 'items' => [
        ['type' => 'page', 'label' => 'Fine', 'page' => $page->id],
        $item,
    ]]);

    $bad(['type' => 'megamenu', 'label' => 'X'])->assertHasErrors()->assertSee('Item 2: unknown type "megamenu"');
    $bad(['type' => 'heading', 'label' => ''])->assertHasErrors()->assertSee('Item 2: every item needs a label');
    $bad(['type' => 'page', 'label' => 'Ghost', 'page' => 999999])->assertHasErrors()->assertSee('Item 2: a page item needs the id of an existing page');
    $bad(['type' => 'link', 'label' => 'Bad', 'url' => 'ftp://nope'])->assertHasErrors()->assertSee('Item 2: a link item needs a url');
    $bad(['type' => 'link', 'label' => 'Bad', 'url' => 'https://ok.example', 'target' => '_parent'])->assertHasErrors()->assertSee('Item 2: target must be');
    $bad(['type' => 'link', 'label' => 'Bad', 'url' => 'https://ok.example', 'appearance' => 'icon'])->assertHasErrors()->assertSee('Item 2: appearance must be');
    $bad('not-an-object')->assertHasErrors()->assertSee('must be an object');
});

it('sets, merges, and removes social links', function (): void {
    WireUpServer::tool(UpdateSocialTool::class, ['links' => ['instagram' => 'https://instagram.com/acme', 'x' => 'https://x.com/acme']])
        ->assertOk()
        ->assertSee('instagram.com/acme');

    WireUpServer::tool(UpdateSocialTool::class, ['links' => ['x' => '']])->assertOk();

    expect(SettingsService::current()->socialLinks())->toBe(['instagram' => 'https://instagram.com/acme']);
});

it('rejects unknown social platforms and non-https links', function (): void {
    WireUpServer::tool(UpdateSocialTool::class, ['links' => ['myspace' => 'https://myspace.com/acme']])
        ->assertHasErrors()
        ->assertSee('Unknown platform(s): myspace');

    WireUpServer::tool(UpdateSocialTool::class, ['links' => ['instagram' => 'instagram.com/acme']])
        ->assertHasErrors()
        ->assertSee('full https:// URL');

    WireUpServer::tool(UpdateSocialTool::class, [])
        ->assertHasErrors()
        ->assertSee('Pass a links object');
});

it('includes social links and platforms in get-settings', function (): void {
    Settings::set(['social' => ['facebook' => 'https://facebook.com/acme']]);

    WireUpServer::tool(GetSettingsTool::class)
        ->assertOk()
        ->assertSee('facebook.com/acme')
        ->assertSee('social_platforms');
});

it('places an account menu item that expands by auth state', function (): void {
    WireUpServer::tool(UpdateMenuTool::class, [
        'menu' => 'header',
        'items' => [['type' => 'account', 'appearance' => 'button']],
    ])
        ->assertOk()
        ->assertSee('"type":"account"');

    $item = collect(Settings::get('menus'))->firstWhere('key', 'header')['items']['en'][0];

    expect($item['type'])->toBe('account')
        ->and($item['appearance'])->toBe('button')
        ->and(collect(SettingsService::current()->menu('header'))->pluck('label')->all())->toContain(__('Log in'));
});

it('rejects an icon appearance on an account menu item', function (): void {
    WireUpServer::tool(UpdateMenuTool::class, [
        'menu' => 'header',
        'items' => [['type' => 'account', 'appearance' => 'icon']],
    ])->assertHasErrors(['appearance must be link or button']);
});
