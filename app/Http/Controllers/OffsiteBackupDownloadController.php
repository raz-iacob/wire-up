<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OffsiteBackupService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class OffsiteBackupDownloadController
{
    public function __invoke(OffsiteBackupService $offsite, string $file): StreamedResponse
    {
        abort_unless($offsite->configured(), 404, 'File not found');

        try {
            $stream = $offsite->readStream($file);
        } catch (Throwable) {
            abort(404, 'File not found');
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $file, ['Content-Type' => 'application/zip']);
    }
}
