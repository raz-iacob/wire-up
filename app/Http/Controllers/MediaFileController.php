<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaFileController
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        $disk = $media->disk();

        abort_unless($disk->exists($media->source), 404, 'File not found');

        $name = $media->filename ?? basename($media->source);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex'];

        return $request->boolean('download')
            ? $disk->download($media->source, $name, $headers)
            : $disk->response($media->source, $name, $headers);
    }
}
