<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Locale;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class LocalizationService
{
    private string $currentLocale;

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly Request $request,
        private readonly Translator $translator
    ) {
        $this->currentLocale = $this->config->string('app.locale', 'en');
    }

    public function setLocale(): ?string
    {
        $locale = $this->request->segment(1);

        if (is_string($locale) && $this->isActiveLocale($locale)) {
            $this->currentLocale = $locale;
        } else {
            $this->currentLocale = $this->config->string('app.locale', 'en');
            $locale = null;
        }

        $this->app->setLocale($this->currentLocale);
        $this->translator->setLocale($this->currentLocale);

        $regional = $this->getCurrentLocaleRegional();
        if (! in_array($regional, [null, '', '0'], true)) {
            $regionalUtf = $regional.'.UTF-8';
            setlocale(LC_TIME, $regionalUtf);
            setlocale(LC_MONETARY, $regionalUtf);
        }

        return $locale;
    }

    public function stripDefaultLocale(string $url): string
    {
        return url(preg_replace('#^'.$this->config->string('app.locale', 'en')."(\/|$)#", '', mb_ltrim($url, '/')) ?? $url);
    }

    public function getLocalizedURL(string $url, string $locale): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        $segments = explode('/', mb_ltrim($path, '/'));

        $activeLocalesCodes = array_keys($this->getActiveLocales());
        if (in_array($segments[0], $activeLocalesCodes, true)) {
            array_shift($segments);
        }

        $pathWithoutLocale = implode('/', $segments);

        $newPath = $locale === $this->config->string('app.locale', 'en')
            ? $pathWithoutLocale
            : $locale.'/'.$pathWithoutLocale;

        $newPath = '/'.mb_ltrim($newPath, '/');

        if (! isset($parsed['host'])) {
            return $newPath.
                (isset($parsed['query']) ? '?'.$parsed['query'] : '').
                (isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
        }

        return ($parsed['scheme'] ?? 'https').'://'.
            $parsed['host'].
            ($parsed['port'] ?? '' ? ':'.$parsed['port'] : '').
            $newPath.
            (isset($parsed['query']) ? '?'.$parsed['query'] : '').
            (isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
    }

    /**
     * @return array<string, mixed>
     */
    public function getActiveLocales(): array
    {
        if ($this->app->runningInConsole() && ! Schema::hasTable('locales')) {
            return [];
        }

        return cache()->rememberForever('site-locales', fn (): array => Locale::query()->where('active', true)
            ->get()
            ->mapWithKeys(fn (Locale $locale): array => [$locale->code => $locale->toArray()])
            ->all());
    }

    /**
     * @return Collection<int, string>
     */
    public function getActiveLocaleCodes(): Collection
    {
        return collect(array_keys($this->getActiveLocales()));
    }

    public function isActiveLocale(string $locale): bool
    {
        return array_key_exists($locale, $this->getActiveLocales());
    }

    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    public function getCurrentLocaleRegional(): ?string
    {
        $locales = $this->getActiveLocales();
        $code = $this->currentLocale;

        return $locales[$code]['regional'] ?? null;
    }

    public function getLocaleName(?string $locale = null): ?string
    {
        $locales = $this->getActiveLocales();
        $code = $locale ?? $this->currentLocale;

        return $locales[$code]['name'] ?? null;
    }

    public function getLocaleNative(?string $locale = null): ?string
    {
        $locales = $this->getActiveLocales();
        $code = $locale ?? $this->currentLocale;

        return $locales[$code]['endonym'] ?? null;
    }

    public function getLocaleDirection(?string $locale = null): string
    {
        $locales = $this->getActiveLocales();
        $code = $locale ?? $this->currentLocale;

        return empty($locales[$code]['rtl']) ? 'ltr' : 'rtl';
    }
}
