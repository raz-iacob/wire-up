<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\CreateRecordTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\GetRecordTool;
use App\Mcp\Tools\UpdatePageBlocksTool;
use App\Mcp\Tools\UpdateRecordTool;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\MediaItem;
use Illuminate\Support\Str;

/**
 * @return list<array<string, mixed>>
 */
function blocksWithBadItems(): array
{
    return [['type' => 'feature-cards', 'content' => ['items' => [
        ['id' => 'same', 'media' => 'icon', 'icon' => 'bug-ant', 'title' => ['en' => 'Bugs']],
        ['id' => 'same', 'media' => 'icon', 'icon' => 'bolt', 'title' => ['en' => 'Speed']],
    ]]]];
}

it('stores plain text as the default language and gives new items an id', function (): void {
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageBlocksTool::class, ['page' => $page->id, 'blocks' => [
        ['type' => 'feature-cards', 'content' => ['heading' => 'Why us', 'items' => [['title' => 'Fast', 'body' => '<p>Quick</p>']]]],
        ['type' => 'collection', 'content' => ['heading' => ['en' => 'Latest'], 'fields' => ['audience']]],
    ]])->assertOk();

    [$cards, $collection] = $page->refresh()->blocks->all();
    $item = $cards->content['items'][0];

    expect($cards->content['heading'])->toBe(['en' => 'Why us'])
        ->and($item['title'])->toBe(['en' => 'Fast'])
        ->and($item['body'])->toBe(['en' => '<p>Quick</p>'])
        ->and(Str::isUuid($item['id']))->toBeTrue()
        ->and($collection->content['fields'])->toBe(['audience']);
});

it('refuses icons that do not exist and repeated item ids, saving nothing', function (string $tool, Closure $arguments, Closure $unchanged): void {
    WireUpServer::tool($tool, $arguments())
        ->assertHasErrors([
            'Block #1 (feature-cards) has two items with the id "same".',
            'Block #1 (feature-cards), item "same": there is no icon called "bug-ant".',
            'Available icons: ',
        ]);

    expect($unchanged())->toBeTrue();
})->with([
    'create-page' => [CreatePageTool::class, fn (): array => ['title' => 'Bugs', 'blocks' => blocksWithBadItems()], fn (): bool => Page::query()->whereHas('translations', fn ($query) => $query->where('body', 'Bugs'))->doesntExist()],
    'update-page-blocks' => [UpdatePageBlocksTool::class, fn (): array => ['page' => Page::factory()->create()->id, 'blocks' => blocksWithBadItems()], fn (): bool => Page::query()->latest('id')->first()->blocks()->doesntExist()],
    'create-record' => [CreateRecordTool::class, fn (): array => ['type' => RecordType::factory()->create(['key' => 'guide'])->key, 'title' => 'Bugs', 'blocks' => blocksWithBadItems()], fn (): bool => Record::query()->doesntExist()],
    'update-record' => [UpdateRecordTool::class, fn (): array => ['record' => Record::factory()->create()->id, 'blocks' => blocksWithBadItems()], fn (): bool => Record::query()->first()->blocks()->doesntExist()],
]);

it('restores the library preview of an image placed by id', function (): void {
    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'mime_type' => 'image/png', 'source' => 'media/photo.png']);
    $page = Page::factory()->create();

    WireUpServer::tool(UpdatePageBlocksTool::class, ['page' => $page->id, 'blocks' => [
        ['type' => 'photo', 'content' => ['image' => ['id' => $media->id, 'source' => 'media/photo.png', 'metadata' => ['alt' => 'A photo']]]],
    ]])->assertOk();

    $library = MediaItem::fromMedia($media);

    expect($page->refresh()->blocks->first()->content['image'])
        ->toMatchArray(['preview' => $library['preview'], 'crop_src' => $library['crop_src'], 'metadata' => ['alt' => 'A photo']]);
});

it('leaves the generated preview keys out of a full page', function (): void {
    $page = Page::factory()->create();
    $page->updateBlocks([['type' => 'photo', 'content' => ['image' => ['id' => 1, 'source' => 'media/photo.png', 'preview' => '/img/preview', 'crop_src' => '/img/crop']]]]);

    WireUpServer::tool(GetPageTool::class, ['page' => $page->id])
        ->assertOk()
        ->assertSee('media/photo.png')
        ->assertDontSee(['/img/preview', 'crop_src']);
});

it('outlines the blocks of a page and a record', function (): void {
    $page = Page::factory()->create();
    $page->updateBlocks([
        ['type' => 'feature-cards', 'content' => ['heading' => ['en' => '<h2>Why us</h2>'], 'anchor' => 'why', 'items' => [
            ['id' => 'c-1', 'title' => ['en' => 'Fast &amp; light'], 'body' => ['en' => '<p>Long body text that stays out</p>']],
        ]]],
        ['type' => 'sponsors', 'content' => ['items' => [['id' => 's-1', 'logo' => null, 'name' => []]]]],
        ['type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Only a body here.</p>']]],
    ]);
    $record = Record::factory()->create();
    $record->updateBlocks([['type' => 'rich-text', 'content' => ['heading' => ['en' => 'Record heading'], 'body' => ['en' => '<p>Hidden body</p>']]]]);

    WireUpServer::tool(GetPageTool::class, ['page' => $page->id, 'outline' => true])
        ->assertOk()
        ->assertSee(['"heading":"Why us"', '"anchor":"why"', '{"id":"c-1","title":"Fast & light"}', '{"id":"s-1","title":""}', '"excerpt":"Only a body here."'])
        ->assertDontSee('Long body text');

    WireUpServer::tool(GetRecordTool::class, ['record' => $record->id, 'outline' => true])
        ->assertOk()
        ->assertSee('"heading":"Record heading"')
        ->assertDontSee('Hidden body');
});
