<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotFoundPath;
use App\Models\Redirect;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RedirectService
{
    public const string CACHE_KEY = 'redirects';

    public const int MAX_PATH_LENGTH = 500;

    /**
     * @var array<int, string>
     */
    private const array SYSTEM_SEGMENTS = ['admin', 'livewire', 'img', 'og', 'stripe', 'build', 'storage'];

    public static function flush(): void
    {
        DB::afterCommit(fn (): bool => cache()->forget(self::CACHE_KEY));
    }

    public static function normalizePath(string $path): string
    {
        $trimmed = mb_trim(mb_strtolower(rawurldecode($path)), " \t\n\r\0\x0B/");

        return (string) preg_replace('#/{2,}#', '/', '/'.$trimmed);
    }

    public static function normalizeFrom(string $input): string
    {
        $input = mb_trim($input);
        $parts = parse_url($input);

        if ($parts === false) {
            $parts = ['path' => $input];
        }

        $path = self::normalizePath($parts['path'] ?? '');
        $query = isset($parts['query']) && ! self::isWildcard($path) ? Request::normalizeQueryString($parts['query']) : '';

        return $query === '' ? $path : $path.'?'.$query;
    }

    public static function normalizeTo(string $input): string
    {
        $input = mb_trim($input);

        return str_contains($input, '://') || str_starts_with($input, '//') ? $input : self::normalizeFrom($input);
    }

    public static function isExternal(string $target): bool
    {
        return preg_match('#^https?://#i', $target) === 1;
    }

    public static function isWildcard(string $path): bool
    {
        return str_ends_with($path, '/*');
    }

    public static function isPublicPath(string $path): bool
    {
        $first = explode('/', mb_ltrim($path, '/'))[0];

        return ! in_array($first, self::SYSTEM_SEGMENTS, true) && ! str_starts_with($first, 'livewire-');
    }

    public static function localizedPath(string $locale, string ...$segments): string
    {
        $prefix = $locale === resolve('localization')->getDefaultLocale() ? '' : $locale;

        return self::normalizePath(implode('/', array_filter([$prefix, ...$segments], fn (string $segment): bool => $segment !== '')));
    }

    /**
     * @return array{id: int, url: string, status: int}|null
     */
    public function match(Request $request): ?array
    {
        $path = self::normalizePath($request->decodedPath());

        if (! self::isPublicPath($path) || mb_strlen($path) > self::MAX_PATH_LENGTH) {
            return null;
        }

        $rules = $this->rules();

        if ($rules === null) {
            return null;
        }

        $query = $request->getQueryString();

        if ($query !== null && isset($rules['exact'][$path.'?'.$query])) {
            return $this->resolved($rules['exact'][$path.'?'.$query], '', null);
        }

        if (isset($rules['exact'][$path])) {
            return $this->resolved($rules['exact'][$path], '', $query);
        }

        foreach ($rules['wildcards'] as $prefix => $rule) {
            if (str_starts_with($path, $prefix.'/')) {
                return $this->resolved($rule, mb_substr($path, mb_strlen($prefix)), $query);
            }
        }

        return null;
    }

    public function recordHit(int $id): void
    {
        Redirect::query()->whereKey($id)->toBase()->increment('hits', 1, ['last_hit_at' => now()]);
    }

    public function recordNotFound(Request $request): void
    {
        $path = self::normalizePath($request->decodedPath());

        if (! $request->isMethod('GET') || ! self::isPublicPath($path) || mb_strlen($path) > self::MAX_PATH_LENGTH) {
            return;
        }

        $referrer = $request->headers->get('referer');

        defer(function () use ($path, $referrer): void {
            NotFoundPath::query()->upsert(
                [['path' => $path, 'hits' => 1, 'referrer' => $referrer === null ? null : mb_substr($referrer, 0, self::MAX_PATH_LENGTH)]],
                ['path'],
                ['hits' => DB::raw('hits + 1')],
            );
        })->always();
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function release(array $paths): void
    {
        $rules = $this->rules();

        if ($rules === null) {
            return;
        }

        $claimed = array_values(array_filter($paths, fn (string $path): bool => isset($rules['exact'][$path])
            || (self::isWildcard($path) && isset($rules['wildcards'][mb_substr($path, 0, -2)]))));

        if ($claimed !== []) {
            Redirect::query()->whereIn('from', $claimed)->delete();

            self::flush();
        }
    }

    public function releasePrefix(string $prefix): void
    {
        $paths = resolve('localization')->getActiveLocaleCodes()
            ->flatMap(fn (string $locale): array => [self::localizedPath($locale, $prefix), self::localizedPath($locale, $prefix).'/*'])
            ->all();

        $this->release($paths);
    }

    private function encode(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /**
     * @return array{exact: array<string, array{0: int, 1: string, 2: int}>, wildcards: array<string, array{0: int, 1: string, 2: int}>}|null
     */
    private function rules(): ?array
    {
        try {
            return cache()->rememberForever(self::CACHE_KEY, $this->compile(...));
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * @return array{exact: array<string, array{0: int, 1: string, 2: int}>, wildcards: array<string, array{0: int, 1: string, 2: int}>}
     */
    private function compile(): array
    {
        $exact = [];
        $wildcards = [];

        foreach (Redirect::query()->get(['id', 'from', 'to', 'status_code']) as $redirect) {
            $rule = [$redirect->id, $redirect->to, $redirect->status_code];

            if (self::isWildcard($redirect->from)) {
                $wildcards[mb_substr($redirect->from, 0, -2)] = $rule;
            } else {
                $exact[$redirect->from] = $rule;
            }
        }

        uksort($wildcards, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return ['exact' => $exact, 'wildcards' => $wildcards];
    }

    /**
     * @param  array{0: int, 1: string, 2: int}  $rule
     * @return array{id: int, url: string, status: int}
     */
    private function resolved(array $rule, string $rest, ?string $query): array
    {
        [$id, $to, $status] = $rule;

        $external = self::isExternal($to);
        [$base, $targetQuery] = array_pad(explode('?', $to, 2), 2, null);

        if (self::isWildcard($base)) {
            $base = mb_substr($base, 0, -2).($external ? $this->encode($rest) : $rest);
        }

        $url = $external ? $base : url($this->encode($base));
        $query = $targetQuery ?? $query;

        return ['id' => $id, 'url' => $query === null || $query === '' ? $url : $url.'?'.$query, 'status' => $status];
    }
}
