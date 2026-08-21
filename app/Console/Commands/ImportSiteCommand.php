<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SiteImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Import a site bundle, replacing this site')]
#[Signature('wireup:import {path : Path to the bundle} {--dry-run : Report what the bundle holds without writing} {--owner= : User id to credit imported content to} {--force : Skip the confirmation prompt}')]
final class ImportSiteCommand extends Command
{
    public function handle(SiteImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->components->error("No bundle at {$path}.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $report = $importer->inspect($path);

        $this->summarize($report);

        if ($report['problems'] !== []) {
            foreach ($report['problems'] as $problem) {
                $this->components->error($problem);
            }

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->components->info('Dry run only — nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->components->confirm('This replaces every page, record, media item and setting on this site. Continue?', false)) {
            $this->components->warn('Import cancelled.');

            return self::SUCCESS;
        }

        $importer->import($path, $this->ownerId());

        $this->components->info('Site imported. Run "php artisan storage:link" if media does not load.');

        return self::SUCCESS;
    }

    /**
     * @param  array{manifest: array<string, mixed>, problems: array<int, string>, tables: array<string, int>, media: array{expected: int, present: int, missing: array<int, string>}, imported: bool}  $report
     */
    private function summarize(array $report): void
    {
        $version = $report['manifest']['app_version'] ?? null;

        $this->components->twoColumnDetail('Exported', (string) ($report['manifest']['exported_at'] ?? 'unknown'));
        $this->components->twoColumnDetail('From version', is_string($version) ? $version : 'unknown');

        foreach ($report['tables'] as $table => $count) {
            $this->components->twoColumnDetail($table, (string) $count);
        }

        $this->components->twoColumnDetail('media files', $report['media']['present'].' of '.$report['media']['expected']);
    }

    private function ownerId(): ?int
    {
        $owner = $this->option('owner');

        if (is_string($owner) && $owner !== '') {
            return (int) $owner;
        }

        return User::query()->orderBy('id')->value('id');
    }
}
