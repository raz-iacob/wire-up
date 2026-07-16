<?php

declare(strict_types=1);

use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\ScaffoldSiteTool;
use App\Models\Page;
use App\Models\Settings;

function scaffoldedTitle(string $title): ?Page
{
    return Page::query()
        ->whereHas('translations', fn ($query) => $query->where('key', 'title')->where('locale', 'en')->where('body', $title))
        ->first();
}

it('advertises the scaffold-site tool with its schema', function (): void {
    $tool = resolve(ScaffoldSiteTool::class)->toArray();

    expect($tool['name'])->toBe('scaffold-site')
        ->and($tool['inputSchema']['required'])->toBe(['pages'])
        ->and($tool['inputSchema']['properties'])->toHaveKey('pages');
});

it('scaffolds pages, wires header and footer nav, and sets the homepage', function (): void {
    WireUpServer::tool(ScaffoldSiteTool::class, ['pages' => [
        ['title' => 'Welcome Home', 'homepage' => true, 'nav' => 'header'],
        ['title' => 'Company', 'nav' => 'both'],
        ['title' => 'Fine Print', 'nav' => 'none'],
    ]])
        ->assertOk()
        ->assertSee('Welcome Home')
        ->assertSee('Company')
        ->assertSee('Fine Print')
        ->assertSee('"created":true');

    $home = scaffoldedTitle('Welcome Home');
    expect($home)->not->toBeNull()
        ->and((int) Settings::get('home_page_id'))->toBe($home->id)
        ->and(scaffoldedTitle('Fine Print'))->not->toBeNull();

    $menus = Settings::get('menus');
    expect(collect($menus)->firstWhere('key', 'header')['items']['en'])->toHaveCount(2)
        ->and(collect($menus)->firstWhere('key', 'footer')['items']['en'])->toHaveCount(1);
});

it('reuses a page with an existing title and leaves the homepage unset', function (): void {
    Page::factory()->create(['title' => 'Our Studio Story']);
    $before = Settings::get('home_page_id');

    WireUpServer::tool(ScaffoldSiteTool::class, ['pages' => [
        ['title' => 'Our Studio Story', 'nav' => 'footer'],
        ['title' => 'The Journal', 'nav' => 'header'],
    ]])
        ->assertOk()
        ->assertSee('"created":false')
        ->assertSee('"created":true');

    expect(Page::query()->whereHas('translations', fn ($q) => $q->where('key', 'title')->where('body', 'Our Studio Story'))->count())->toBe(1)
        ->and(Settings::get('home_page_id'))->toBe($before);

    $menus = Settings::get('menus');
    expect(collect($menus)->firstWhere('key', 'footer')['items']['en'])->toHaveCount(1)
        ->and(collect($menus)->firstWhere('key', 'header')['items']['en'])->toHaveCount(1);
});

it('rejects duplicate titles in one scaffold call', function (): void {
    WireUpServer::tool(ScaffoldSiteTool::class, ['pages' => [
        ['title' => 'About Us'],
        ['title' => 'About Us'],
    ]])->assertHasErrors(['Page titles must be unique within one scaffold call.']);
});

it('rejects more than one homepage', function (): void {
    WireUpServer::tool(ScaffoldSiteTool::class, ['pages' => [
        ['title' => 'Page One', 'homepage' => true],
        ['title' => 'Page Two', 'homepage' => true],
    ]])->assertHasErrors(['Only one page can be the homepage.']);
});
