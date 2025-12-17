<?php

declare(strict_types=1);

use App\Actions\UpdateMediaAction;
use App\Models\Media;

it('updates media with new attributes', function (): void {
    $media = Media::factory()->create([
        'alt_text' => 'Old Text',
        'filename' => 'old-file.jpg',
    ]);

    $action = new UpdateMediaAction();
    $action->handle($media, [
        'alt_text' => 'New Text',
        'filename' => 'new-file.jpg',
    ]);

    $media->refresh();

    expect($media->alt_text)->toBe('New Text')
        ->and($media->filename)->toBe('new-file.jpg');
});

it('updates single attribute on media', function (): void {
    $media = Media::factory()->create(['alt_text' => 'Original']);

    $action = new UpdateMediaAction();
    $action->handle($media, ['alt_text' => 'Updated']);

    $media->refresh();

    expect($media->alt_text)->toBe('Updated');
});
