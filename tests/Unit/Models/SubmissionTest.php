<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\Page;
use App\Models\Submission;

it('casts metadata to an array and exposes the type', function (): void {
    $submission = Submission::factory()->create([
        'type' => 'contact',
        'metadata' => ['foo' => ['label' => 'Foo', 'value' => 'bar']],
    ]);

    expect($submission->type)->toBe('contact');
    expect($submission->metadata)->toBe(['foo' => ['label' => 'Foo', 'value' => 'bar']]);
});

it('belongs to a page and a block', function (): void {
    $page = Page::factory()->create();
    $block = Block::factory()->create(['blockable_id' => $page->id, 'blockable_type' => 'page']);

    $submission = Submission::factory()->create(['page_id' => $page->id, 'block_id' => $block->id]);

    expect($submission->page)->toBeInstanceOf(Page::class);
    expect($submission->page?->id)->toBe($page->id);
    expect($submission->block)->toBeInstanceOf(Block::class);
    expect($submission->block?->id)->toBe($block->id);
});

it('allows null page and block references', function (): void {
    $submission = Submission::factory()->create(['page_id' => null, 'block_id' => null]);

    expect($submission->page)->toBeNull();
    expect($submission->block)->toBeNull();
});
