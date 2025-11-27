<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Page;

test('to array', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create();

    $page->media()->attach($media->id, [
        'locale' => 'en',
        'role' => 'poster',
        'crop' => ['x' => 1],
    ]);

    $mediable = $page->media()->first()->pivot->fresh();

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
            'created_at',
            'updated_at',
        ]);
});

it('has crop cast to array', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create();

    $page->media()->attach($media->id, [
        'crop' => ['default' => [1, 2, 3]],
        'locale' => 'en',
    ]);

    $mediable = $page->media()->first()->pivot->fresh();

    expect($mediable->crop)->toBeArray();
});

it('has metadata cast to array', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create();

    $page->media()->attach($media->id, [
        'metadata' => ['key' => 'value'],
        'locale' => 'en',
    ]);

    $mediable = $page->media()->first()->pivot->fresh();

    expect($mediable->metadata)->toBeArray()
        ->and($mediable->metadata['key'])->toEqual('value');
});

it('has position cast to integer', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create();

    $page->media()->attach($media->id, [
        'position' => '5',
        'locale' => 'en',
    ]);

    $mediable = $page->media()->first()->pivot->fresh();

    expect($mediable->position)->toBeInt()
        ->and($mediable->position)->toBe(5);
});

it('belongs to media', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create([
        'filename' => 'test-image.jpg',
    ]);

    $page->media()->attach($media->id, [
        'locale' => 'en',
    ]);

    $mediable = $page->media()->first()->pivot->fresh();

    expect($mediable->media->filename)->toEqual('test-image.jpg');
});

it('morph to page', function (): void {
    $page = Page::factory()->create(['title' => 'Test page']);
    $media = Media::factory()->create();

    $page->media()->attach($media->id, [
        'locale' => 'en',
    ]);

    $mediable = $page->media()->first()->pivot->fresh();

    expect($mediable->mediable->title)->toEqual('Test page');
});
