<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

final class DownloadMediaAction
{
    /**
     * @param  Collection<int, Media>  $medias
     */
    public function handle(Collection $medias): StreamedResponse
    {
        if ($medias->count() === 1) {
            return $this->downloadSingle($medias->first());
        }

        return $this->downloadMultiple($medias);
    }

    private function downloadSingle(Media $media): StreamedResponse
    {
        return Storage::disk(config('filesystems.media'))
            ->download(
                $media->source,
                $media->filename,
                ['Content-Type' => $media->mime_type]
            );
    }

    /**
     * @param  Collection<int, Media>  $medias
     */
    private function downloadMultiple(Collection $medias): StreamedResponse
    {
        $zipFileName = 'media-'.now()->format('Y-m-d-H-i-s').'.zip';

        return response()->streamDownload(function () use ($medias, $zipFileName): void {

            $zipFilePath = storage_path('app/tmp/'.$zipFileName);

            $zip = new ZipArchive();
            $zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            foreach ($medias as $media) {
                $stream = Storage::disk(config('filesystems.media'))->readStream($media->source);
                if ($stream) {
                    $zip->addFromString($media->filename, stream_get_contents($stream));
                    fclose($stream);
                }
            }

            $zip->close();

            readfile($zipFilePath);
            unlink($zipFilePath);
        }, $zipFileName, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$zipFileName.'"',
        ]);
    }
}
