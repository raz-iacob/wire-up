<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class PageCache
{
    public const string VERSION_KEY = 'page-cache:version';

    public const int MAX_PAGE = 50;

    public const int TTL = 3600;

    private bool $skipped = false;

    public function enabled(): bool
    {
        return SettingsService::current()->cachesPages();
    }

    public function skip(): void
    {
        $this->skipped = true;
    }

    public function cacheable(Request $request): bool
    {
        if (! $this->enabled() || ! $request->isMethod('GET') || auth()->check() || ! $this->hasCacheableQuery($request)) {
            return false;
        }

        $session = $request->session();

        return empty($session->get(CartService::SESSION_KEY))
            && empty($session->get('_flash.old'))
            && empty($session->get('_flash.new'))
            && ! $session->has('errors');
    }

    /**
     * @return array{body: string, type: string, vary: string|null}|null
     */
    public function get(Request $request): ?array
    {
        $entry = Cache::get($this->key($request));

        if (! is_array($entry) || ($entry['version'] ?? null) !== $this->version() || ($entry['build'] ?? null) !== $this->build()) {
            return null;
        }

        return ['body' => (string) $entry['body'], 'type' => (string) $entry['type'], 'vary' => is_string($entry['vary'] ?? null) ? $entry['vary'] : null];
    }

    public function put(Request $request, Response $response): void
    {
        $body = $response->getContent();

        if ($this->skipped || $response->getStatusCode() !== 200 || ! is_string($body) || $body === '') {
            return;
        }

        Cache::put($this->key($request), [
            'version' => $this->version(),
            'build' => $this->build(),
            'body' => $body,
            'type' => (string) $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
            'vary' => $response->headers->get('Vary'),
        ], $this->ttl());
    }

    public function flush(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * @template TResult
     *
     * @param  array<int, int|null>  $recordIds
     * @param  Closure(): TResult  $change
     * @return TResult
     */
    public function afterStockChange(array $recordIds, Closure $change): mixed
    {
        $soldOutBefore = $this->anySoldOut($recordIds);

        $result = $change();

        if ($soldOutBefore || $this->anySoldOut($recordIds)) {
            $this->flush();
        }

        return $result;
    }

    private function hasCacheableQuery(Request $request): bool
    {
        $query = $request->query();

        if (array_diff(array_keys($query), ['page']) !== []) {
            return false;
        }

        $page = $query['page'] ?? '1';

        return is_string($page) && ctype_digit($page) && (int) $page >= 1 && (int) $page <= self::MAX_PAGE;
    }

    /**
     * @param  array<int, int|null>  $recordIds
     */
    private function anySoldOut(array $recordIds): bool
    {
        return Record::query()->whereKey(array_filter($recordIds))->where('stock', 0)->exists();
    }

    private function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn (): int => 1);
    }

    private function build(): string
    {
        $manifest = public_path('build/manifest.json');

        return resolve(UpdateService::class)->currentVersion().'|'.(is_file($manifest) ? (string) filemtime($manifest) : '');
    }

    private function key(Request $request): string
    {
        $format = $request->prefers(['text/html', 'text/markdown']) === 'text/markdown' ? 'markdown' : 'html';

        return 'page-cache:'.hash('sha256', implode('|', [$request->getSchemeAndHttpHost(), $request->path(), (string) $request->query('page', '1'), $format]));
    }

    private function ttl(): int
    {
        $next = collect([Page::query(), Record::query()])
            ->map(fn (Builder $query): mixed => $query->where('status', ContentStatus::PUBLISHED)->where('published_at', '>', now())->min('published_at'))
            ->filter()
            ->min();

        return $next === null ? self::TTL : max(1, min(self::TTL, (int) now()->diffInSeconds((string) $next)));
    }
}
