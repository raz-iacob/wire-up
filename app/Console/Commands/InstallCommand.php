<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReleaseAssets;
use App\Services\UpdateService;
use App\Traits\RunsSteps;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Description('Prepare a server install after cloning the repository')]
#[Signature('wireup:install')]
final class InstallCommand extends Command
{
    use RunsSteps;

    public function handle(UpdateService $updates, ReleaseAssets $assets): int
    {
        $this->info('Install Wire Up');
        $this->newLine();

        if (config('app.env') !== 'production' && ! $this->confirm('The application environment is not production. Continue anyway?')) {
            return 1;
        }

        if (blank(config('app.key')) && ! $this->runStep('Generate application key', [PHP_BINARY, 'artisan', 'key:generate', '--force'])) {
            return 1;
        }

        $this->configureDefaultLocale();
        $this->ensureSqliteDatabaseExists();

        $release = $this->prebuiltRelease($updates, $assets);

        $frontend = $release !== null
            ? ['Download frontend assets' => fn (): string => $assets->download($release), 'Install frontend assets' => $assets->install(...)]
            : ['Install frontend dependencies' => ['npm', 'ci'], 'Build frontend assets' => ['npm', 'run', 'build']];

        $steps = [
            'Run database migrations' => [PHP_BINARY, 'artisan', 'migrate', '--force'],
            'Link public storage' => [PHP_BINARY, 'artisan', 'storage:link', '--force'],
            ...$frontend,
            'Cache configuration' => [PHP_BINARY, 'artisan', 'optimize'],
        ];

        foreach ($steps as $label => $step) {
            if (! $this->runStep($label, $step)) {
                return 1;
            }
        }

        if (User::query()->count() === 0) {
            $this->call(CreateAdminUserCommand::class);
        } else {
            $this->components->info('An admin user already exists, skipping.');
        }

        $updates->refreshCurrentVersionFromGit();
        $updates->check();

        $this->newLine();
        $this->info('Wire Up is installed.');

        return 0;
    }

    private function prebuiltRelease(UpdateService $updates, ReleaseAssets $assets): ?string
    {
        $tag = $updates->checkedOutTag();

        return $tag !== null && $assets->published($tag) ? $tag : null;
    }

    private function ensureSqliteDatabaseExists(): void
    {
        $database = config()->string('database.connections.sqlite.database', ':memory:');

        if (config('database.default') !== 'sqlite' || $database === ':memory:' || File::exists($database)) {
            return;
        }

        File::ensureDirectoryExists(dirname($database));
        File::put($database, '');

        $this->components->task('Create SQLite database', fn (): bool => true);
    }

    private function configureDefaultLocale(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $current = config()->string('app.locale', 'en');
        $locale = mb_strtolower(mb_trim((string) $this->ask('Default site language (BCP 47 code, e.g. en, de, fr)', $current)));

        if ($locale === '' || $locale === $current) {
            return;
        }

        if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $locale) !== 1) {
            $this->components->warn("\"{$locale}\" is not a valid language code, keeping \"{$current}\".");

            return;
        }

        $path = $this->laravel->environmentFilePath();
        $contents = File::exists($path) ? File::get($path) : '';
        $replaced = (string) preg_replace('/^APP_LOCALE=.*$/m', "APP_LOCALE={$locale}", $contents, count: $count);

        File::put($path, $count > 0 ? $replaced : mb_rtrim($contents)."\nAPP_LOCALE={$locale}\n");

        config()->set('app.locale', $locale);
        config()->set('app.default_locale', $locale);

        $this->components->info("Default language set to \"{$locale}\".");
    }

    /**
     * @param  list<string>|Closure(): mixed  $step
     */
    private function runStep(string $label, array|Closure $step): bool
    {
        [$successful, $output] = $this->performStep($step);

        $this->components->task($label, fn (): bool => $successful);

        if ($successful) {
            return true;
        }

        if ($output !== '') {
            $this->components->error(mb_substr($output, -2000));
        }

        return false;
    }
}
