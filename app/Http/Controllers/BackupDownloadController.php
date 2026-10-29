<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BackupDownloadController
{
    public function __invoke(BackupService $backups, string $file): BinaryFileResponse
    {
        $path = $backups->find($file);

        abort_if($path === null, 404, 'File not found');

        return response()->download($path);
    }
}
