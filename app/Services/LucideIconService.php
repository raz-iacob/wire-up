<?php

declare(strict_types=1);

namespace App\Services;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class LucideIconService
{
    public function svg(string $name): ?string
    {
        $name = mb_strtolower(mb_trim($name));

        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            return null;
        }

        $version = config()->string('menu.lucide_version');
        $cacheKey = "lucide:svg:{$version}:{$name}";

        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get("https://unpkg.com/lucide-static@{$version}/icons/{$name}.svg");
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $clean = mb_trim((string) (new Sanitizer)->sanitize($response->body()));

        if (! str_contains($clean, '<svg')) {
            return null;
        }

        Cache::forever($cacheKey, $clean);

        return $clean;
    }
}
