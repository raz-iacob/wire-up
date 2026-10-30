<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RedirectOrigin;
use App\Models\NotFoundPath;
use App\Models\Redirect;
use App\Services\RedirectService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveRedirectAction
{
    private const int MAX_HOPS = 10;

    public function handle(string $from, string $to, int $statusCode = 301, RedirectOrigin $origin = RedirectOrigin::MANUAL, ?Redirect $redirect = null): Redirect
    {
        $problems = $this->problems($from, $to, $redirect);

        throw_if($problems !== [], ValidationException::withMessages($problems));

        $from = RedirectService::normalizeFrom($from);
        $to = RedirectService::normalizeTo($to);

        return DB::transaction(function () use ($from, $to, $statusCode, $origin, $redirect): Redirect {
            $attributes = ['from' => $from, 'to' => $to, 'status_code' => $statusCode, 'origin' => $origin];

            if ($redirect instanceof Redirect) {
                $redirect->update($attributes);
            } else {
                $redirect = Redirect::query()->updateOrCreate(['from' => $from], $attributes);
            }

            Redirect::query()->whereKeyNot($redirect->id)->where('to', $from)->update(['to' => $to]);
            NotFoundPath::query()->where('path', Str::before($from, '?'))->delete();

            RedirectService::flush();

            return $redirect;
        });
    }

    /**
     * @return array<string, string>
     */
    public function problems(string $from, string $to, ?Redirect $redirect = null): array
    {
        $from = RedirectService::normalizeFrom($from);
        $to = RedirectService::normalizeTo($to);
        $path = Str::before($from, '?');

        return match (true) {
            in_array($path, ['/', '/*'], true) => ['from' => __('The homepage cannot be redirected.')],
            ! RedirectService::isPublicPath($path) => ['from' => __('This address belongs to the admin and cannot be redirected.')],
            mb_strlen($from) > RedirectService::MAX_PATH_LENGTH => ['from' => __('The old address is too long.')],
            ! $this->isValidTarget($to) => ['to' => __('Enter a path starting with / or a full web address starting with https://.')],
            RedirectService::isWildcard(Str::before($to, '?')) && ! RedirectService::isWildcard($path) => ['to' => __('Only an old address ending in /* can send visitors to one.')],
            $to === $from || ($from === $path && Str::before($to, '?') === $path) => ['to' => __('The new address is the same as the old one.')],
            $redirect instanceof Redirect && Redirect::query()->whereKeyNot($redirect->id)->where('from', $from)->exists() => ['from' => __('Another redirect already starts from this address.')],
            $this->loopsBack($from, $to, $redirect) => ['to' => __('This would send visitors around in a loop.')],
            default => [],
        };
    }

    private function isValidTarget(string $to): bool
    {
        if (RedirectService::isExternal($to)) {
            return filter_var(Str::replaceEnd('/*', '', $to), FILTER_VALIDATE_URL) !== false;
        }

        return str_starts_with($to, '/') && ! str_starts_with($to, '//');
    }

    private function loopsBack(string $from, string $to, ?Redirect $ignoring): bool
    {
        $hop = $to;

        for ($step = 0; $step < self::MAX_HOPS && ! RedirectService::isExternal($hop); $step++) {
            $next = $this->targetOf($hop, $ignoring) ?? $this->targetOf(Str::before($hop, '?'), $ignoring);

            if ($next === null) {
                return false;
            }

            if ($next === $from) {
                return true;
            }

            $hop = $next;
        }

        return false;
    }

    private function targetOf(string $from, ?Redirect $ignoring): ?string
    {
        $to = Redirect::query()->whereKeyNot($ignoring->id ?? 0)->where('from', $from)->value('to');

        return is_string($to) ? $to : null;
    }
}
