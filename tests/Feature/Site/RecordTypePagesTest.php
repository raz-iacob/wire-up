<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\BlockMarkdown;
use App\Services\SeoService;
use App\Services\SiteSearchQuery;

function serviceType(bool $detail = true, bool $index = false): RecordType
{
    return RecordType::factory()->create([
        'key' => 'service',
        'slug_prefix' => 'services',
        'has_detail_page' => $detail,
        'has_index_page' => $index,
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'overview', 'type' => 'rich-text', 'prefills' => 'description', 'translatable' => true],
        ],
    ]);
}

function serviceRecord(RecordType $type, string $title = 'Group coaching'): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'data' => ['heading' => ['en' => $title], 'overview' => ['en' => '<p>Weekly sessions.</p>']],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    return $record;
}

it('serves a record detail page by default', function (): void {
    serviceRecord(serviceType());

    test()->get('/services/group-coaching')->assertOk()->assertSee('Group coaching');
});

it('404s a record detail page for a type that has none', function (): void {
    serviceRecord(serviceType(detail: false));

    test()->get('/services/group-coaching')->assertNotFound();
});

it('still shows an unreachable record to staff, with a notice', function (): void {
    test()->actingAsAdmin();
    serviceRecord(serviceType(detail: false));

    test()->get('/services/group-coaching')
        ->assertOk()
        ->assertSee('no detail pages', false);
});

it('drops unreachable records from the sitemap', function (): void {
    serviceRecord(serviceType(detail: false));

    expect(SeoService::current()->sitemapXml())->not->toContain('/services/group-coaching');
});

it('keeps reachable records in the sitemap', function (): void {
    serviceRecord(serviceType());

    expect(SeoService::current()->sitemapXml())->toContain('/services/group-coaching');
});

it('leaves unreachable records out of site search', function (): void {
    $type = serviceType(detail: false);
    serviceRecord($type);

    expect(resolve(SiteSearchQuery::class)->search('coaching', [$type->id], 10))->toBe([]);
});

it('renders collection cards unlinked when there is no detail page', function (): void {
    $type = serviceType(detail: false);
    serviceRecord($type);

    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'our-services']);
    $page->blocks()->create([
        'type' => BlockType::COLLECTION,
        'position' => 0,
        'content' => ['recordTypeId' => $type->id, 'source' => 'latest', 'layout' => 'grid'],
    ]);

    $html = (string) test()->get('/our-services')->assertOk()->getContent();

    expect($html)->toContain('Group coaching')
        ->and($html)->not->toContain('href="'.url('/services/group-coaching').'"');
});

it('lists unreachable records without links in the markdown rendering', function (): void {
    $type = serviceType(detail: false);
    serviceRecord($type);

    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $block = $page->blocks()->create([
        'type' => BlockType::COLLECTION,
        'position' => 0,
        'content' => ['recordTypeId' => $type->id, 'source' => 'latest', 'layout' => 'grid'],
    ]);

    $markdown = (new BlockMarkdown)->render($block, 'en');

    expect($markdown)->toContain('Group coaching')->and($markdown)->not->toContain('](http');
});

it('404s the URL prefix until a listing is switched on', function (): void {
    serviceRecord(serviceType());

    test()->get('/services')->assertNotFound();
});

it('publishes a listing at the URL prefix when asked', function (): void {
    serviceRecord(serviceType(index: true));

    test()->get('/services')->assertOk()->assertSee('Group coaching');
});

it('lets a page with the same web address win over the listing', function (): void {
    serviceRecord(serviceType(index: true));

    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
        'title' => ['en' => 'Everything we offer'],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'services']);
    $page->blocks()->create([
        'type' => BlockType::RICH_TEXT,
        'position' => 0,
        'content' => ['heading' => ['en' => '<p>Everything we offer</p>'], 'body' => ['en' => '<p>Hand written.</p>']],
    ]);

    test()->get('/services')->assertOk()->assertSee('Hand written.');
});

it('leaves an unpublished record out of the listing', function (): void {
    $type = serviceType(index: true);
    serviceRecord($type);

    Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Draft service'],
        'data' => ['heading' => ['en' => 'Draft service']],
        'metadata' => ['published_locales' => []],
        'status' => ContentStatus::DRAFT,
    ])->setSlugs();

    test()->get('/services')->assertOk()->assertSee('Group coaching')->assertDontSee('Draft service');
});
