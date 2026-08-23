<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class UiStrings
{
    /**
     * @return array<int, array{group: string, strings: array<int, string>}>
     */
    public static function catalog(): array
    {
        return cache()->rememberForever('ui-strings-catalog', self::scan(...));
    }

    /**
     * @return array<int, string>
     */
    public static function strings(): array
    {
        return array_merge([], ...array_map(fn (array $group): array => $group['strings'], self::catalog()));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function areas(): array
    {
        return [
            'Account' => [resource_path('views/pages/⚡account.blade.php')],
            'Sign in' => [resource_path('views/pages/auth')],
            'Site' => [resource_path('views/components/site')],
        ];
    }

    /**
     * @return array<int, array{group: string, strings: array<int, string>}>
     */
    private static function scan(): array
    {
        $seen = [];
        $catalog = [];

        foreach (self::areas() as $group => $paths) {
            $strings = [];

            foreach ($paths as $path) {
                foreach (self::files($path) as $file) {
                    foreach (self::extract((string) file_get_contents($file)) as $string) {
                        if ($string !== '' && ! isset($seen[$string])) {
                            $seen[$string] = true;
                            $strings[] = $string;
                        }
                    }
                }
            }

            if ($strings !== []) {
                sort($strings);
                $catalog[] = ['group' => $group, 'strings' => $strings];
            }
        }

        $catalog[] = ['group' => 'Form messages', 'strings' => array_values(array_diff(self::frameworkLines(), array_keys($seen)))];

        return $catalog;
    }

    /**
     * @return array<int, string>
     */
    private static function frameworkLines(): array
    {
        $rules = [
            'after', 'array', 'between', 'boolean', 'confirmed', 'date', 'dimensions', 'distinct',
            'email', 'exists', 'file', 'image', 'in', 'integer', 'ip', 'json', 'lowercase', 'max',
            'mimes', 'min', 'not_in', 'numeric', 'present', 'regex', 'required', 'required_if',
            'required_with', 'size', 'string', 'unique', 'url',
        ];

        $lines = trans('validation');

        $keys = collect(is_array($lines) ? Arr::dot($lines) : [])
            ->keys()
            ->filter(fn (string $key): bool => in_array(Str::before($key, '.'), $rules, true))
            ->map(fn (string $key): string => 'validation.'.$key)
            ->push('auth.throttle')
            ->values()
            ->all();

        sort($keys);

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    private static function files(string $path): array
    {
        return is_dir($path) ? (glob($path.'/*.blade.php') ?: []) : [$path];
    }

    /**
     * @return array<int, string>
     */
    private static function extract(string $contents): array
    {
        $strings = [];

        foreach (["/__\\(\\s*'((?:\\\\.|[^'\\\\])*)'/", '/__\\(\\s*"((?:\\\\.|[^"\\\\])*)"/'] as $pattern) {
            preg_match_all($pattern, $contents, $matches);

            foreach ($matches[1] as $raw) {
                $strings[] = stripcslashes($raw);
            }
        }

        return $strings;
    }
}
