<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PharData;
use RuntimeException;

final class ReleaseAssets
{
    public const string ARCHIVE = 'build.tar.gz';

    public function url(string $tag, string $file = self::ARCHIVE): ?string
    {
        $repository = $this->repository();

        return $repository === null ? null : "https://github.com/{$repository}/releases/download/{$tag}/{$file}";
    }

    public function published(string $tag): bool
    {
        return $this->checksum($tag) !== null;
    }

    public function stillBuilding(string $tag, string $current): bool
    {
        return ! $this->published($tag) && $this->published($current);
    }

    public function download(string $tag): string
    {
        $checksum = $this->checksum($tag) ?? throw new RuntimeException("No prebuilt frontend assets were published for {$tag}.");

        $response = Http::timeout(300)->get((string) $this->url($tag));

        throw_if(
            $response->failed() || hash('sha256', $response->body()) !== $checksum,
            RuntimeException::class,
            "The frontend assets for {$tag} could not be downloaded or did not match their checksum.",
        );

        File::ensureDirectoryExists($this->directory());
        File::put($this->archive(), $response->body());

        return $this->archive();
    }

    public function install(): void
    {
        $build = (string) config('wireup.build_path');
        $staging = $build.'-next';
        $previous = $build.'-previous';

        File::deleteDirectory($staging);
        File::deleteDirectory($previous);

        try {
            new PharData($this->archive())->extractTo($staging);

            throw_unless(File::exists($staging.'/build/manifest.json'), RuntimeException::class, 'The downloaded frontend assets have no manifest.');

            if (File::isDirectory($build)) {
                File::moveDirectory($build, $previous);
            }

            throw_unless(File::moveDirectory($staging.'/build', $build), RuntimeException::class, 'The new frontend assets could not be moved into place.');
        } finally {
            File::deleteDirectory($staging);
        }

        File::deleteDirectory($previous);
        File::deleteDirectory($this->directory());
    }

    public function snapshotBuild(): void
    {
        $build = (string) config('wireup.build_path');

        File::deleteDirectory($this->snapshot());

        if (File::isDirectory($build)) {
            throw_unless(File::copyDirectory($build, $this->snapshot()), RuntimeException::class, 'The current frontend assets could not be copied.');
        }
    }

    public function restoreSnapshot(): void
    {
        if (! File::isDirectory($this->snapshot())) {
            return;
        }

        $build = (string) config('wireup.build_path');

        File::deleteDirectory($build);

        throw_unless(File::moveDirectory($this->snapshot(), $build), RuntimeException::class, 'The previous frontend assets could not be moved back.');
    }

    public function discardSnapshot(): void
    {
        File::deleteDirectory($this->snapshot());
    }

    private function snapshot(): string
    {
        return config('wireup.build_path').'-rollback';
    }

    private function checksum(string $tag): ?string
    {
        $url = $this->url($tag, self::ARCHIVE.'.sha256');

        if ($url === null) {
            return null;
        }

        $body = rescue(fn (): string => Http::timeout(15)->get($url)->throw()->body(), '', report: false);

        return preg_match('/^[a-f0-9]{64}\b/', mb_trim($body), $match) === 1 ? $match[0] : null;
    }

    private function repository(): ?string
    {
        return once(function (): ?string {
            $remote = mb_trim(Process::path(base_path())->run(['git', 'remote', 'get-url', 'origin'])->output());

            return preg_match('#github\.com[:/]([\w.-]+)/([\w.-]+?)(?:\.git)?/?$#', $remote, $match) === 1
                ? "{$match[1]}/{$match[2]}"
                : null;
        });
    }

    private function archive(): string
    {
        return $this->directory().'/'.self::ARCHIVE;
    }

    private function directory(): string
    {
        return (string) config('wireup.updates_path');
    }
}
