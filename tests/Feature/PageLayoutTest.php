<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Settings;

function publishPageWithLayout(string $slug, array $layout): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en'], 'layout' => $layout],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ucfirst($slug),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('shows the header and footer by default', function (): void {
    publishPageWithLayout('with-chrome', []);

    $this->get(route('page', 'with-chrome'))
        ->assertOk()
        ->assertSee('<header', false)
        ->assertSee('<footer', false);
});

it('hides the header and footer when configured', function (): void {
    publishPageWithLayout('no-chrome', ['hideHeader' => true, 'hideFooter' => true]);

    $this->get(route('page', 'no-chrome'))
        ->assertOk()
        ->assertDontSee('<header', false)
        ->assertDontSee('<footer', false);
});

it('applies a background color', function (): void {
    publishPageWithLayout('styled', ['backgroundColor' => '#101820']);

    $this->get(route('page', 'styled'))
        ->assertOk()
        ->assertSee('background-color:#101820', false);
});

it('injects custom css and neutralizes style-tag breakout', function (): void {
    publishPageWithLayout('css', [
        'customCss' => '.promo { color: #bada55; } </style><script>alert(1)</script>',
    ]);

    $this->get(route('page', 'css'))
        ->assertOk()
        ->assertSee('.promo { color: #bada55; }', false)
        ->assertDontSee('</style><script>', false);
});

it('applies a fixed background image', function (): void {
    publishPageWithLayout('bg-image', [
        'backgroundImage' => ['source' => 'media/bg.jpg', 'crop' => []],
        'backgroundFixed' => true,
    ]);

    $this->get(route('page', 'bg-image'))
        ->assertOk()
        ->assertSee('background-image:url(', false)
        ->assertSee('media/bg.jpg', false)
        ->assertSee('background-attachment:fixed', false)
        ->assertSee('bg-cover', false);
});

it('includes the crop parameters in the background image url', function (): void {
    publishPageWithLayout('bg-cropped', [
        'backgroundImage' => [
            'source' => 'media/bg.jpg',
            'crop' => ['default' => ['crop_w' => 1600, 'crop_h' => 900, 'crop_x' => 10, 'crop_y' => 20]],
        ],
    ]);

    $this->get(route('page', 'bg-cropped'))
        ->assertOk()
        ->assertSee('crop=1600-900-10-20', false);
});

it('omits the fixed attachment when no image is set', function (): void {
    publishPageWithLayout('color-only', ['backgroundColor' => '#222222', 'backgroundFixed' => true]);

    $this->get(route('page', 'color-only'))
        ->assertOk()
        ->assertSee('background-color:#222222', false)
        ->assertDontSee('background-attachment:fixed', false);
});

it('renders a selected menu in the sidebar using its per-menu display settings', function (): void {
    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => false, 'position' => 'right', 'sticky' => true, 'mobile' => 'hide'],
        'items' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => 'https://example.com/install', 'icon' => 'home', 'badge' => 'New', 'badgeColor' => 'green'],
        ]],
    ]]]);

    publishPageWithLayout('handbook', ['sidebar' => ['menus' => ['docs-nav']]]);

    $this->get(route('page', 'handbook'))
        ->assertOk()
        ->assertSee('data-site-sidebar', false)
        ->assertSee('Installation')
        ->assertSee('https://example.com/install', false)
        ->assertSee('New')
        ->assertSee('md:sticky', false)
        ->assertSee('hidden md:block', false)
        ->assertSee('<main class="flex-1 flex flex-col">', false)
        ->assertDontSee('bg-(--wire-card-bg)', false);
});

it('renders the card background and padding only when the menu enables it', function (): void {
    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => true, 'position' => 'left', 'sticky' => false, 'mobile' => 'collapse'],
        'items' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => 'https://example.com/install'],
        ]],
    ]]]);

    publishPageWithLayout('handbook-bg', ['sidebar' => ['menus' => ['docs-nav']]]);

    $this->get(route('page', 'handbook-bg'))
        ->assertOk()
        ->assertSee('bg-(--wire-card-bg)', false)
        ->assertSee('p-6', false);
});

it('renders multiple group headings within a single sidebar menu', function (): void {
    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => false, 'position' => 'left', 'sticky' => false, 'mobile' => 'collapse'],
        'items' => ['en' => [
            ['type' => 'heading', 'appearance' => 'link', 'target' => '_self', 'label' => 'Guides', 'url' => ''],
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => 'https://example.com/install'],
            ['type' => 'heading', 'appearance' => 'link', 'target' => '_self', 'label' => 'Components', 'url' => ''],
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Accordion', 'url' => 'https://example.com/accordion'],
        ]],
    ]]]);

    publishPageWithLayout('grouped', ['sidebar' => ['menus' => ['docs-nav']]]);

    $this->get(route('page', 'grouped'))
        ->assertOk()
        ->assertSeeInOrder(['Guides', 'Installation', 'Components', 'Accordion']);
});

it('adds a scroll-spy hook to on-page anchor links in the sidebar', function (): void {
    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => false, 'position' => 'left', 'sticky' => false, 'mobile' => 'collapse'],
        'items' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => '#installation'],
        ]],
    ]]]);

    publishPageWithLayout('handbook-spy', ['sidebar' => ['menus' => ['docs-nav']]]);

    $this->get(route('page', 'handbook-spy'))
        ->assertOk()
        ->assertSee('data-spy="installation"', false)
        ->assertDontSee('aria-current="page"', false);
});

it('marks the current page item as active in the sidebar', function (): void {
    $page = publishPageWithLayout('handbook-active', ['sidebar' => ['menus' => ['docs-nav']]]);

    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => true, 'position' => 'left', 'sticky' => false, 'mobile' => 'collapse'],
        'items' => ['en' => [
            ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'This page', 'page_id' => $page->id],
        ]],
    ]]]);

    $this->get(route('page', 'handbook-active'))
        ->assertOk()
        ->assertSee('aria-current="page"', false);
});

it('renders a toggle button and drawer for a toggleable sidebar menu on mobile', function (): void {
    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => true, 'position' => 'left', 'sticky' => false, 'mobile' => 'toggle'],
        'items' => ['en' => [
            ['type' => 'heading', 'appearance' => 'link', 'target' => '_self', 'label' => 'Guides', 'url' => ''],
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => 'https://example.com/install'],
        ]],
    ]]]);

    publishPageWithLayout('toggle-nav', ['sidebar' => ['menus' => ['docs-nav']]]);

    $this->get(route('page', 'toggle-nav'))
        ->assertOk()
        ->assertSee('aria-expanded', false)
        ->assertSee('Guides')
        ->assertSee('Installation');
});

it('shows the current location on the toggle button as group and item', function (): void {
    $page = publishPageWithLayout('handbook-here', ['sidebar' => ['menus' => ['docs-nav']]]);

    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => true, 'position' => 'left', 'sticky' => false, 'mobile' => 'toggle'],
        'items' => ['en' => [
            ['type' => 'heading', 'appearance' => 'link', 'target' => '_self', 'label' => 'Get Started', 'url' => ''],
            ['type' => 'page', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'page_id' => $page->id],
        ]],
    ]]]);

    $this->get(route('page', 'handbook-here'))
        ->assertOk()
        ->assertSeeInOrder(['Get Started', 'Installation']);
});

it('opens a right-positioned toggle menu from the right and aligns its button', function (): void {
    Settings::set(['menus' => [[
        'key' => 'on-this-page',
        'name' => 'On this page',
        'builtin' => false,
        'display' => ['background' => false, 'position' => 'right', 'sticky' => false, 'mobile' => 'toggle'],
        'items' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Intro', 'url' => '#intro'],
        ]],
    ]]]);

    publishPageWithLayout('right-toggle', ['sidebar' => ['menus' => ['on-this-page']]]);

    $this->get(route('page', 'right-toggle'))
        ->assertOk()
        ->assertSee('ms-auto', false)
        ->assertSee('right-0', false)
        ->assertSee('max-md:order-last', false);
});

it('renders a horizontal nav on mobile for a stack-above-content menu', function (): void {
    Settings::set(['menus' => [[
        'key' => 'docs-nav',
        'name' => 'Docs',
        'builtin' => false,
        'display' => ['background' => false, 'position' => 'left', 'sticky' => false, 'mobile' => 'collapse'],
        'items' => ['en' => [
            ['type' => 'link', 'appearance' => 'link', 'target' => '_self', 'label' => 'Installation', 'url' => 'https://example.com/install'],
        ]],
    ]]]);

    publishPageWithLayout('stacked-nav', ['sidebar' => ['menus' => ['docs-nav']]]);

    $this->get(route('page', 'stacked-nav'))
        ->assertOk()
        ->assertSee('md:hidden', false)
        ->assertSee('Installation');
});

it('renders no sidebar region when no menus are selected', function (): void {
    publishPageWithLayout('plain', []);

    $this->get(route('page', 'plain'))
        ->assertOk()
        ->assertDontSee('data-site-sidebar', false)
        ->assertSee('overflow-y-auto', false);
});

it('omits an empty selected menu from the sidebar', function (): void {
    Settings::set(['menus' => menusPayload(['docs-nav' => ['en' => []]])]);

    publishPageWithLayout('empty-aside', ['sidebar' => ['menus' => ['docs-nav']]]);

    $this->get(route('page', 'empty-aside'))
        ->assertOk()
        ->assertDontSee('data-site-sidebar', false);
});
