<?php

declare(strict_types=1);

use App\Console\Commands\InstallCommand;
use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.version_file', storage_path('framework/testing/wireup-'.Str::random(8).'/version'));
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

afterEach(function (): void {
    File::deleteDirectory(dirname((string) config('wireup.version_file')));
});

it('runs every install step in order', function (): void {
    Process::fake([
        '*describe*' => Process::result(output: "v1.0.0\n"),
        '*' => Process::result(),
    ]);
    User::factory()->create();

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'en')
        ->expectsOutputToContain('An admin user already exists, skipping.')
        ->expectsOutputToContain('Wire-Up is installed.')
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'migrate', '--force']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'storage:link', '--force']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'ci']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'run', 'build']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'optimize']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'describe', '--tags', '--abbrev=0']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'ls-remote', '--tags', 'origin']);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'key:generate', '--force']);

    expect(resolve(UpdateService::class)->currentVersion())->toBe('v1.0.0');
});

it('generates the application key when it is missing', function (): void {
    Process::fake();
    config()->set('app.key', '');
    User::factory()->create();

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'en')
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'key:generate', '--force']);
});

it('aborts when key generation fails', function (): void {
    Process::fake(['*key:generate*' => Process::result(errorOutput: 'key boom', exitCode: 1)]);
    config()->set('app.key', '');

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsOutputToContain('key boom')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'migrate', '--force']);
});

it('aborts when a step fails', function (): void {
    Process::fake([
        '*migrate*' => Process::result(output: 'migration boom', exitCode: 1),
        '*' => Process::result(),
    ]);
    User::factory()->create();

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'en')
        ->expectsOutputToContain('migration boom')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === ['npm', 'ci']);
});

it('aborts when the environment is not production and the user declines', function (): void {
    Process::fake();

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?')
        ->assertExitCode(1);

    Process::assertNothingRan();
});

it('creates the first admin user when none exists', function (): void {
    Process::fake();

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'en')
        ->expectsQuestion('Enter name', 'Admin User')
        ->expectsQuestion('Enter email', 'admin@example.com')
        ->expectsQuestion('Please enter your desired password', 'password')
        ->expectsQuestion('Please confirm your password', 'password')
        ->assertExitCode(0);

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue();
});

it('writes the chosen default language to the environment file', function (): void {
    Process::fake();
    User::factory()->create();

    $dir = dirname((string) config('wireup.version_file'));
    File::ensureDirectoryExists($dir);
    File::put($dir.'/.env', "APP_NAME=Test\nAPP_LOCALE=en\n");
    $this->app->useEnvironmentPath($dir);

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'de')
        ->expectsOutputToContain('Default language set to "de".')
        ->assertExitCode(0);

    expect(File::get($dir.'/.env'))->toContain('APP_LOCALE=de')
        ->and(config('app.locale'))->toBe('de')
        ->and(config('app.default_locale'))->toBe('de');
});

it('appends APP_LOCALE when the environment file lacks it', function (): void {
    Process::fake();
    User::factory()->create();

    $dir = dirname((string) config('wireup.version_file'));
    File::ensureDirectoryExists($dir);
    File::put($dir.'/.env', "APP_NAME=Test\n");
    $this->app->useEnvironmentPath($dir);

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'fr')
        ->assertExitCode(0);

    expect(File::get($dir.'/.env'))->toContain("\nAPP_LOCALE=fr\n");
});

it('rejects an invalid language code and keeps the current locale', function (): void {
    Process::fake();
    User::factory()->create();

    $dir = dirname((string) config('wireup.version_file'));
    File::ensureDirectoryExists($dir);
    File::put($dir.'/.env', "APP_LOCALE=en\n");
    $this->app->useEnvironmentPath($dir);

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'Not A Locale!')
        ->expectsOutputToContain('is not a valid language code')
        ->assertExitCode(0);

    expect(File::get($dir.'/.env'))->toContain('APP_LOCALE=en')
        ->and(config('app.locale'))->toBe('en');
});

it('skips the language prompt when running non-interactively', function (): void {
    Process::fake();
    config()->set('app.env', 'production');
    User::factory()->create();

    $this->artisan(InstallCommand::class, ['--no-interaction' => true])
        ->assertExitCode(0);

    expect(config('app.locale'))->toBe('en');
});

it('creates the sqlite database file when it is missing', function (): void {
    Process::fake();
    User::factory()->create();

    $dir = dirname((string) config('wireup.version_file'));
    config()->set('database.connections.sqlite.database', $dir.'/database.sqlite');

    $this->artisan(InstallCommand::class)
        ->expectsConfirmation('The application environment is not production. Continue anyway?', 'yes')
        ->expectsQuestion('Default site language (BCP 47 code, e.g. en, de, fr)', 'en')
        ->expectsOutputToContain('Create SQLite database')
        ->assertExitCode(0);

    expect(File::exists($dir.'/database.sqlite'))->toBeTrue();
});
