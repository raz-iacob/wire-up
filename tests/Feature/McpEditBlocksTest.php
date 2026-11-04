<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\EditBlocksTool;
use App\Models\Block;
use App\Models\Page;
use App\Models\Record;
use Illuminate\Support\Str;

function editablePage(): Page
{
    $page = Page::factory()->create(['title' => 'Editable', 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->updateBlocks([
        ['type' => 'hero', 'content' => ['heading' => ['en' => 'Welcome', 'nl' => 'Welkom'], 'align' => 'center']],
        ['type' => 'feature-cards', 'content' => ['heading' => ['en' => 'Features'], 'items' => [
            ['id' => 'c-1', 'media' => 'icon', 'icon' => 'bolt', 'title' => ['en' => 'Fast'], 'body' => ['en' => '<p>Quick.</p>']],
            ['id' => 'c-2', 'media' => 'icon', 'icon' => 'tag', 'title' => ['en' => 'Cheap'], 'body' => ['en' => '<p>Low.</p>']],
        ]]],
        ['type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Closing words.</p>']]],
    ]);

    return $page->load('blocks');
}

/**
 * @return array{hero: int, cards: int, closing: int}
 */
function editableIds(Page $page): array
{
    return array_combine(['hero', 'cards', 'closing'], $page->blocks->pluck('id')->all());
}

it('advertises the edit-blocks tool with its schema', function (): void {
    $tool = resolve(EditBlocksTool::class)->toArray();

    expect($tool['name'])->toBe('edit-blocks')
        ->and($tool['inputSchema']['required'])->toBe(['changes'])
        ->and($tool['inputSchema']['properties'])->toHaveKeys(['page', 'record', 'changes'])
        ->and(WireUpServer::TOOLS)->toContain(EditBlocksTool::class);
});

it('merges a change into one block and one item, leaving the rest as it was', function (): void {
    $page = editablePage();
    $ids = editableIds($page);
    $closing = $page->blocks->firstWhere('id', $ids['closing'])->content;

    WireUpServer::tool(EditBlocksTool::class, ['page' => $page->id, 'changes' => [
        ['action' => 'update', 'block' => $ids['hero'], 'content' => ['heading' => 'Hello there']],
        ['action' => 'update-item', 'block' => $ids['cards'], 'item' => 'c-2', 'content' => ['title' => ['en' => 'Affordable']]],
    ]])
        ->assertOk()
        ->assertSee(['"changes":2', 'Hello there', 'Affordable']);

    $blocks = $page->refresh()->blocks->keyBy('id');

    expect($blocks[$ids['hero']]->content['heading'])->toBe(['en' => 'Hello there', 'nl' => 'Welkom'])
        ->and($blocks[$ids['hero']]->content['align'])->toBe('center')
        ->and($blocks[$ids['cards']]->content['items'][1])->toMatchArray(['id' => 'c-2', 'icon' => 'tag', 'title' => ['en' => 'Affordable'], 'body' => ['en' => '<p>Low.</p>']])
        ->and($blocks[$ids['closing']]->content)->toBe($closing)
        ->and($page->computed_status)->toBe(ContentStatus::PUBLISHED);
});

it('adds, moves and removes blocks and items in one call', function (): void {
    $page = editablePage();
    $ids = editableIds($page);

    WireUpServer::tool(EditBlocksTool::class, ['page' => $page->id, 'changes' => [
        ['action' => 'add', 'type' => 'rich-text', 'after' => $ids['hero'], 'content' => ['body' => '<p>Intro</p>']],
        ['action' => 'add', 'type' => 'divider', 'after' => 'new-1'],
        ['action' => 'add', 'type' => 'spacer'],
        ['action' => 'move', 'block' => $ids['closing'], 'before' => $ids['hero']],
        ['action' => 'remove', 'block' => $ids['hero']],
        ['action' => 'add-item', 'block' => $ids['cards'], 'before' => 'c-1', 'content' => ['media' => 'icon', 'icon' => 'star', 'title' => 'Loved']],
        ['action' => 'add-item', 'block' => $ids['cards'], 'after' => 'c-1', 'content' => ['id' => 'c-mid', 'title' => 'Middle']],
        ['action' => 'remove-item', 'block' => $ids['cards'], 'item' => 'c-2'],
    ]])->assertOk();

    $blocks = $page->refresh()->blocks;
    $items = $blocks->firstWhere('id', $ids['cards'])->content['items'];

    expect($blocks->map(fn (Block $block): string => $block->type->value)->all())->toBe(['rich-text', 'rich-text', 'divider', 'feature-cards', 'spacer'])
        ->and($blocks->first()->id)->toBe($ids['closing'])
        ->and($blocks[1]->content['body'])->toBe(['en' => '<p>Intro</p>'])
        ->and(Block::query()->find($ids['hero']))->toBeNull()
        ->and(Str::isUuid($items[0]['id']))->toBeTrue()
        ->and($items[0]['title'])->toBe(['en' => 'Loved'])
        ->and(array_column($items, 'id'))->toBe([$items[0]['id'], 'c-1', 'c-mid']);
});

it('edits the blocks of a record and keeps it published', function (): void {
    $record = Record::factory()->create(['title' => 'Guide', 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $record->updateBlocks([['type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Old</p>']]]]);

    WireUpServer::tool(EditBlocksTool::class, ['record' => $record->id, 'changes' => [
        ['action' => 'update', 'block' => $record->blocks()->value('id'), 'content' => ['body' => ['en' => '<p>New</p>']]],
    ]])
        ->assertOk()
        ->assertSee('"record":');

    expect($record->refresh()->blocks->first()->content['body'])->toBe(['en' => '<p>New</p>'])
        ->and($record->computed_status)->toBe(ContentStatus::PUBLISHED);
});

it('refuses a change that points at nothing and saves none of the call', function (array $change, string $message): void {
    $page = editablePage();
    $ids = editableIds($page);

    $resolved = array_map(fn (mixed $value): mixed => is_string($value) && isset($ids[$value]) ? $ids[$value] : $value, $change);

    WireUpServer::tool(EditBlocksTool::class, ['page' => $page->id, 'changes' => [
        ['action' => 'update', 'block' => $ids['hero'], 'content' => ['heading' => 'Changed']],
        $resolved,
    ]])
        ->assertHasErrors([$message, 'Nothing was saved.']);

    expect($page->refresh()->blocks->firstWhere('id', $ids['hero'])->content['heading'])->toBe(['en' => 'Welcome', 'nl' => 'Welkom']);
})->with([
    'unknown block' => [['action' => 'update', 'block' => 999999], 'Change 2 (update): there is no block 999999. The blocks are:'],
    'no block' => [['action' => 'update'], 'pass "block" with the id of the block to change.'],
    'unknown type' => [['action' => 'add', 'type' => 'carousel'], 'pass "type", one of:'],
    'unknown anchor' => [['action' => 'add', 'type' => 'spacer', 'after' => 999999], 'there is no block 999999'],
    'remove unknown' => [['action' => 'remove', 'block' => 999999], 'Change 2 (remove): there is no block 999999'],
    'move nowhere' => [['action' => 'move', 'block' => 'cards'], 'pass "after" or "before"'],
    'move unknown' => [['action' => 'move', 'block' => 999999, 'after' => 'hero'], 'Change 2 (move): there is no block 999999'],
    'move to unknown' => [['action' => 'move', 'block' => 'cards', 'after' => 999999], 'there is no block 999999'],
    'no item' => [['action' => 'update-item', 'block' => 'cards'], 'pass "item" with the id of the item to change.'],
    'unknown item' => [['action' => 'update-item', 'block' => 'cards', 'item' => 'c-9'], 'has no item "c-9". Its items are: c-1, c-2.'],
    'item on unknown block' => [['action' => 'update-item', 'block' => 999999, 'item' => 'c-1'], 'there is no block 999999'],
    'item on block without items' => [['action' => 'remove-item', 'block' => 'closing', 'item' => 'x'], 'has no item "x". It has no items.'],
    'add item without items' => [['action' => 'add-item', 'block' => 'closing'], 'is a rich-text block, which has no items.'],
    'add item at unknown item' => [['action' => 'add-item', 'block' => 'cards', 'after' => 'c-9'], 'has no item "c-9"'],
    'add item to unknown block' => [['action' => 'add-item', 'block' => 999999], 'Change 2 (add-item): there is no block 999999'],
]);

it('refuses an icon that does not exist and keeps the page as it was', function (): void {
    $page = editablePage();
    $ids = editableIds($page);

    WireUpServer::tool(EditBlocksTool::class, ['page' => $page->id, 'changes' => [
        ['action' => 'update-item', 'block' => $ids['cards'], 'item' => 'c-1', 'content' => ['icon' => 'bug-ant']],
    ]])
        ->assertHasErrors(["Block {$ids['cards']} (feature-cards), item \"c-1\": there is no icon called \"bug-ant\".", 'Available icons: ', 'Nothing was saved.']);

    expect($page->refresh()->blocks->firstWhere('id', $ids['cards'])->content['items'][0]['icon'])->toBe('bolt');
});

it('validates what to edit', function (array $arguments, string $message): void {
    WireUpServer::tool(EditBlocksTool::class, $arguments)->assertHasErrors([$message]);
})->with([
    'neither' => [['changes' => [['action' => 'remove', 'block' => 1]]], 'Pass "page" (a page id) or "record" (a record id).'],
    'both' => [['page' => 1, 'record' => 1, 'changes' => [['action' => 'remove', 'block' => 1]]], 'Pass either "page" or "record", not both.'],
    'unknown page' => [['page' => 999999, 'changes' => [['action' => 'remove', 'block' => 1]]], 'No page with id 999999.'],
    'unknown record' => [['record' => 999999, 'changes' => [['action' => 'remove', 'block' => 1]]], 'No record with id 999999.'],
    'unknown action' => [['page' => 1, 'changes' => [['action' => 'replace']]], 'Unknown action. Use one of: update, add, remove, move'],
    'no changes' => [['page' => 1], 'Pass "changes": a list such as'],
]);
