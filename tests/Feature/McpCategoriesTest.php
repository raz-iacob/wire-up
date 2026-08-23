<?php

declare(strict_types=1);

use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\CreateRecordTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Models\Category;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;

it('creates a category and returns its id', function (): void {
    WireUpServer::tool(CreateCategoryTool::class, ['name' => '  Guides  '])
        ->assertOk()
        ->assertSee('"created":true');

    $category = Category::query()->with('translations')->sole();

    expect($category->name)->toBe('Guides');
});

it('reuses a category with the same name instead of duplicating it', function (): void {
    WireUpServer::tool(CreateCategoryTool::class, ['name' => 'Guides'])->assertOk();

    WireUpServer::tool(CreateCategoryTool::class, ['name' => 'guides'])
        ->assertOk()
        ->assertSee('"created":false');

    expect(Category::query()->count())->toBe(1);
});

it('requires a category name', function (): void {
    WireUpServer::tool(CreateCategoryTool::class, [])
        ->assertHasErrors(['Pass a name for the category, such as "Guides".']);
});

it('lists categories with their usage counts', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides']);
    $guides = Category::factory()->create(['name' => ['en' => 'Guides']]);
    $news = Category::factory()->create(['name' => ['en' => 'Announcements']]);

    Record::factory()->create(['record_type_id' => $type->id])->categories()->attach($guides);
    Page::factory()->create()->categories()->attach($guides);

    $response = WireUpServer::tool(ListCategoriesTool::class, []);

    $response->assertOk()->assertSee('Guides')->assertSee('Announcements');

    expect($news->fresh()->records()->count())->toBe(0);

    $response->assertSee('"pages":1')->assertSee('"records":1');
});

it('says how to create one when there are no categories', function (): void {
    WireUpServer::tool(ListCategoriesTool::class, [])
        ->assertOk()
        ->assertSee('No categories yet');
});

it('attaches a created category to a new record', function (): void {
    RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'name' => 'Guides']);

    WireUpServer::tool(CreateCategoryTool::class, ['name' => 'Getting started'])->assertOk();

    $category = Category::query()->sole();

    WireUpServer::tool(CreateRecordTool::class, [
        'type' => 'guide',
        'title' => 'Installing Wire-Up',
        'categories' => [$category->id],
    ])->assertOk();

    expect(Record::query()->sole()->categories->pluck('id')->all())->toBe([$category->id]);
});

it('advertises both category tools', function (): void {
    expect(resolve(CreateCategoryTool::class)->toArray()['inputSchema']['required'])->toBe(['name'])
        ->and(resolve(ListCategoriesTool::class)->toArray()['name'])->toBe('list-categories');
});
