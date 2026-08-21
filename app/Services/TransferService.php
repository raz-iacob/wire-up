<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class TransferService
{
    private const string CACHE_STATE = 'wireup:transfer:state';

    /**
     * @return array{status: string, bundle: ?string, step: ?string, output: ?string, at: ?CarbonImmutable}
     */
    public function state(): array
    {
        $raw = Cache::get(self::CACHE_STATE);

        if (! is_array($raw)) {
            return ['status' => 'idle', 'bundle' => null, 'step' => null, 'output' => null, 'at' => null];
        }

        $status = is_string($raw['status'] ?? null) ? $raw['status'] : 'idle';
        $at = is_string($raw['at'] ?? null) ? CarbonImmutable::parse($raw['at']) : null;

        if ($at instanceof CarbonImmutable && (
            ($status === 'pending' && $at->lt(now()->subMinutes(10)))
            || ($status === 'running' && $at->lt(now()->subMinutes(30)))
        )) {
            $status = 'stalled';
        }

        return [
            'status' => $status,
            'bundle' => is_string($raw['bundle'] ?? null) ? $raw['bundle'] : null,
            'step' => is_string($raw['step'] ?? null) ? $raw['step'] : null,
            'output' => is_string($raw['output'] ?? null) ? $raw['output'] : null,
            'at' => $at,
        ];
    }

    public function importing(): bool
    {
        return in_array($this->state()['status'], ['pending', 'running'], true);
    }

    public function markPending(string $bundle): void
    {
        $this->writeState('pending', $bundle);
    }

    public function markRunning(string $bundle, string $step): void
    {
        $this->writeState('running', $bundle, $step);
    }

    public function markFinished(string $bundle, string $summary): void
    {
        $this->writeState('finished', $bundle, null, $summary);
    }

    public function markFailed(string $bundle, string $output): void
    {
        $this->writeState('failed', $bundle, null, mb_substr($output, -2000));
    }

    public function clearState(): void
    {
        Cache::forget(self::CACHE_STATE);
    }

    private function writeState(string $status, string $bundle, ?string $step = null, ?string $output = null): void
    {
        Cache::forever(self::CACHE_STATE, [
            'status' => $status,
            'bundle' => $bundle,
            'step' => $step,
            'output' => $output,
            'at' => now()->toIso8601String(),
        ]);
    }
}
