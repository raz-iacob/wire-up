<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Locale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

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

    /**
     * @return Collection<int, string>
     */
    private function siteActiveLocales(): Collection
    {
        return Cache::rememberForever('site-locales', fn (): Collection => Locale::active()->pluck('code'));
    }
}
