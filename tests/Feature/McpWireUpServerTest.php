<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Resources\BlockTypesResource;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\PublishPageTool;
use App\Mcp\Tools\UpdatePageBlocksTool;
use App\Models\Page;

it('advertises every tool with its name and input schema', function (): void {
    $advertised = collect([
        ListPagesTool::class,
        GetPageTool::class,
        CreatePageTool::class,
        UpdatePageBlocksTool::class,
        PublishPageTool::class,
    ])->map(fn (string $tool): array => resolve($tool)->toArray());

    expect($advertised->pluck('name')->all())->toBe(['list-pages', 'get-page', 'create-page', 'update-page-blocks', 'publish-page'])
        ->and($advertised[1]['inputSchema']['required'])->toBe(['page'])
        ->and($advertised[2]['inputSchema']['required'])->toBe(['title'])
        ->and($advertised[2]['inputSchema']['properties'])->toHaveKeys(['title', 'description', 'blocks', 'publish'])
        ->and($advertised[3]['inputSchema']['properties'])->toHaveKeys(['page', 'blocks'])
        ->and($advertised[4]['inputSchema']['properties']['status']['enum'])->toBe(['published', 'draft']);
});

it('documents every block type in the block-types resource', function (): void {
    $response = WireUpServer::resource(BlockTypesResource::class);

    $response->assertOk()
        ->assertSee('localizedText')
        ->assertSee('"key":"hero"')
        ->assertSee('"key":"rich-text"')
        ->assertSee('"key":"contact-form"')
        ->assertSee('defaultContent');
});

it('lists pages with slug, url, status and homepage flag', function (): void {
    $draft = Page::factory()->create(['title' => 'Unlinked Draft', 'status' => ContentStatus::DRAFT]);

    $response = WireUpServer::tool(ListPagesTool::class);

    $response->assertOk()
        ->assertSee('"is_homepage":true')
        ->assertSee('"slug":"welcome"')
        ->assertSee('Unlinked Draft')
        ->assertSee('"id":'.$draft->id);
});

it('gets a page in full including its blocks', function (): void {
    $page = Page::factory()->create(['title' => 'Detailed Page', 'description' => 'A described page.']);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'detailed-page']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Block body</p>']]],
    ]);

    $response = WireUpServer::tool(GetPageTool::class, ['page' => $page->id]);

    $response->assertOk()
        ->assertSee('Detailed Page')
        ->assertSee('A described page.')
        ->assertSee('detailed-page')
        ->assertSee('"type":"rich-text"')
        ->assertSee('Block body');
});

it('returns a friendly error when getting an unknown page', function (): void {
    WireUpServer::tool(GetPageTool::class, ['page' => 999999])
        ->assertHasErrors(['No page with id 999999. Use list-pages to see the available pages.']);
});

it('validates the page id when getting a page', function (): void {
    WireUpServer::tool(GetPageTool::class, ['page' => 'nope'])
        ->assertHasErrors()
        ->assertSee('The page id must be an integer.');
});

it('creates a draft page with blocks', function (): void {
    $response = WireUpServer::tool(CreatePageTool::class, [
        'title' => 'Agent Made',
        'description' => 'Made over MCP.',
        'blocks' => [
            ['type' => 'hero', 'content' => ['heading' => ['en' => 'Hello agents']]],
            ['type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Copy</p>']]],
        ],
    ]);

    $response->assertOk()
        ->assertSee('agent-made')
        ->assertSee('"status":"draft"');

    $page = Page::query()->with('blocks')->latest('id')->firstOrFail();

    expect($page->title)->toBe('Agent Made')
        ->and($page->description)->toBe('Made over MCP.')
        ->and($page->status)->toBe(ContentStatus::DRAFT)
        ->and($page->blocks)->toHaveCount(2)
        ->and($page->blocks->first()->type->value)->toBe('hero');
});

it('creates and publishes a page in one call', function (): void {
    WireUpServer::tool(CreatePageTool::class, ['title' => 'Live Right Away', 'publish' => true])
        ->assertOk()
        ->assertSee('"status":"published"');

    $page = Page::query()->latest('id')->firstOrFail();

    expect($page->status)->toBe(ContentStatus::PUBLISHED)
        ->and($page->published_at)->not->toBeNull();
});

it('refuses to create a page whose title already exists', function (): void {
    $existing = Page::factory()->create(['title' => 'Duplicate Me']);
    $before = Page::query()->count();

    WireUpServer::tool(CreatePageTool::class, ['title' => 'Duplicate Me'])
        ->assertHasErrors()
        ->assertSee('already exists (id '.$existing->id.')');

    expect(Page::query()->count())->toBe($before);
});

it('rejects unknown block types with a friendly message', function (): void {
    $before = Page::query()->count();

    WireUpServer::tool(CreatePageTool::class, [
        'title' => 'Broken Blocks',
        'blocks' => [['type' => 'carousel-3000', 'content' => []]],
    ])
        ->assertHasErrors()
        ->assertSee('Unknown block type. Valid types are: hero,');

    expect(Page::query()->count())->toBe($before);
});

it('replaces page blocks keeping the ones referenced by id', function (): void {
    $page = Page::factory()->create(['title' => 'Reblocked']);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'reblocked']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Keep me']]],
        ['id' => 'new-2', 'type' => 'divider', 'content' => ['size' => 'medium']],
    ]);

    $kept = $page->blocks()->where('type', 'hero')->firstOrFail();

    $response = WireUpServer::tool(UpdatePageBlocksTool::class, [
        'page' => $page->id,
        'blocks' => [
            ['id' => $kept->id, 'type' => 'hero', 'content' => ['heading' => ['en' => 'Kept and updated']]],
            ['type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Fresh</p>']]],
        ],
    ]);

    $response->assertOk()->assertSee('"blocks":2');

    $blocks = $page->refresh()->blocks;

    expect($blocks)->toHaveCount(2)
        ->and($blocks->first()->id)->toBe($kept->id)
        ->and($blocks->first()->text('heading'))->toBe('Kept and updated')
        ->and($blocks->last()->type->value)->toBe('rich-text');
});

it('returns a friendly error when updating blocks on an unknown page', function (): void {
    WireUpServer::tool(UpdatePageBlocksTool::class, ['page' => 999999, 'blocks' => []])
        ->assertHasErrors(['No page with id 999999. Use list-pages to see the available pages.']);
});

it('requires the block list to be present when updating blocks', function (): void {
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageBlocksTool::class, ['page' => $page->id])
        ->assertHasErrors()
        ->assertSee('Pass the full list of blocks the page should have.');
});

it('publishes and unpublishes a page', function (): void {
    $page = Page::factory()->create(['title' => 'Toggle Me', 'status' => ContentStatus::DRAFT]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'toggle-me']);

    WireUpServer::tool(PublishPageTool::class, ['page' => $page->id])
        ->assertOk()
        ->assertSee('"status":"published"')
        ->assertSee('Toggle Me');

    expect($page->refresh()->status)->toBe(ContentStatus::PUBLISHED)
        ->and($page->title)->toBe('Toggle Me');

    WireUpServer::tool(PublishPageTool::class, ['page' => $page->id, 'status' => 'draft'])
        ->assertOk()
        ->assertSee('"status":"draft"');

    expect($page->refresh()->status)->toBe(ContentStatus::DRAFT)
        ->and($page->published_at)->toBeNull();
});

it('returns a friendly error when publishing an unknown page', function (): void {
    WireUpServer::tool(PublishPageTool::class, ['page' => 999999])
        ->assertHasErrors(['No page with id 999999. Use list-pages to see the available pages.']);
});
