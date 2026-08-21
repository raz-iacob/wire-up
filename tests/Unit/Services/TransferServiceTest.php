<?php

declare(strict_types=1);

use App\Services\TransferService;

it('starts idle', function (): void {
    expect(resolve(TransferService::class)->state())->toBe([
        'status' => 'idle',
        'bundle' => null,
        'step' => null,
        'output' => null,
        'at' => null,
    ])->and(resolve(TransferService::class)->importing())->toBeFalse();
});

it('ignores a cached value that is not state', function (): void {
    cache()->forever('wireup:transfer:state', 'nonsense');

    expect(resolve(TransferService::class)->state()['status'])->toBe('idle');
});

it('walks from pending to finished', function (): void {
    $transfers = resolve(TransferService::class);

    $transfers->markPending('site.zip');

    expect($transfers->state())->toMatchArray(['status' => 'pending', 'bundle' => 'site.zip', 'step' => null])
        ->and($transfers->importing())->toBeTrue();

    $transfers->markRunning('site.zip', 'Replacing site content');

    expect($transfers->state())->toMatchArray(['status' => 'running', 'step' => 'Replacing site content'])
        ->and($transfers->importing())->toBeTrue();

    $transfers->markFinished('site.zip', '4 pages, 12 records');

    expect($transfers->state())->toMatchArray(['status' => 'finished', 'output' => '4 pages, 12 records'])
        ->and($transfers->importing())->toBeFalse();
});

it('records a failure', function (): void {
    $transfers = resolve(TransferService::class);

    $transfers->markFailed('site.zip', 'The bundle is missing manifest.json.');

    expect($transfers->state())->toMatchArray(['status' => 'failed', 'output' => 'The bundle is missing manifest.json.'])
        ->and($transfers->importing())->toBeFalse();
});

it('keeps only the tail of a long failure', function (): void {
    $transfers = resolve(TransferService::class);

    $transfers->markFailed('site.zip', str_repeat('a', 2500).'THE-END');

    $output = (string) $transfers->state()['output'];

    expect(mb_strlen($output))->toBe(2000)
        ->and($output)->toEndWith('THE-END');
});

it('treats a stuck pending import as stalled after ten minutes', function (): void {
    $transfers = resolve(TransferService::class);

    $transfers->markPending('site.zip');

    $this->travel(11)->minutes();

    expect($transfers->state()['status'])->toBe('stalled')
        ->and($transfers->importing())->toBeFalse();
});

it('treats a stuck running import as stalled after thirty minutes', function (): void {
    $transfers = resolve(TransferService::class);

    $transfers->markRunning('site.zip', 'Replacing site content');

    $this->travel(20)->minutes();

    expect($transfers->state()['status'])->toBe('running');

    $this->travel(15)->minutes();

    expect($transfers->state()['status'])->toBe('stalled');
});

it('forgets its state on request', function (): void {
    $transfers = resolve(TransferService::class);

    $transfers->markFinished('site.zip', 'done');
    $transfers->clearState();

    expect($transfers->state()['status'])->toBe('idle');
});
