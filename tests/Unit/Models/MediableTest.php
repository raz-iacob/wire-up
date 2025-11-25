<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Mediable;
use App\Models\Page;

test('to array', function (): void {
    $mediable = Mediable::factory()->create()->fresh();

    expect(array_keys($mediable->toArray()))
        ->toEqual([
            'id',
            'media_id',
            'mediable_type',
            'mediable_id',
            'locale',
            'role',
            'crop',
            'metadata',
            'position',
            'published',
            'created_at',
            'updated_at',
        ]);
});

it('has crop cast to array', function (): void {
    $mediable = Mediable::factory()->create()->fresh();

    expect($mediable->crop)->toBeArray();
});

it('has metadata cast to array', function (): void {
    $mediable = Mediable::factory()->create([
        'metadata' => ['key' => 'value'],
    ])->fresh();

    expect($mediable->metadata)->toBeArray()
        ->and($mediable->metadata['key'])->toEqual('value');
});

it('has position cast to integer', function (): void {
    $mediable = Mediable::factory()->create([
        'position' => '5',
    ])->fresh();

    expect($mediable->position)->toBeInt()
        ->and($mediable->position)->toBe(5);
});

it('has published cast to boolean', function (): void {
    $mediable = Mediable::factory()->create([
        'published' => true,
    ])->fresh();

    expect($mediable->published)->toBeTrue();
});

it('belongs to media', function (): void {
    $media = Media::factory()->create([
        'filename' => 'test-image.jpg',
    ]);
    $mediable = Mediable::factory()->create(['media_id' => $media->id])->fresh();

    expect($mediable->media->filename)->toEqual('test-image.jpg');
});

it('morph to page', function (): void {
    $page = Page::factory()->create([
        'title' => 'Test page',
    ]);
    $mediable = Mediable::factory()->for($page, 'mediable')->create()->fresh();

    expect($mediable->mediable->title)->toEqual('Test page');
});
