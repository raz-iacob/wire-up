<?php

declare(strict_types=1);

use App\Ai\Agents\SiteAssistant;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\DeleteMediaTool;
use App\Mcp\Tools\DeletePageTool;
use App\Mcp\Tools\DeleteRecordTool;
use App\Models\Block;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Settings;
use App\Models\Slug;
use Illuminate\Support\Facades\Storage;

it('deletes a page with its blocks, slugs and translations', function (): void {
    $page = Page::factory()->create(['title' => 'Scratch Page']);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'scratch-page']);
    $page->updateBlocks([['id' => 'new-1', 'type' => 'divider', 'content' => ['size' => 'medium']]]);

    WireUpServer::tool(DeletePageTool::class, ['page' => $page->id])
        ->assertOk()
        ->assertSee('Scratch Page');

    expect(Page::query()->whereKey($page->id)->exists())->toBeFalse()
        ->and(Slug::query()->where('sluggable_id', $page->id)->where('sluggable_type', 'page')->exists())->toBeFalse()
        ->and(Block::query()->where('blockable_id', $page->id)->where('blockable_type', 'page')->exists())->toBeFalse();
});

it('refuses to delete the homepage', function (): void {
    $page = Page::factory()->create(['title' => 'Front Door']);
    Settings::set(['home_page_id' => $page->id]);

    WireUpServer::tool(DeletePageTool::class, ['page' => $page->id])
        ->assertHasErrors()
        ->assertSee('is the homepage and cannot be deleted');

    expect(Page::query()->whereKey($page->id)->exists())->toBeTrue();
});

it('reports an unknown page id', function (): void {
    WireUpServer::tool(DeletePageTool::class, ['page' => 999999])
        ->assertHasErrors(['No page with id 999999. Use list-pages to see the available pages.']);
});

it('requires a page id', function (): void {
    WireUpServer::tool(DeletePageTool::class, [])
        ->assertHasErrors(['Pass the page id. Use list-pages to find it.']);
});

it('deletes a record', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides']);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => 'Old Guide']);

    WireUpServer::tool(DeleteRecordTool::class, ['record' => $record->id])
        ->assertOk()
        ->assertSee('Old Guide');

    expect(Record::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('reports an unknown record id', function (): void {
    WireUpServer::tool(DeleteRecordTool::class, ['record' => 999999])
        ->assertHasErrors(['No record with id 999999. Use list-records to see the available records.']);
});

it('requires a record id', function (): void {
    WireUpServer::tool(DeleteRecordTool::class, [])
        ->assertHasErrors(['Pass the record id. Use list-records to find it.']);
});

it('deletes an unused media file', function (): void {
    Storage::fake(config()->string('filesystems.media'));
    Storage::disk(config()->string('filesystems.media'))->put('media/stray.png', 'x');

    $media = Media::factory()->create(['source' => 'media/stray.png', 'filename' => 'stray.png', 'thumbnail' => null]);

    WireUpServer::tool(DeleteMediaTool::class, ['media' => $media->id])
        ->assertOk()
        ->assertSee('stray.png');

    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse();

    Storage::disk(config()->string('filesystems.media'))->assertMissing('media/stray.png');
});

it('refuses to delete media that is still in use and says what uses it', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    $media = Media::factory()->create(['filename' => 'in-use.png', 'thumbnail' => null]);
    $page = Page::factory()->create(['title' => 'Uses The Image']);
    $page->media()->attach($media, ['role' => 'og_image', 'locale' => 'en', 'position' => 0]);

    WireUpServer::tool(DeleteMediaTool::class, ['media' => $media->id])
        ->assertHasErrors()
        ->assertSee('still in use by');

    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});

it('reports an unknown media id', function (): void {
    WireUpServer::tool(DeleteMediaTool::class, ['media' => 999999])
        ->assertHasErrors(['No media with id 999999. Use list-media to see the library.']);
});

it('requires a media id', function (): void {
    WireUpServer::tool(DeleteMediaTool::class, [])
        ->assertHasErrors(['Pass the media id. Use list-media to find it.']);
});

it('gates every delete tool behind owner confirmation for the assistant', function (): void {
    expect(SiteAssistant::confirmableToolNames())
        ->toContain('delete-page')
        ->toContain('delete-record')
        ->toContain('delete-media')
        ->and(SiteAssistant::confirmableToolClass('delete-page'))->toBe(DeletePageTool::class)
        ->and(SiteAssistant::confirmableToolClass('delete-record'))->toBe(DeleteRecordTool::class)
        ->and(SiteAssistant::confirmableToolClass('delete-media'))->toBe(DeleteMediaTool::class);
});

it('advertises each delete tool with the id it needs', function (string $tool, string $name, string $argument): void {
    $advertised = resolve($tool)->toArray();

    expect($advertised['name'])->toBe($name)
        ->and($advertised['inputSchema']['required'])->toBe([$argument])
        ->and($advertised['inputSchema']['properties'])->toHaveKey($argument)
        ->and($advertised['description'])->toContain('cannot be undone');
})->with([
    'page' => [DeletePageTool::class, 'delete-page', 'page'],
    'record' => [DeleteRecordTool::class, 'delete-record', 'record'],
    'media' => [DeleteMediaTool::class, 'delete-media', 'media'],
]);
