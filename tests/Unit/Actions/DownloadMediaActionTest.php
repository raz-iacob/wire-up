<?php

declare(strict_types=1);

use App\Actions\DownloadMediaAction;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

it('downloads a single media file', function (): void {
    Storage::fake(config('filesystems.media'));

    $media = Media::factory()->create(['source' => 'media/test.pdf']);

    Storage::disk(config('filesystems.media'))->put('media/test.pdf', 'test content');

    $action = new DownloadMediaAction();
    $response = $action->handle(collect([$media]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->headers->get('content-disposition'))->toContain($media->filename);
});

it('prepares multiple media files as zip download', function (): void {
    $medias = Media::factory(3)->create();

    $action = new DownloadMediaAction();
    $response = $action->handle($medias);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toBe('application/zip')
        ->and($response->headers->get('content-disposition'))->toContain('.zip');
});
