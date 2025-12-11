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

it('downloads multiple media files as zip', function (): void {
    Storage::fake(config('filesystems.media'));

    $medias = Media::factory(3)->create();

    foreach ($medias as $media) {
        Storage::disk(config('filesystems.media'))->put($media->source, 'test content '.$media->id);
    }

    $action = new DownloadMediaAction();
    $response = $action->handle($medias);

    ob_start();
    $zipContent = $response->sendContent();
    $zipContent = ob_get_clean();

    expect($response->getStatusCode())->toBe(200)
        ->and(mb_strlen($zipContent))->toBeGreaterThan(0)
        ->and($response->headers->get('content-type'))->toBe('application/zip')
        ->and($response->headers->get('content-disposition'))->toContain('.zip');
});
