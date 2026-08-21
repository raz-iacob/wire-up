<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SiteExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Export this site to a transferable bundle')]
#[Signature('wireup:export {--path= : Where to write the bundle} {--with-secrets : Include API keys and mail credentials}')]
final class ExportSiteCommand extends Command
{
    public function handle(SiteExporter $exporter): int
    {
        $path = $this->pathOption() ?? config()->string('wireup.transfer_path').'/site-'.now()->format('Y-m-d-His').'.zip';

        $manifest = $exporter->export($path, (bool) $this->option('with-secrets'));

        $this->components->twoColumnDetail('Bundle', $path);
        $this->components->twoColumnDetail('Version', $manifest['app_version'] ?? 'unknown');
        $this->components->twoColumnDetail('Secrets', $manifest['with_secrets'] ? 'included' : 'excluded');

        foreach ($manifest['tables'] as $table => $count) {
            $this->components->twoColumnDetail($table, (string) $count);
        }

        $this->components->twoColumnDetail('media files', $manifest['media']['count'].' ('.$this->humanBytes($manifest['media']['bytes']).')');

        if ($manifest['media']['missing'] !== []) {
            $this->components->warn(count($manifest['media']['missing']).' media file(s) are recorded but missing on disk:');

            foreach ($manifest['media']['missing'] as $source) {
                $this->components->bulletList([$source === '' ? '(blank source)' : $source]);
            }
        }

        $this->components->info('Site exported.');

        return self::SUCCESS;
    }

    private function pathOption(): ?string
    {
        $path = $this->option('path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }
}
