<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateMenuTool;
use App\Mcp\Tools\UpdateMenuTool;
use App\Mcp\Tools\UpdatePageTool;
use App\Models\Media;
use App\Models\Page;
use App\Services\SettingsService;

function layoutOf(Page $page): array
{
    return $page->fresh()->metadata['layout'] ?? [];
}

it('creates a custom menu with a slug key derived from its name', function (): void {
    WireUpServer::tool(CreateMenuTool::class, ['name' => 'Docs Sidebar'])
        ->assertOk()
        ->assertSee('docs-sidebar');

    $menu = collect(SettingsService::current()->allMenus())->firstWhere('key', 'docs-sidebar');

    expect($menu['name'])->toBe('Docs Sidebar')
        ->and($menu['builtin'])->toBeFalse()
        ->and($menu['display']['position'])->toBe('left')
        ->and($menu['items'])->toBe([]);
});

it('accepts an explicit key and display options', function (): void {
    WireUpServer::tool(CreateMenuTool::class, [
        'name' => 'Guide nav',
        'key' => 'guide-nav',
        'position' => 'right',
        'sticky' => true,
        'background' => false,
        'mobile' => 'toggle',
    ])->assertOk();

    $menu = collect(SettingsService::current()->allMenus())->firstWhere('key', 'guide-nav');

    expect($menu['display'])->toMatchArray([
        'position' => 'right',
        'sticky' => true,
        'background' => false,
        'mobile' => 'toggle',
    ]);
});

it('refuses a menu key that is already taken', function (string $key): void {
    WireUpServer::tool(CreateMenuTool::class, ['name' => 'Another', 'key' => $key])
        ->assertHasErrors();
})->with(['header', 'footer']);

it('uniquifies a derived key instead of colliding', function (): void {
    WireUpServer::tool(CreateMenuTool::class, ['name' => 'Sidebar'])->assertOk();
    WireUpServer::tool(CreateMenuTool::class, ['name' => 'Sidebar'])->assertOk();

    $keys = array_column(SettingsService::current()->allMenus(), 'key');

    expect($keys)->toContain('sidebar')->toContain('sidebar-2');
});

it('requires a name', function (): void {
    WireUpServer::tool(CreateMenuTool::class, [])
        ->assertHasErrors(['Pass a name for the menu, such as "Docs sidebar".']);
});

it('shows a created menu beside a page as a sidebar', function (): void {
    $page = Page::factory()->create(['title' => 'Guides']);

    WireUpServer::tool(CreateMenuTool::class, ['name' => 'Docs sidebar'])->assertOk();

    WireUpServer::tool(UpdateMenuTool::class, [
        'menu' => 'docs-sidebar',
        'items' => [['type' => 'link', 'label' => 'Intro', 'url' => '/guides/intro']],
    ])->assertOk();

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'layout' => ['sidebar' => ['menus' => ['docs-sidebar']]],
    ])->assertOk();

    expect(layoutOf($page)['sidebar']['menus'])->toBe(['docs-sidebar']);
});

it('sets the layout flags, background colour and per-page css', function (): void {
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'layout' => [
            'hideHeader' => true,
            'hideFooter' => true,
            'backgroundFixed' => true,
            'backgroundColor' => '  #0b1220  ',
            'customCss' => 'main { color: red } </style><script>bad()</script>',
        ],
    ])->assertOk();

    $layout = layoutOf($page);

    expect($layout['hideHeader'])->toBeTrue()
        ->and($layout['hideFooter'])->toBeTrue()
        ->and($layout['backgroundFixed'])->toBeTrue()
        ->and($layout['backgroundColor'])->toBe('#0b1220')
        ->and($layout['customCss'])->not->toContain('</style');
});

it('changes only the layout keys it is given', function (): void {
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'layout' => ['hideHeader' => true, 'backgroundColor' => '#111111'],
    ])->assertOk();

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'layout' => ['hideFooter' => true],
    ])->assertOk();

    $layout = layoutOf($page);

    expect($layout['hideHeader'])->toBeTrue()
        ->and($layout['hideFooter'])->toBeTrue()
        ->and($layout['backgroundColor'])->toBe('#111111');
});

it('clears a background colour with an empty string', function (): void {
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'layout' => ['backgroundColor' => '#abcdef']])->assertOk();
    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'layout' => ['backgroundColor' => '']])->assertOk();

    expect(layoutOf($page)['backgroundColor'])->toBeNull();
});

it('sets and clears a background image by media id', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => 'image', 'source' => 'media/bg.jpg']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'layout' => ['backgroundImage' => $media->id]])->assertOk();

    expect(layoutOf($page)['backgroundImage']['source'])->toBe('media/bg.jpg');

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'layout' => ['backgroundImage' => null]])->assertOk();

    expect(layoutOf($page)['backgroundImage'])->toBeNull();
});

it('refuses a background that is not an image', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => 'document', 'source' => 'media/notes.pdf']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'layout' => ['backgroundImage' => $media->id]])
        ->assertHasErrors();
});

it('keeps noindex when layout is set in the same call', function (): void {
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'noindex' => true,
        'layout' => ['hideHeader' => true],
    ])->assertOk();

    $metadata = $page->fresh()->metadata;

    expect($metadata['noindex'])->toBeTrue()
        ->and($metadata['layout']['hideHeader'])->toBeTrue();
});

it('keeps existing metadata that layout does not touch', function (): void {
    $page = Page::factory()->create();
    $page->forceFill(['metadata' => ['published_locales' => ['en'], 'members_only' => true]])->saveQuietly();

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'layout' => ['hideFooter' => true]])->assertOk();

    $metadata = $page->fresh()->metadata;

    expect($metadata['published_locales'])->toBe(['en'])
        ->and($metadata['members_only'])->toBeTrue()
        ->and($metadata['layout']['hideFooter'])->toBeTrue();
});

it('rejects more than three sidebar menus', function (): void {
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'layout' => ['sidebar' => ['menus' => ['a', 'b', 'c', 'd']]],
    ])->assertHasErrors(['A page may show at most 3 sidebar menus.']);
});

it('advertises create-menu and the layout argument', function (): void {
    expect(resolve(CreateMenuTool::class)->toArray()['inputSchema']['required'])->toBe(['name'])
        ->and(resolve(UpdatePageTool::class)->toArray()['inputSchema']['properties'])->toHaveKey('layout');
});

it('renders the sidebar on the published page after wiring it through the tools', function (): void {
    $page = Page::factory()->create([
        'title' => 'Handbook',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'handbook']);

    WireUpServer::tool(CreateMenuTool::class, ['name' => 'Handbook nav', 'position' => 'left'])->assertOk();

    WireUpServer::tool(UpdateMenuTool::class, [
        'menu' => 'handbook-nav',
        'items' => [
            ['type' => 'heading', 'label' => 'Getting started'],
            ['type' => 'link', 'label' => 'Installation', 'url' => '/handbook#install'],
        ],
    ])->assertOk();

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'layout' => ['sidebar' => ['menus' => ['handbook-nav']]],
    ])->assertOk();

    $this->get(route('page', 'handbook'))
        ->assertOk()
        ->assertSee('data-site-sidebar', false)
        ->assertSee('Getting started')
        ->assertSee('Installation');
});
