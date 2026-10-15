<?php

declare(strict_types=1);

use App\Actions\ImportMediaFromUrlAction;
use App\Actions\ImportPexelsMediaAction;
use App\Mail\IntegrationTestMail;

arch()->preset()->php();
arch()->preset()->laravel()->ignoring(IntegrationTestMail::class);
arch()->preset()->security()->ignoring([ImportPexelsMediaAction::class, ImportMediaFromUrlAction::class]);

it('keeps every migration forward-only, with no down method', function (string $path): void {
    expect(method_exists(require $path, 'down'))->toBeFalse();
})->with(fn (): array => collect(glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [])
    ->mapWithKeys(fn (string $path): array => [basename($path) => $path])
    ->all());
