<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\Page;
use Illuminate\Support\Arr;

it('resolves the parent through the blockable relation', function (): void {
    $page = Page::factory()->create();
    $block = Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
    ]);

    expect($block->blockable)->toBeInstanceOf(Page::class);
    expect($block->blockable->id)->toBe($page->id);
});

it('creates blocks and assigns positions from array order', function (): void {
    $page = Page::factory()->create();

    $page->updateBlocks([
        ['id' => 'new-a', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Hi']]],
        ['id' => 'new-b', 'type' => 'spacer', 'content' => ['size' => 'large']],
    ]);

    $blocks = $page->blocks()->get();

    expect($blocks)->toHaveCount(2);
    expect($blocks[0]->type)->toBe('hero');
    expect($blocks[0]->position)->toBe(0);
    expect($blocks[0]->content)->toBe(['heading' => ['en' => 'Hi']]);
    expect($blocks[1]->type)->toBe('spacer');
    expect($blocks[1]->position)->toBe(1);
});

it('updates existing blocks, deletes missing ones, and reindexes positions', function (): void {
    $page = Page::factory()->create();

    $page->updateBlocks([
        ['id' => 'new-a', 'type' => 'hero', 'content' => ['heading' => ['en' => 'A']]],
        ['id' => 'new-b', 'type' => 'spacer', 'content' => ['size' => 'small']],
    ]);

    $hero = $page->blocks()->where('type', 'hero')->first();

    $page->updateBlocks([
        ['id' => (string) $hero->id, 'type' => 'hero', 'content' => ['heading' => ['en' => 'Updated']]],
    ]);

    $blocks = $page->blocks()->get();

    expect($blocks)->toHaveCount(1);
    expect($blocks[0]->id)->toBe($hero->id);
    expect($blocks[0]->position)->toBe(0);
    expect($blocks[0]->content['heading']['en'])->toBe('Updated');
});

it('returns an ordered blocks array with string ids', function (): void {
    $page = Page::factory()->create();

    $page->updateBlocks([
        ['id' => 'new-a', 'type' => 'spacer', 'content' => ['size' => 'medium']],
    ]);

    $page->load('blocks');

    $array = $page->getBlocksArray();
    $block = Arr::first($array);

    expect($array)->toHaveCount(1);
    expect((string) array_key_first($array))->toBe($block['id']);
    expect($block['id'])->toBeString();
    expect($block['type'])->toBe('spacer');
    expect($block['content'])->toBe(['size' => 'medium']);
});

it('deletes blocks when the page is deleted', function (): void {
    $page = Page::factory()->create();

    $page->updateBlocks([
        ['id' => 'new-a', 'type' => 'spacer', 'content' => ['size' => 'medium']],
    ]);

    $page->delete();

    expect($page->blocks()->count())->toBe(0);
});
