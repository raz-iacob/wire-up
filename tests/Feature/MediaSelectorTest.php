<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use Livewire\Livewire;

it('keeps media that was stored with only a source path', function (): void {
    $media = Media::factory()->count(3)->create(['type' => MediaType::IMAGE]);

    $stored = $media
        ->map(fn (Media $item): array => [
            'source' => $item->source,
            'metadata' => ['alt' => "alt {$item->id}"],
        ])
        ->all();

    $component = Livewire::test('admin.media-selector', [
        'name' => 'gallery', 'multiple' => true, 'max' => 10, 'media' => $stored,
    ]);

    /** @var array<int, array<string, mixed>> $kept */
    $kept = $component->get('media');

    expect($kept)->toHaveCount(3)
        ->and(array_column($kept, 'id'))->toBe($media->pluck('id')->all())
        ->and(array_column($kept, 'source'))->toBe($media->pluck('source')->all())
        ->and($kept[0]['metadata'])->toBe(['alt' => "alt {$media[0]->id}"]);
});

it('keeps a single media field that was stored with only a source path', function (): void {
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);

    $component = Livewire::test('admin.media-selector', [
        'name' => 'cover',
        'multiple' => false,
        'media' => ['source' => $media->source, 'metadata' => ['alt' => 'Cover']],
    ]);

    /** @var array<string, mixed> $kept */
    $kept = $component->get('media');

    expect($kept['id'])->toBe($media->id)
        ->and($kept['source'])->toBe($media->source)
        ->and($kept['metadata'])->toBe(['alt' => 'Cover']);
});

it('drops media whose source no longer resolves', function (): void {
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);

    $component = Livewire::test('admin.media-selector', [
        'name' => 'gallery',
        'multiple' => true,
        'max' => 10,
        'media' => [
            ['source' => $media->source],
            ['source' => 'media/deleted-file.jpg'],
            ['metadata' => ['alt' => 'no source at all']],
            'not-an-array',
        ],
    ]);

    expect($component->get('media'))->toHaveCount(1);
});
