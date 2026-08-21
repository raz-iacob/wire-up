<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SiteImporter;
use App\Services\TransferService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\File;
use Throwable;

#[Timeout(3600)]
#[Tries(1)]
final class RunSiteImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $path,
        public readonly ?int $ownerId,
        public readonly bool $deleteBundle = false,
    ) {}

    public function uniqueId(): string
    {
        return 'wireup-import';
    }

    public function handle(SiteImporter $importer, TransferService $transfers): void
    {
        $bundle = basename($this->path);

        if ($transfers->state()['status'] !== 'pending') {
            return;
        }

        $transfers->markRunning($bundle, __('Reading the bundle'));

        $report = $importer->inspect($this->path);

        if ($report['problems'] !== []) {
            $transfers->markFailed($bundle, implode("\n", $report['problems']));

            $this->cleanUp();

            return;
        }

        $transfers->markRunning($bundle, __('Replacing site content'));

        $report = $importer->import($this->path, $this->ownerId);

        $transfers->markFinished($bundle, $this->summary($report));

        $this->cleanUp();
    }

    public function failed(?Throwable $exception): void
    {
        resolve(TransferService::class)->markFailed(
            basename($this->path),
            (string) $exception?->getMessage(),
        );

        $this->cleanUp();
    }

    /**
     * @param  array{manifest: array<string, mixed>, problems: array<int, string>, tables: array<string, int>, media: array{expected: int, present: int, missing: array<int, string>}, imported: bool}  $report
     */
    private function summary(array $report): string
    {
        $parts = [];

        foreach ($report['tables'] as $table => $count) {
            if ($count > 0) {
                $parts[] = "{$count} {$table}";
            }
        }

        $parts[] = $report['media']['present'].' media files';

        return implode(', ', $parts);
    }

    private function cleanUp(): void
    {
        if ($this->deleteBundle && File::exists($this->path)) {
            File::delete($this->path);
        }
    }
}
