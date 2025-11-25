<?php

declare(strict_types=1);

use App\Enums\MediaType;

it('has correct enum cases', function (): void {
    expect(MediaType::cases())->toHaveCount(4)
        ->and(MediaType::AUDIO)->toBeInstanceOf(MediaType::class)
        ->and(MediaType::DOCUMENT)->toBeInstanceOf(MediaType::class)
        ->and(MediaType::PHOTO)->toBeInstanceOf(MediaType::class)
        ->and(MediaType::VIDEO)->toBeInstanceOf(MediaType::class);
});

it('has correct string values', function (): void {
    expect(MediaType::AUDIO->value)->toBe('audio')
        ->and(MediaType::DOCUMENT->value)->toBe('document')
        ->and(MediaType::PHOTO->value)->toBe('photo')
        ->and(MediaType::VIDEO->value)->toBe('video');
});

it('returns labels for each case', function (): void {
    expect(MediaType::AUDIO->label())->toBeString()->not->toBeEmpty()
        ->and(MediaType::DOCUMENT->label())->toBeString()->not->toBeEmpty()
        ->and(MediaType::PHOTO->label())->toBeString()->not->toBeEmpty()
        ->and(MediaType::VIDEO->label())->toBeString()->not->toBeEmpty();
});
