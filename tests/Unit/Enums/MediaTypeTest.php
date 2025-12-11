<?php

declare(strict_types=1);

use App\Enums\MediaType;

it('has correct enum cases', function (): void {
    expect(MediaType::cases())->toHaveCount(4)
        ->and(MediaType::AUDIO)->toBeInstanceOf(MediaType::class)
        ->and(MediaType::DOCUMENT)->toBeInstanceOf(MediaType::class)
        ->and(MediaType::IMAGE)->toBeInstanceOf(MediaType::class)
        ->and(MediaType::VIDEO)->toBeInstanceOf(MediaType::class);
});

it('has correct string values', function (): void {
    expect(MediaType::AUDIO->value)->toBe('audio')
        ->and(MediaType::DOCUMENT->value)->toBe('document')
        ->and(MediaType::IMAGE->value)->toBe('image')
        ->and(MediaType::VIDEO->value)->toBe('video');
});

it('returns labels for each case', function (): void {
    expect(MediaType::AUDIO->label())->toBeString()->not->toBeEmpty()
        ->and(MediaType::DOCUMENT->label())->toBeString()->not->toBeEmpty()
        ->and(MediaType::IMAGE->label())->toBeString()->not->toBeEmpty()
        ->and(MediaType::VIDEO->label())->toBeString()->not->toBeEmpty();
});

it('returns plural labels for each case', function (): void {
    expect(MediaType::AUDIO->label(true))->toBeString()->not->toBeEmpty()
        ->and(MediaType::DOCUMENT->label(true))->toBeString()->not->toBeEmpty()
        ->and(MediaType::IMAGE->label(true))->toBeString()->not->toBeEmpty()
        ->and(MediaType::VIDEO->label(true))->toBeString()->not->toBeEmpty();
});

it('maps mime types to correct enum cases', function (): void {
    expect(MediaType::fromMimeType('image/jpeg'))->toBe(MediaType::IMAGE)
        ->and(MediaType::fromMimeType('video/mp4'))->toBe(MediaType::VIDEO)
        ->and(MediaType::fromMimeType('audio/mpeg'))->toBe(MediaType::AUDIO)
        ->and(MediaType::fromMimeType('application/pdf'))->toBe(MediaType::DOCUMENT)
        ->and(MediaType::fromMimeType(null))->toBe(MediaType::DOCUMENT);
});

it('returns correct icon for each case', function (): void {
    expect(MediaType::AUDIO->icon())->toBe('speaker-wave')
        ->and(MediaType::DOCUMENT->icon())->toBe('document')
        ->and(MediaType::IMAGE->icon())->toBe('photo')
        ->and(MediaType::VIDEO->icon())->toBe('video-camera');
});
