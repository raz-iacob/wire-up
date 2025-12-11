<?php

declare(strict_types=1);

use App\Actions\CreateMediaAction;
use App\Enums\MediaType;
use App\Models\Media;

it('creates a media record with valid attributes', function (): void {
    $action = new CreateMediaAction();

    $media = $action->handle([
        'type' => MediaType::IMAGE->value,
        'source' => 'media/test.jpg',
        'etag' => 'abc123def456',
        'filename' => 'test.jpg',
        'alt_text' => 'Test Image',
        'mime_type' => 'image/jpeg',
        'size' => 102400,
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->type)->toBe(MediaType::IMAGE)
        ->and($media->source)->toBe('media/test.jpg')
        ->and($media->etag)->toBe('abc123def456')
        ->and($media->filename)->toBe('test.jpg')
        ->and($media->alt_text)->toBe('Test Image')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBe(102400);

    expect(Media::query()->where('etag', 'abc123def456')->exists())->toBeTrue();
});

it('creates media with optional attributes', function (): void {
    $action = new CreateMediaAction();

    $media = $action->handle([
        'type' => MediaType::VIDEO->value,
        'source' => 'media/test.mp4',
        'etag' => 'video123',
        'filename' => 'test.mp4',
        'mime_type' => 'video/mp4',
        'size' => 5242880,
        'thumbnail' => 'media/test_thumb.jpg',
        'duration' => 120,
        'width' => 1920,
        'height' => 1080,
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->type)->toBe(MediaType::VIDEO)
        ->and($media->thumbnail)->toBe('media/test_thumb.jpg')
        ->and($media->duration)->toBe(120)
        ->and($media->width)->toBe(1920)
        ->and($media->height)->toBe(1080);
});

it('creates multiple media records independently', function (): void {
    $action = new CreateMediaAction();

    $media1 = $action->handle([
        'type' => MediaType::IMAGE->value,
        'source' => 'media/image1.jpg',
        'etag' => 'image1hash',
        'filename' => 'image1.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 102400,
    ]);

    $media2 = $action->handle([
        'type' => MediaType::DOCUMENT->value,
        'source' => 'media/document.pdf',
        'etag' => 'document1hash',
        'filename' => 'document.pdf',
        'mime_type' => 'application/pdf',
        'size' => 512000,
    ]);

    expect($media1->id)->not->toBe($media2->id)
        ->and(Media::query()->count())->toBe(2);
});
