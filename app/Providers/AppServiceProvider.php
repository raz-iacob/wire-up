<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Locale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('locales', fn (): Collection => $this->siteActiveLocales());
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRelations();
        $this->configureUrl();
        $this->configureVite();
        $this->configureDates();
        $this->configurePasswordValidation();
    }

    private function configureModels(): void
    {
        Model::automaticallyEagerLoadRelationships();
        Model::preventLazyLoading(! app()->isProduction());
        Model::shouldBeStrict();
        Model::unguard();
    }

    private function configureRelations(): void
    {
        Relation::enforceMorphMap((array) config('models.map', []));
    }

    private function configureUrl(): void
    {
        URL::forceScheme('https');
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureVite(): void
    {
        Vite::usePrefetchStrategy('aggressive');
    }

    private function configurePasswordValidation(): void
    {
        Password::defaults(fn () => $this->app->isProduction() ? Password::min(8)->uncompromised() : null);
    }

    /**
     * @return Collection<int, string>
     */
    private function siteActiveLocales(): Collection
    {
        return Cache::rememberForever('site-locales', fn (): Collection => Locale::active()->pluck('code'));
    }
}
