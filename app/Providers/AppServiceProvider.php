<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use App\Services\DatabaseTranslationLoader;
use App\Services\LocalizationService;
use App\Services\PageCache;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Translation\FileLoader;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Fortify\Fortify;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
        Cashier::ignoreRoutes();

        $this->app->singleton('localization', LocalizationService::class);
        $this->app->scoped(PageCache::class);

        $this->app->make(Repository::class)->set('app.default_locale', $this->app->make(Repository::class)->string('app.locale', 'en'));

        $this->app->extend('translation.loader', fn (FileLoader $loader): DatabaseTranslationLoader => new DatabaseTranslationLoader(
            $this->app->make(Filesystem::class),
            $loader->paths(),
        ));
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRelations();
        $this->configureUrl();
        $this->configureVite();
        $this->configureDates();
        $this->configurePasswordValidation();
        $this->configureSettings();
        $this->configureGates();
    }

    private function configureGates(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if ($ability === Role::DEVELOPER) {
                return $user->isDeveloper();
            }

            if ($user->role?->bypass) {
                return true;
            }

            if (str_contains($ability, '.')) {
                return $user->hasAbility($ability);
            }

            return null;
        });
    }

    private function configureSettings(): void
    {
        config()->set('site', Settings::cached());
        config()->set('cashier.currency', Str::lower(SettingsService::current()->currency()));
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
}
