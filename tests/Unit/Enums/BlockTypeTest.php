<?php

declare(strict_types=1);

use App\Enums\BlockType;

it('exposes a label, icon and default content for every case', function (BlockType $type): void {
    expect($type->label())->toBeString()->not->toBeEmpty();
    expect($type->icon())->toBeString()->not->toBeEmpty();
    expect($type->defaultContent())->toBeArray();
})->with(BlockType::cases());

it('derives admin and frontend view paths from the value', function (): void {
    expect(BlockType::HERO->adminView())->toBe('components.admin.blocks.hero');
    expect(BlockType::HERO->frontendView())->toBe('components.site.blocks.hero');
    expect(BlockType::TEXT_IMAGE->adminView())->toBe('components.admin.blocks.text-image');
    expect(BlockType::TEXT_IMAGE->frontendView())->toBe('components.site.blocks.text-image');
});

it('lists all backed values', function (): void {
    expect(BlockType::values())->toBe(['hero', 'text-image', 'spacer']);
});
