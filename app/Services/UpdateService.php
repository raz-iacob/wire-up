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

    private const string CACHE_STATE = 'wireup:update:state';

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
        $current = $this->currentVersion();

        $notes = $latest !== null && $current !== null && version_compare(mb_ltrim($latest, 'v'), mb_ltrim($current, 'v'), '>')
            ? $this->releaseNotes($current, $latest)
            : [];

        Cache::forever(self::CACHE_LATEST, [
            'version' => $latest,
            'checked_at' => now()->toIso8601String(),
            'notes' => $notes,
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

    /**
     * @return array<int, array{version: string, notes: list<string>}>
     */
    public function changelog(): array
    {
        $notes = data_get(Cache::get(self::CACHE_LATEST), 'notes');

        if (! is_array($notes)) {
            return [];
        }

        $sections = [];

        foreach ($notes as $section) {
            $version = is_array($section) ? ($section['version'] ?? null) : null;
            $lines = is_array($section) ? ($section['notes'] ?? null) : null;
            if (! is_string($version)) {
                continue;
            }
            if (! is_array($lines)) {
                continue;
            }

            $sections[] = ['version' => $version, 'notes' => array_values(array_filter($lines, is_string(...)))];
        }

        return $sections;
    }

    /**
     * @return array{status: string, tag: ?string, step: ?string, output: ?string, at: ?CarbonImmutable}
     */
    public function state(): array
    {
        $raw = Cache::get(self::CACHE_STATE);

        if (! is_array($raw)) {
            return ['status' => 'idle', 'tag' => null, 'step' => null, 'output' => null, 'at' => null];
        }

        $status = is_string($raw['status'] ?? null) ? $raw['status'] : 'idle';
        $at = is_string($raw['at'] ?? null) ? CarbonImmutable::parse($raw['at']) : null;

        if ($at instanceof CarbonImmutable && (
            ($status === 'pending' && $at->lt(now()->subMinutes(10)))
            || ($status === 'running' && $at->lt(now()->subMinutes(30)))
        )) {
            $status = 'stalled';
        }

        return [
            'status' => $status,
            'tag' => is_string($raw['tag'] ?? null) ? $raw['tag'] : null,
            'step' => is_string($raw['step'] ?? null) ? $raw['step'] : null,
            'output' => is_string($raw['output'] ?? null) ? $raw['output'] : null,
            'at' => $at,
        ];
    }

    public function updating(): bool
    {
        return in_array($this->state()['status'], ['pending', 'running'], true);
    }

    public function markPending(string $tag): void
    {
        $this->writeState('pending', $tag);
    }

    public function markRunning(string $tag, string $step): void
    {
        $this->writeState('running', $tag, $step);
    }

    public function markFinished(string $tag): void
    {
        $this->writeState('finished', $tag);
    }

    public function markFailed(string $tag, string $step, string $output): void
    {
        $this->writeState('failed', $tag, $step, mb_substr($output, -2000));
    }

    public function clearState(): void
    {
        Cache::forget(self::CACHE_STATE);
    }

    /**
     * @return array<int, array{version: string, notes: list<string>}>
     */
    private function releaseNotes(string $current, string $latest): array
    {
        $fetch = Process::path(base_path())->timeout(120)->run(['git', 'fetch', '--tags', '--force', 'origin']);

        if ($fetch->failed()) {
            return [];
        }

        $show = Process::path(base_path())->run(['git', 'show', "{$latest}:CHANGELOG.md"]);

        if ($show->failed()) {
            return [];
        }

        return $this->parseChangelog($show->output(), $current);
    }

    /**
     * @return array<int, array{version: string, notes: list<string>}>
     */
    private function parseChangelog(string $markdown, string $current): array
    {
        preg_match_all('/^##\s+(v\d+\.\d+\.\d+)[^\n]*$\n?(.*?)(?=^##\s|\z)/ms', $markdown, $matches, PREG_SET_ORDER);

        $sections = [];

        foreach ($matches as $match) {
            if (version_compare(mb_ltrim($match[1], 'v'), mb_ltrim($current, 'v'), '<=')) {
                continue;
            }

            $lines = [];

            foreach (preg_split('/\R/', $match[2]) ?: [] as $line) {
                $line = mb_ltrim(mb_trim($line), '- ');

                if ($line !== '') {
                    $lines[] = $line;
                }
            }

            $sections[] = ['version' => $match[1], 'notes' => $lines];
        }

        return $sections;
    }

    private function writeState(string $status, string $tag, ?string $step = null, ?string $output = null): void
    {
        Cache::forever(self::CACHE_STATE, [
            'status' => $status,
            'tag' => $tag,
            'step' => $step,
            'output' => $output,
            'at' => now()->toIso8601String(),
        ]);
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
