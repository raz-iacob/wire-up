<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Settings;
use App\Models\User;

function stylesPage(string $slug, string $title, array $layout = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en'], 'layout' => $layout],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => $title,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('opens the admin without the site styles after signing in', function (): void {
    $user = User::factory()->admin()->create(['email' => 'styles@example.com', 'active' => true]);

    visit(route('login'))
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/admin')
        ->assertScript("[...document.querySelectorAll('link[rel=stylesheet]')].some((link) => /site(-[\\w]+)?\\.css/.test(link.href))", false)
        ->assertScript("document.head.textContent.includes('--wire-')", false)
        ->assertNoJavascriptErrors();
});

it('drops a page\'s own styles when leaving it through the logo', function (): void {
    $home = stylesPage('styles-home', 'Styles Home');
    Settings::set(['home_page_id' => $home->id]);
    stylesPage('styled', 'Styled Page', ['customCss' => '.only-on-styled { color: red; }']);

    visit('/styled')
        ->assertScript("document.documentElement.outerHTML.includes('only-on-styled')", true)
        ->click('header a[href="/"]')
        ->assertPathIs('/')
        ->assertTitleContains('Styles Home')
        ->assertScript("document.documentElement.outerHTML.includes('only-on-styled')", false)
        ->assertNoJavascriptErrors();
});
