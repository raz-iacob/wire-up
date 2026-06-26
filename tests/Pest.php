<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Pest\Browser\Playwright\Playwright;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Console', 'Feature', 'Unit');

pest()->beforeEach(function (): void {
    Playwright::setTimeout(15_000);
})->in('Browser');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function something(): void
{
    //
}

/**
 * @param  array<string, array<string, array<int, array<string, mixed>>>>  $menus  keyed by menu key => [locale => items]
 * @return array<int, array{key: string, name: string, builtin: bool, items: array<string, array<int, array<string, mixed>>>}>
 */
function menusPayload(array $menus): array
{
    $payload = [];

    foreach ($menus as $key => $items) {
        $payload[] = [
            'key' => $key,
            'name' => ucfirst($key),
            'builtin' => in_array($key, ['header', 'footer'], true),
            'items' => $items,
        ];
    }

    return $payload;
}
