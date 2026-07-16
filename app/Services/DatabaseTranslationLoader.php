<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Translation\FileLoader;

final class DatabaseTranslationLoader extends FileLoader
{
    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);

        if ($group === '*' && $namespace === '*') {
            return [...$lines, ...$this->databaseLines($locale)];
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function databaseLines(string $locale): array
    {
        $all = config('site.ui_translations');
        $lines = is_array($all) && is_array($all[$locale] ?? null) ? $all[$locale] : [];

        $clean = [];

        foreach ($lines as $key => $value) {
            if (is_string($value) && $value !== '') {
                $clean[(string) $key] = $value;
            }
        }

        return $clean;
    }
}
