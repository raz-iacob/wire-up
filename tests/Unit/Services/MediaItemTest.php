<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Services\MediaItem;

it('builds a canonical item from a media model', function (): void {
    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'alt_text' => 'A cat']);

    $item = MediaItem::fromMedia($media);

    expect($item['id'])->toBe($media->id)
        ->and($item['source'])->toBe($media->source)
        ->and($item['alt_text'])->toBe('A cat')
        ->and($item['icon'])->toBe(MediaType::IMAGE->icon())
        ->and($item)->toHaveKeys([
            'id', 'source', 'preview', 'crop_src', 'filename', 'alt_text', 'mime_type',
            'thumbnail', 'icon', 'size', 'duration', 'width', 'height', 'dimensions', 'created_at',
        ]);
});

it('resolves a media item from its source path', function (): void {
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);

    expect(MediaItem::fromSource($media->source))->toBe(MediaItem::fromMedia($media));
});

it('returns null for a source that matches no media', function (): void {
    expect(MediaItem::fromSource('media/does-not-exist.jpg'))->toBeNull()
        ->and(MediaItem::fromSource(''))->toBeNull();
});
