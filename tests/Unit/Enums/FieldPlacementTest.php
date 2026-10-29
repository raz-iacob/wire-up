<?php

declare(strict_types=1);

use App\Enums\FieldPlacement;
use App\Enums\FieldType;

it('exposes the placements in order with a label each', function (): void {
    expect(FieldPlacement::values())->toBe(['main', 'sidebar', 'hidden'])
        ->and(FieldPlacement::MAIN->label())->toBe('Main')
        ->and(FieldPlacement::SIDEBAR->label())->toBe('Sidebar')
        ->and(FieldPlacement::HIDDEN->label())->toBe('Hidden');
});

it('puts long and media fields in the main column and short ones in the sidebar by default', function (FieldType $type, FieldPlacement $placement): void {
    expect($type->defaultPlacement())->toBe($placement);
})->with([
    [FieldType::RICH_TEXT, FieldPlacement::MAIN],
    [FieldType::TEXTAREA, FieldPlacement::MAIN],
    [FieldType::MEDIA_GALLERY, FieldPlacement::MAIN],
    [FieldType::VIDEO, FieldPlacement::MAIN],
    [FieldType::AUDIO, FieldPlacement::MAIN],
    [FieldType::RELATIONSHIP, FieldPlacement::MAIN],
    [FieldType::PHOTO, FieldPlacement::SIDEBAR],
    [FieldType::TEXT, FieldPlacement::SIDEBAR],
    [FieldType::MONEY, FieldPlacement::SIDEBAR],
    [FieldType::DOCUMENT, FieldPlacement::SIDEBAR],
]);
