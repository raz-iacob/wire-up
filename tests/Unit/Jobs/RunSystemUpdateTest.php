<?php

declare(strict_types=1);

use App\Jobs\RunSystemUpdate;
use App\Services\UpdateService;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Process;

it('runs the update command when its tag is pending', function (): void {
    Process::fake();

    $service = resolve(UpdateService::class);
    $service->markPending('v1.0.0');

    new RunSystemUpdate('v1.0.0')->handle($service);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'wireup:update', '--tag=v1.0.0', '--force']);
});

it('skips when no update is pending', function (): void {
    Process::fake();

    new RunSystemUpdate('v1.0.0')->handle(resolve(UpdateService::class));

    Process::assertNothingRan();
});

it('skips when a different tag is pending', function (): void {
    Process::fake();

    $service = resolve(UpdateService::class);
    $service->markPending('v2.0.0');

    new RunSystemUpdate('v1.0.0')->handle($service);

    Process::assertNothingRan();
});

it('marks the update failed when the process fails silently', function (): void {
    Process::fake(['*' => Process::result(errorOutput: 'spawn boom', exitCode: 1)]);

    $service = resolve(UpdateService::class);
    $service->markPending('v1.0.0');

    new RunSystemUpdate('v1.0.0')->handle($service);

    expect($service->state()['status'])->toBe('failed')
        ->and($service->state()['output'])->toContain('spawn boom');
});

it('preserves the failure recorded by the update command', function (): void {
    Process::fake(['*' => function (PendingProcess $process): FakeProcessResult {
        resolve(UpdateService::class)->markFailed('v1.0.0', 'Run database migrations', 'original failure');

        return Process::result(exitCode: 1);
    }]);

    $service = resolve(UpdateService::class);
    $service->markPending('v1.0.0');

    new RunSystemUpdate('v1.0.0')->handle($service);

    expect($service->state()['step'])->toBe('Run database migrations')
        ->and($service->state()['output'])->toBe('original failure');
});

it('records a failure when the job itself fails', function (): void {
    $service = resolve(UpdateService::class);
    $service->markPending('v1.0.0');

    new RunSystemUpdate('v1.0.0')->failed(new RuntimeException('kaboom'));

    expect($service->state()['status'])->toBe('failed')
        ->and($service->state()['output'])->toBe('kaboom');
});

it('keeps an existing failure when the job fails afterwards', function (): void {
    $service = resolve(UpdateService::class);
    $service->markFailed('v1.0.0', 'Build frontend assets', 'original failure');

    new RunSystemUpdate('v1.0.0')->failed(new RuntimeException('kaboom'));

    expect($service->state()['output'])->toBe('original failure');
});

it('has conservative queue settings', function (): void {
    $job = new RunSystemUpdate('v1.0.0');
    $reflection = new ReflectionClass($job);

    $tries = $reflection->getAttributes(Tries::class)[0]->newInstance();
    $timeout = $reflection->getAttributes(Timeout::class)[0]->newInstance();

    expect($tries->tries)->toBe(1)
        ->and($timeout->timeout)->toBe(3600)
        ->and($job->uniqueId())->toBe('wireup-update');
});
