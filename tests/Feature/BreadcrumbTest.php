<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Settings;
use App\Services\BreadcrumbService;
use Illuminate\Database\Eloquent\Builder;

function crumbPage(string $slug, string $title, array $blocks = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => $title,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug, 'base_path' => '']);

    foreach ($blocks as $position => $block) {
        $page->blocks()->create([
            'type' => $block['type'],
            'position' => $position,
            'content' => $block['content'] ?? [],
        ]);
    }

    return $page->refresh();
}

function crumbHomePage(array $blocks = []): Page
{
    $home = Page::query()->whereHas('slugs', fn (Builder $query): mixed => $query->where('slug', 'home'))->firstOrFail();

    $home->update([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    foreach ($blocks as $position => $block) {
        $home->blocks()->create([
            'type' => $block['type'],
            'position' => 100 + $position,
            'content' => $block['content'] ?? [],
        ]);
    }

    Settings::set(['home_page_id' => $home->id]);

    return $home->refresh();
}

function crumbRecord(string $slug, string $title, bool $breadcrumbs = false): Record
{
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'store',
        'name' => 'Products',
        'breadcrumbs' => $breadcrumbs,
    ]);

    $record = Record::factory()->for($type)->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => $title,
    ]);
    $record->slugs()->create(['locale' => 'en', 'slug' => $slug, 'base_path' => 'store']);

    return $record;
}

it('builds a two-step trail for a page', function (): void {
    $page = crumbPage('store', 'Store');

    expect(BreadcrumbService::current()->trail($page))->toBe([
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Store', 'url' => null],
    ]);
});

it('builds no trail for the home page', function (): void {
    expect(BreadcrumbService::current()->trail(crumbHomePage()))->toBe([]);
});

it('links the record type landing page in the middle of a record trail', function (): void {
    $landing = crumbPage('store', 'Store');
    $record = crumbRecord('3060', '3060 (On Sale)');

    expect(BreadcrumbService::current()->trail($record))->toBe([
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Store', 'url' => $landing->getUrl()],
        ['label' => '3060 (On Sale)', 'url' => null],
    ]);
});

it('falls back to the record type name when no landing page exists', function (): void {
    $record = crumbRecord('3060', '3060 (On Sale)');

    expect(BreadcrumbService::current()->trail($record))->toBe([
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Products', 'url' => null],
        ['label' => '3060 (On Sale)', 'url' => null],
    ]);
});

it('drops the home crumb and relabels it on request', function (): void {
    $page = crumbPage('store', 'Store');
    $service = BreadcrumbService::current();

    expect($service->trail($page, withHome: false))->toBe([
        ['label' => 'Store', 'url' => null],
    ]);

    expect($service->trail($page, homeLabel: 'Start'))->toBe([
        ['label' => 'Start', 'url' => route('home')],
        ['label' => 'Store', 'url' => null],
    ]);
});

it('renders the breadcrumb block on a page', function (): void {
    crumbPage('store', 'Store', [
        ['type' => BlockType::BREADCRUMB->value, 'content' => BlockType::BREADCRUMB->defaultContent()],
    ]);

    $this->get(route('page', 'store'))
        ->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSeeInOrder(['Home', 'Store'], false);
});

it('renders the separator and home label from the block content', function (): void {
    crumbPage('store', 'Store', [
        [
            'type' => BlockType::BREADCRUMB->value,
            'content' => [...BlockType::BREADCRUMB->defaultContent(), 'separator' => '›', 'homeLabel' => ['en' => 'Start']],
        ],
    ]);

    $this->get(route('page', 'store'))
        ->assertOk()
        ->assertSee('›', false)
        ->assertSee('Start', false);
});

it('renders nothing for a breadcrumb block on the home page', function (): void {
    crumbHomePage([
        ['type' => BlockType::BREADCRUMB->value, 'content' => BlockType::BREADCRUMB->defaultContent()],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('aria-label="Breadcrumb"', false);
});

it('shows breadcrumbs on a record page when the content type opts in', function (): void {
    crumbPage('store', 'Store');
    crumbRecord('3060', '3060 (On Sale)', breadcrumbs: true);

    $this->get(route('record', ['recordType' => 'store', 'slug' => '3060']))
        ->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSeeInOrder(['Home', 'Store', '3060 (On Sale)'], false);
});

it('hides breadcrumbs on a record page when the content type opts out', function (): void {
    crumbPage('store', 'Store');
    crumbRecord('3060', '3060 (On Sale)');

    $this->get(route('record', ['recordType' => 'store', 'slug' => '3060']))
        ->assertOk()
        ->assertDontSee('aria-label="Breadcrumb"', false);
});

it('carries the visible trail into the breadcrumb structured data', function (): void {
    crumbPage('store', 'Store');
    crumbRecord('3060', '3060 (On Sale)', breadcrumbs: true);

    $content = (string) $this->get(route('record', ['recordType' => 'store', 'slug' => '3060']))
        ->assertOk()
        ->getContent();

    expect($content)->toContain('BreadcrumbList')
        ->toContain('"position":3');
});
