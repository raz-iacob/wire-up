<?php

declare(strict_types=1);

use App\Actions\DownloadMediaAction;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

it('downloads a single media file via handle', function (): void {
    Storage::fake(config('filesystems.media'));

    $media = Media::factory()->create(['source' => 'media/test.pdf']);

    Storage::disk(config('filesystems.media'))->put('media/test.pdf', 'test content');

    $action = new DownloadMediaAction();
    $response = $action->handle(collect([$media]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->headers->get('content-disposition'))->toContain($media->filename);
});

it('creates and downloads multiple media files as zip archive', function (): void {
    Storage::fake(config('filesystems.media'));

    $medias = Media::factory(3)->create();

    foreach ($medias as $media) {
        Storage::disk(config('filesystems.media'))->put($media->source, 'test content '.$media->id);
    }

    $action = new DownloadMediaAction();
    $response = $action->handle($medias);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toBe('application/zip')
        ->and($response->headers->get('content-disposition'))->toContain('.zip');

    ob_start();
    try {
        $response->sendContent();
        $zipContent = ob_get_clean();

        expect(mb_strlen($zipContent))->toBeGreaterThan(0);
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
});

it('creates tmp directory if it does not exist', function (): void {
    Storage::fake(config('filesystems.media'));

    $tmpDir = storage_path('app/tmp');
    if (is_dir($tmpDir)) {
        array_map(unlink(...), glob("$tmpDir/*.*"));
        rmdir($tmpDir);
    }

    expect(is_dir($tmpDir))->toBeFalse();

    $medias = Media::factory(2)->create();
    foreach ($medias as $media) {
        Storage::disk(config('filesystems.media'))->put($media->source, 'test content');
    }

    $action = new DownloadMediaAction();
    $response = $action->handle($medias);

    ob_start();
    try {
        $response->sendContent();
        ob_get_clean();

        expect(is_dir($tmpDir))->toBeTrue();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
});
