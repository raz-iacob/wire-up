<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

#[Description('Prepare a server install after cloning the repository')]
#[Signature('wireup:install')]
final class InstallCommand extends Command
{
    public function handle(UpdateService $updates): int
    {
        $this->info('Install Wire-Up');
        $this->newLine();

        if (config('app.env') !== 'production' && ! $this->confirm('The application environment is not production. Continue anyway?')) {
            return 1;
        }

        if (blank(config('app.key')) && ! $this->runStep('Generate application key', [PHP_BINARY, 'artisan', 'key:generate', '--force'])) {
            return 1;
        }

        $steps = [
            'Run database migrations' => [PHP_BINARY, 'artisan', 'migrate', '--force'],
            'Link public storage' => [PHP_BINARY, 'artisan', 'storage:link', '--force'],
            'Install frontend dependencies' => ['npm', 'ci'],
            'Build frontend assets' => ['npm', 'run', 'build'],
            'Cache configuration' => [PHP_BINARY, 'artisan', 'optimize'],
        ];

        foreach ($steps as $label => $command) {
            if (! $this->runStep($label, $command)) {
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
        $this->info('Wire-Up is installed.');

        return 0;
    }

    /**
     * @param  list<string>  $command
     */
    private function runStep(string $label, array $command): bool
    {
        $result = Process::path(base_path())->timeout(600)->run($command);

        $this->components->task($label, fn (): bool => $result->successful());

        if ($result->successful()) {
            return true;
        }

        $output = mb_trim($result->output()."\n".$result->errorOutput());

        if ($output !== '') {
            $this->components->error(mb_substr($output, -2000));
        }

        return false;
    }
}
