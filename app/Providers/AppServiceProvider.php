<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Settings;
use App\Models\User;
use App\Services\DatabaseTranslationLoader;
use App\Services\LocalizationService;
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
use Illuminate\Translation\FileLoader;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->singleton('localization', LocalizationService::class);

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

        $this->configureServiceCredentials();
    }

    private function configureServiceCredentials(): void
    {
        $pexelsKey = config('site.pexels_api_key');

        if (is_string($pexelsKey) && $pexelsKey !== '') {
            config()->set('services.pexels.key', $pexelsKey);
        }

        $analyticsPropertyId = config('site.google_analytics_property_id');

        if (is_string($analyticsPropertyId) && $analyticsPropertyId !== '') {
            config()->set('services.google_analytics.property_id', $analyticsPropertyId);
        }

        $analyticsCredentials = config('site.google_analytics_credentials');

        if (is_string($analyticsCredentials) && $analyticsCredentials !== '') {
            config()->set('services.google_analytics.credentials', $analyticsCredentials);
        }

        $slackWebhookUrl = config('site.slack_webhook_url');

        if (is_string($slackWebhookUrl) && $slackWebhookUrl !== '') {
            config()->set('services.slack.webhook_url', $slackWebhookUrl);
        }

        $aiProvider = config('site.ai_provider');
        $aiApiKey = config('site.ai_api_key');

        if (is_string($aiProvider) && in_array($aiProvider, ['anthropic', 'openai', 'gemini'], true) && is_string($aiApiKey) && $aiApiKey !== '') {
            config()->set('ai.default', $aiProvider);
            config()->set('ai.providers.'.$aiProvider.'.key', $aiApiKey);
        }

        if (SettingsService::current()->smtpSettingsComplete()) {
            $mailFromName = config('site.mail_from_name');

            config()->set('mail.default', 'smtp');
            config()->set('mail.mailers.smtp.host', config()->string('site.mail_host'));
            config()->set('mail.mailers.smtp.port', (int) config('site.mail_port', 587));
            config()->set('mail.mailers.smtp.username', config()->string('site.mail_username'));
            config()->set('mail.mailers.smtp.password', config()->string('site.mail_password'));
            config()->set('mail.mailers.smtp.scheme', config('site.mail_encryption') === 'ssl' ? 'smtps' : null);
            config()->set('mail.from.address', config()->string('site.mail_from_address'));
            config()->set('mail.from.name', is_string($mailFromName) && $mailFromName !== '' ? $mailFromName : config()->string('app.name'));
        }
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
