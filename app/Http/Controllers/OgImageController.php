<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Record;
use App\Services\OgImageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class OgImageController
{
    public function show(OgImageService $images, string $type, int $id, string $locale): BinaryFileResponse
    {
        $content = $type === 'page'
            ? Page::query()->find($id)
            : Record::query()->find($id);

        abort_unless($content instanceof Page || $content instanceof Record, 404, 'File not found');
        abort_unless($content->isLiveInLocale($locale), 404, 'File not found');

        $file = $images->file($content, $locale);

        abort_if($file === null, 404, 'File not found');

        return response()->file($file, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800, s-maxage=604800',
        ]);
    }
}
