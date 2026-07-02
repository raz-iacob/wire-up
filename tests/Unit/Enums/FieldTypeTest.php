<?php

declare(strict_types=1);

use App\Enums\FieldType;

it('exposes all field type values in order', function (): void {
    expect(FieldType::values())->toBe([
        'text',
        'textarea',
        'rich-text',
        'number',
        'money',
        'date',
        'datetime',
        'boolean',
        'select',
        'photo',
        'video',
        'audio',
        'document',
        'media-gallery',
        'url',
    ]);
});

it('provides a label, icon and description for every case', function (FieldType $type): void {
    expect($type->label())->toBeString()->not->toBe('')
        ->and($type->icon())->toBeString()->not->toBe('')
        ->and($type->description())->toBeString()->not->toBe('');
})->with(FieldType::cases());

it('marks text-like types translatable by default', function (): void {
    expect(FieldType::TEXT->isTranslatableByDefault())->toBeTrue()
        ->and(FieldType::TEXTAREA->isTranslatableByDefault())->toBeTrue()
        ->and(FieldType::RICH_TEXT->isTranslatableByDefault())->toBeTrue()
        ->and(FieldType::SELECT->isTranslatableByDefault())->toBeTrue()
        ->and(FieldType::NUMBER->isTranslatableByDefault())->toBeFalse()
        ->and(FieldType::DATE->isTranslatableByDefault())->toBeFalse()
        ->and(FieldType::BOOLEAN->isTranslatableByDefault())->toBeFalse()
        ->and(FieldType::PHOTO->isTranslatableByDefault())->toBeFalse();
});

it('knows which types support options', function (): void {
    expect(FieldType::SELECT->supportsOptions())->toBeTrue()
        ->and(FieldType::TEXT->supportsOptions())->toBeFalse();
});

it('maps media field types to the kinds they accept', function (): void {
    expect(FieldType::PHOTO->acceptsMedia())->toBe(['image'])
        ->and(FieldType::VIDEO->acceptsMedia())->toBe(['video'])
        ->and(FieldType::AUDIO->acceptsMedia())->toBe(['audio'])
        ->and(FieldType::DOCUMENT->acceptsMedia())->toBe(['document'])
        ->and(FieldType::MEDIA_GALLERY->acceptsMedia())->toBe(['image', 'video'])
        ->and(FieldType::TEXT->acceptsMedia())->toBe([]);
});

it('knows which types are media and galleries', function (): void {
    expect(FieldType::PHOTO->isMedia())->toBeTrue()
        ->and(FieldType::MEDIA_GALLERY->isMedia())->toBeTrue()
        ->and(FieldType::TEXT->isMedia())->toBeFalse()
        ->and(FieldType::MEDIA_GALLERY->isGallery())->toBeTrue()
        ->and(FieldType::PHOTO->isGallery())->toBeFalse();
});

it('marks single-line input types as compact', function (): void {
    expect(FieldType::TEXT->isCompact())->toBeTrue()
        ->and(FieldType::NUMBER->isCompact())->toBeTrue()
        ->and(FieldType::MONEY->isCompact())->toBeTrue()
        ->and(FieldType::DATE->isCompact())->toBeTrue()
        ->and(FieldType::DATETIME->isCompact())->toBeTrue()
        ->and(FieldType::SELECT->isCompact())->toBeTrue()
        ->and(FieldType::TEXTAREA->isCompact())->toBeFalse()
        ->and(FieldType::RICH_TEXT->isCompact())->toBeFalse()
        ->and(FieldType::BOOLEAN->isCompact())->toBeFalse()
        ->and(FieldType::URL->isCompact())->toBeFalse()
        ->and(FieldType::PHOTO->isCompact())->toBeFalse()
        ->and(FieldType::MEDIA_GALLERY->isCompact())->toBeFalse();
});

it('resolves an admin view path per type', function (): void {
    expect(FieldType::TEXT->adminView())->toBe('components.admin.fields.text')
        ->and(FieldType::MEDIA_GALLERY->adminView())->toBe('components.admin.fields.media-gallery');
});
