<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class PageScreenshot
{
    private const array VIEWPORTS = [
        'desktop' => '1280,900',
        'tablet' => '834,1112',
        'mobile' => '390,844',
    ];

    public function capture(string $url, string $viewport = 'desktop', bool $fullPage = false): string
    {
        $directory = storage_path('framework/screenshots');
        File::ensureDirectoryExists($directory);

        $file = $directory.'/'.Str::uuid()->toString().'.png';

        $command = [
            'npx', 'playwright', 'screenshot',
            '--browser=chromium',
            '--viewport-size='.(self::VIEWPORTS[$viewport] ?? self::VIEWPORTS['desktop']),
            '--wait-for-timeout=1500',
        ];

        if ($fullPage) {
            $command[] = '--full-page';
        }

        $result = Process::path(base_path())->timeout(90)->run([...$command, $url, $file]);

        if (! $result->successful()) {
            File::delete($file);

            throw new RuntimeException($this->explain(mb_trim($result->output()."\n".$result->errorOutput())));
        }

        throw_unless(is_file($file), RuntimeException::class, 'The browser ran but produced no image. Check that the URL is reachable from the server.');

        $png = (string) file_get_contents($file);

        File::delete($file);

        return $png;
    }

    private function explain(string $output): string
    {
        if (str_contains($output, 'Executable doesn\'t exist') || str_contains($output, 'playwright install')) {
            return 'The headless browser is not installed on this server. Run "npx playwright install chromium" once, then try again.';
        }

        if (str_contains($output, 'not found') || str_contains($output, 'ENOENT')) {
            return 'Playwright is not available on this server. Run "npm ci" and "npx playwright install chromium", then try again.';
        }

        return 'The screenshot failed: '.Str::limit($output, 300);
    }
}
