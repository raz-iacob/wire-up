<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

final class UpdateService
{
    private const string CACHE_LATEST = 'wireup:update:latest';

    public function currentVersion(): ?string
    {
        $path = $this->versionFile();

        if (! is_file($path)) {
            return null;
        }

        $version = mb_trim(File::get($path));

        return $version === '' ? null : $version;
    }

    public function writeCurrentVersion(string $version): void
    {
        $path = $this->versionFile();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $version);
    }

    public function refreshCurrentVersionFromGit(): ?string
    {
        $result = Process::path(base_path())->run(['git', 'describe', '--tags', '--abbrev=0']);

        if ($result->failed()) {
            return null;
        }

        $version = mb_trim($result->output());

        if ($version === '') {
            return null;
        }

        $this->writeCurrentVersion($version);

        return $version;
    }

    public function check(): ?string
    {
        $result = Process::path(base_path())->run(['git', 'ls-remote', '--tags', 'origin']);

        $latest = $result->failed() ? null : $this->highestTag($result->output());

        Cache::forever(self::CACHE_LATEST, [
            'version' => $latest,
            'checked_at' => now()->toIso8601String(),
        ]);

        return $latest;
    }

    public function latestVersion(): ?string
    {
        $version = data_get(Cache::get(self::CACHE_LATEST), 'version');

        return is_string($version) ? $version : null;
    }

    public function lastCheckedAt(): ?CarbonImmutable
    {
        $checkedAt = data_get(Cache::get(self::CACHE_LATEST), 'checked_at');

        return is_string($checkedAt) ? CarbonImmutable::parse($checkedAt) : null;
    }

    public function updateAvailable(): bool
    {
        $current = $this->currentVersion();
        $latest = $this->latestVersion();

        return $current !== null
            && $latest !== null
            && version_compare(mb_ltrim($latest, 'v'), mb_ltrim($current, 'v'), '>');
    }

    private function highestTag(string $output): ?string
    {
        preg_match_all('#refs/tags/(v\d+\.\d+\.\d+)$#m', $output, $matches);

        $tags = $matches[1];

        if ($tags === []) {
            return null;
        }

        usort($tags, fn (string $a, string $b): int => version_compare(mb_ltrim($a, 'v'), mb_ltrim($b, 'v')));

        return end($tags);
    }

    private function versionFile(): string
    {
        return (string) config('wireup.version_file');
    }
}
