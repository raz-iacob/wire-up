<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateContentTypeTool;
use App\Mcp\Tools\CreateRecordTool;
use App\Mcp\Tools\GetRecordTool;
use App\Mcp\Tools\ListContentTypesTool;
use App\Mcp\Tools\ListRecordsTool;
use App\Mcp\Tools\PublishRecordTool;
use App\Mcp\Tools\UpdateContentTypeTool;
use App\Mcp\Tools\UpdateRecordTool;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;

/**
 * @param  array<string, mixed>  $overrides
 */
function mcpProductType(array $overrides = []): RecordType
{
    return RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'name' => 'Products',
        'icon' => 'shopping-bag',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'required' => false, 'translatable' => true, 'column' => false, 'sortable' => false, 'searchable' => false, 'help' => '', 'options' => [], 'prefills' => 'title'],
            ['key' => 'sku', 'type' => 'text', 'label' => ['en' => 'SKU'], 'required' => false, 'translatable' => false, 'column' => true, 'sortable' => false, 'searchable' => true, 'help' => '', 'options' => [], 'prefills' => null],
            ['key' => 'price', 'type' => 'money', 'label' => ['en' => 'Price'], 'required' => false, 'translatable' => false, 'column' => true, 'sortable' => true, 'searchable' => false, 'help' => '', 'options' => [], 'prefills' => null],
            ['key' => 'size', 'type' => 'select', 'label' => ['en' => 'Size'], 'required' => false, 'translatable' => false, 'column' => false, 'sortable' => false, 'searchable' => false, 'help' => '', 'options' => ['S', 'M', 'L'], 'prefills' => null],
            ['key' => 'gallery', 'type' => 'media-gallery', 'label' => ['en' => 'Gallery'], 'required' => false, 'translatable' => false, 'column' => false, 'sortable' => false, 'searchable' => false, 'help' => '', 'options' => [], 'prefills' => null],
        ],
        ...$overrides,
    ]);
}

it('advertises the record tools with their names and required schema', function (): void {
    $advertised = collect([
        ListContentTypesTool::class,
        CreateContentTypeTool::class,
        UpdateContentTypeTool::class,
        ListRecordsTool::class,
        GetRecordTool::class,
        CreateRecordTool::class,
        UpdateRecordTool::class,
        PublishRecordTool::class,
    ])->map(fn (string $tool): array => resolve($tool)->toArray());

    expect($advertised->pluck('name')->all())->toBe([
        'list-content-types', 'create-content-type', 'update-content-type',
        'list-records', 'get-record', 'create-record', 'update-record', 'publish-record',
    ])
        ->and($advertised[3]['inputSchema']['required'])->toBe(['type'])
        ->and($advertised[5]['inputSchema']['required'])->toBe(['type', 'title'])
        ->and($advertised[7]['inputSchema']['required'])->toBe(['record']);
});

it('lists content types with their fields, record count, and presets', function (): void {
    $type = mcpProductType();
    Record::factory()->create(['record_type_id' => $type->id]);

    WireUpServer::tool(ListContentTypesTool::class)
        ->assertOk()
        ->assertSee('"key":"product"')
        ->assertSee('"record_count":1')
        ->assertSee('"slug_prefix":"products"')
        ->assertSee('"available_presets"');
});

it('creates a content type from a preset', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, ['preset' => 'post'])
        ->assertOk()
        ->assertSee('"key":"post"')
        ->assertSee('"slug_prefix":"blog"');

    expect(RecordType::query()->where('key', 'post')->exists())->toBeTrue();
});

it('rejects an unknown preset', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, ['preset' => 'nope'])
        ->assertHasErrors(['No preset']);
});

it('creates a content type with custom fields and derives the slug prefix', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, [
        'name' => 'Case study',
        'fields' => [
            ['key' => 'client', 'type' => 'text', 'label' => 'Client'],
            ['key' => 'tier', 'type' => 'select', 'label' => 'Tier', 'options' => ['Gold', 'Silver', '']],
        ],
    ])
        ->assertOk()
        ->assertSee('"slug_prefix":"case-studies"');

    $type = RecordType::query()->where('slug_prefix', 'case-studies')->firstOrFail();

    expect($type->fields)->toHaveCount(2)
        ->and($type->fields[0]['translatable'])->toBeTrue()
        ->and($type->fields[1]['options'])->toBe(['Gold', 'Silver']);
});

it('requires a name when no preset is given', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, [])
        ->assertHasErrors(['Pass a "name"']);
});

it('rejects a reserved url prefix', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, ['name' => 'Admin things', 'slug_prefix' => 'admin'])
        ->assertHasErrors(['That URL prefix is reserved. Choose another.']);
});

it('rejects a url prefix already used by another type', function (): void {
    mcpProductType();

    WireUpServer::tool(CreateContentTypeTool::class, ['name' => 'More', 'slug_prefix' => 'products'])
        ->assertHasErrors(['already in use']);
});

it('rejects a url prefix already used by a page slug', function (): void {
    $page = Page::factory()->create(['title' => 'Showcase']);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'showcase', 'base_path' => '']);

    WireUpServer::tool(CreateContentTypeTool::class, ['name' => 'Showcase things', 'slug_prefix' => 'showcase'])
        ->assertHasErrors(['already in use']);
});

it('gives colliding custom types a unique key', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, ['name' => 'Widget', 'slug_prefix' => 'widgets-a'])->assertOk();
    WireUpServer::tool(CreateContentTypeTool::class, ['name' => 'Widget', 'slug_prefix' => 'widgets-b'])->assertOk();

    expect(RecordType::query()->whereIn('key', ['widget', 'widget_1'])->count())->toBe(2);
});

it('rejects invalid field definitions', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, [
        'name' => 'Bad',
        'fields' => [['key' => 'title', 'type' => 'text', 'label' => 'X']],
    ])->assertHasErrors(['That field key is reserved. Choose another: title, description, slug, status, published_at, og_image.']);
});

it('updates a content type name, icon, prefix, and fields', function (): void {
    $type = mcpProductType();

    WireUpServer::tool(UpdateContentTypeTool::class, [
        'type' => 'product',
        'name' => 'Gear',
        'icon' => 'wrench',
        'slug_prefix' => 'gear',
        'fields' => [['key' => 'sku', 'type' => 'text', 'label' => 'SKU']],
    ])
        ->assertOk()
        ->assertSee('"name":"Gear"')
        ->assertSee('"slug_prefix":"gear"');

    $type->refresh();

    expect($type->name)->toBe('Gear')
        ->and($type->icon)->toBe('wrench')
        ->and($type->fields)->toHaveCount(1);
});

it('leaves a content type untouched when no attributes are passed', function (): void {
    mcpProductType();

    WireUpServer::tool(UpdateContentTypeTool::class, ['type' => 'product'])
        ->assertOk()
        ->assertSee('"name":"Products"');
});

it('rejects updating an unknown content type', function (): void {
    WireUpServer::tool(UpdateContentTypeTool::class, ['type' => 'ghost', 'name' => 'X'])
        ->assertHasErrors(['No content type with key']);
});

it('rejects a prefix taken by another type when updating', function (): void {
    mcpProductType();
    RecordType::factory()->create(['key' => 'service', 'slug_prefix' => 'services', 'name' => 'Services']);

    WireUpServer::tool(UpdateContentTypeTool::class, ['type' => 'service', 'slug_prefix' => 'products'])
        ->assertHasErrors(['already in use']);
});

it('creates a record with field data, blocks, categories, and media', function (): void {
    $type = mcpProductType();
    $category = Category::factory()->create();
    $media = Media::factory()->create();

    WireUpServer::tool(CreateRecordTool::class, [
        'type' => 'product',
        'title' => 'Trail Runner',
        'description' => 'A fast shoe',
        'data' => [
            'heading' => ['en' => 'Trail Runner Pro'],
            'sku' => 'TR-1',
            'price' => '129.95',
            'size' => 'M',
        ],
        'categories' => [$category->id],
        'media' => ['gallery' => [$media->id]],
        'blocks' => [['type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Hello</p>']]]],
        'publish' => true,
    ])
        ->assertOk()
        ->assertSee('Trail Runner')
        ->assertSee('"status":"published"');

    $record = Record::query()->where('record_type_id', $type->id)->firstOrFail();

    expect($record->title)->toBe('Trail Runner')
        ->and($record->data['sku'])->toBe('TR-1')
        ->and($record->data['price'])->toBe(129.95)
        ->and($record->status)->toBe(ContentStatus::PUBLISHED)
        ->and($record->blocks)->toHaveCount(1)
        ->and($record->categories)->toHaveCount(1)
        ->and($record->media->pluck('id')->all())->toBe([$media->id])
        ->and($record->getSlug())->toBe('trail-runner');
});

it('creates a record as a draft by default', function (): void {
    mcpProductType();

    WireUpServer::tool(CreateRecordTool::class, ['type' => 'product', 'title' => 'Draft Item'])
        ->assertOk()
        ->assertSee('"status":"draft"');
});

it('rejects creating a record for an unknown type', function (): void {
    WireUpServer::tool(CreateRecordTool::class, ['type' => 'ghost', 'title' => 'Nope'])
        ->assertHasErrors(['No content type with key']);
});

it('rejects attaching media to a non-media field', function (): void {
    mcpProductType();
    $media = Media::factory()->create();

    WireUpServer::tool(CreateRecordTool::class, [
        'type' => 'product',
        'title' => 'Bad Media',
        'media' => ['sku' => [$media->id]],
    ])
        ->assertHasErrors(['is not a media field']);
});

it('lists records with status and search filters', function (): void {
    $type = mcpProductType();
    Record::factory()->create(['record_type_id' => $type->id]);
    WireUpServer::tool(CreateRecordTool::class, ['type' => 'product', 'title' => 'Findable', 'data' => ['sku' => 'FIND-9'], 'publish' => true])->assertOk();

    WireUpServer::tool(ListRecordsTool::class, ['type' => 'product'])
        ->assertOk()
        ->assertSee('Findable');

    WireUpServer::tool(ListRecordsTool::class, ['type' => 'product', 'status' => 'published', 'search' => 'FIND', 'limit' => 5])
        ->assertOk()
        ->assertSee('Findable');
});

it('rejects listing records for an unknown type', function (): void {
    WireUpServer::tool(ListRecordsTool::class, ['type' => 'ghost'])
        ->assertHasErrors(['No content type with key']);
});

it('lists a record with no slug as having no url', function (): void {
    $type = mcpProductType();
    Record::factory()->create(['record_type_id' => $type->id, 'title' => '']);

    WireUpServer::tool(ListRecordsTool::class, ['type' => 'product'])
        ->assertOk()
        ->assertSee('"url":null');
});

it('gets a record in full', function (): void {
    $type = mcpProductType();
    $media = Media::factory()->create();
    $create = WireUpServer::tool(CreateRecordTool::class, [
        'type' => 'product',
        'title' => 'Detailed',
        'data' => ['sku' => 'DET-1'],
        'media' => ['gallery' => [$media->id]],
    ]);
    $create->assertOk();

    $record = Record::query()->where('record_type_id', $type->id)->firstOrFail();

    WireUpServer::tool(GetRecordTool::class, ['record' => $record->id])
        ->assertOk()
        ->assertSee('"sku":"DET-1"')
        ->assertSee('"gallery"')
        ->assertSee('"fields"');
});

it('rejects getting an unknown record', function (): void {
    WireUpServer::tool(GetRecordTool::class, ['record' => 999])
        ->assertHasErrors(['No record with id 999']);
});

it('updates a record and preserves its published status when status is omitted', function (): void {
    $type = mcpProductType();
    $media = Media::factory()->create();
    WireUpServer::tool(CreateRecordTool::class, ['type' => 'product', 'title' => 'Live One', 'data' => ['sku' => 'A'], 'publish' => true])->assertOk();
    $record = Record::query()->where('record_type_id', $type->id)->firstOrFail();

    WireUpServer::tool(UpdateRecordTool::class, [
        'record' => $record->id,
        'title' => 'Live One Updated',
        'description' => 'Now better',
        'data' => ['sku' => 'B'],
        'media' => ['gallery' => [$media->id]],
        'blocks' => [['type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Body</p>']]]],
    ])
        ->assertOk()
        ->assertSee('"status":"published"');

    $record->refresh();

    expect($record->title)->toBe('Live One Updated')
        ->and($record->data['sku'])->toBe('B')
        ->and($record->status)->toBe(ContentStatus::PUBLISHED)
        ->and($record->blocks)->toHaveCount(1)
        ->and($record->media->pluck('id')->all())->toBe([$media->id]);
});

it('changes a record status and category set', function (): void {
    $type = mcpProductType();
    $category = Category::factory()->create();
    WireUpServer::tool(CreateRecordTool::class, ['type' => 'product', 'title' => 'Toggle', 'publish' => true])->assertOk();
    $record = Record::query()->where('record_type_id', $type->id)->firstOrFail();

    WireUpServer::tool(UpdateRecordTool::class, [
        'record' => $record->id,
        'status' => 'private',
        'categories' => [$category->id],
    ])->assertOk();

    $record->refresh();

    expect($record->status)->toBe(ContentStatus::PRIVATE)
        ->and($record->categories)->toHaveCount(1);
});

it('keeps a scheduled record scheduled when updated without a status', function (): void {
    $type = mcpProductType();
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => 'Soon',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->addWeek(),
    ]);

    WireUpServer::tool(UpdateRecordTool::class, ['record' => $record->id, 'data' => ['sku' => 'S']])
        ->assertOk()
        ->assertSee('"status":"published"');

    expect($record->refresh()->computed_status)->toBe(ContentStatus::SCHEDULED);
});

it('rejects updating an unknown record', function (): void {
    WireUpServer::tool(UpdateRecordTool::class, ['record' => 999, 'title' => 'Ghost Record'])
        ->assertHasErrors(['No record with id 999']);
});

it('rejects updating media on a non-media field', function (): void {
    $type = mcpProductType();
    $record = Record::factory()->create(['record_type_id' => $type->id]);
    $media = Media::factory()->create();

    WireUpServer::tool(UpdateRecordTool::class, ['record' => $record->id, 'media' => ['sku' => [$media->id]]])
        ->assertHasErrors(['is not a media field']);
});

it('publishes and unpublishes a record', function (): void {
    $type = mcpProductType();
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => 'Pub', 'status' => ContentStatus::DRAFT]);
    $record->setSlugs();

    WireUpServer::tool(PublishRecordTool::class, ['record' => $record->id])
        ->assertOk()
        ->assertSee('"status":"published"');

    expect($record->refresh()->status)->toBe(ContentStatus::PUBLISHED);

    WireUpServer::tool(PublishRecordTool::class, ['record' => $record->id, 'status' => 'draft'])
        ->assertOk()
        ->assertSee('"status":"draft"');

    expect($record->refresh()->status)->toBe(ContentStatus::DRAFT);
});

it('rejects publishing an unknown record', function (): void {
    WireUpServer::tool(PublishRecordTool::class, ['record' => 999])
        ->assertHasErrors(['No record with id 999']);
});
