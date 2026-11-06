<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Phar;
use PharData;

final class ReleaseArchive
{
    public const string REMOTE = 'git@github.com:wire-up-dev/wire-up.git';

    public static function url(string $tag, string $file = 'build.tar.gz'): string
    {
        return "https://github.com/wire-up-dev/wire-up/releases/download/{$tag}/{$file}";
    }

    /**
     * @param  array<string, string>  $files
     */
    public static function make(array $files = ['build/manifest.json' => '{}', 'build/assets/site.css' => 'body{}']): string
    {
        $directory = storage_path('framework/testing/archive-'.Str::random(8));

        File::ensureDirectoryExists($directory);

        $tar = new PharData($directory.'/build.tar');

        foreach ($files as $name => $contents) {
            $tar->addFromString($name, $contents);
        }

        $tar->compress(Phar::GZ);

        $archive = File::get($directory.'/build.tar.gz');

        File::deleteDirectory($directory);

        return $archive;
    }
}
