<?php

declare(strict_types=1);

use App\Enums\BlockType;

it('exposes a label, icon, description and default content for every case', function (BlockType $type): void {
    expect($type->label())->toBeString()->not->toBeEmpty();
    expect($type->icon())->toBeString()->not->toBeEmpty();
    expect($type->description())->toBeString()->not->toBeEmpty();
    expect($type->defaultContent())->toBeArray();
})->with(BlockType::cases());

it('derives admin and frontend view paths from the value', function (): void {
    expect(BlockType::HERO->adminView())->toBe('components.admin.blocks.hero');
    expect(BlockType::HERO->frontendView())->toBe('components.site.blocks.hero');
    expect(BlockType::TEXT_IMAGE->adminView())->toBe('components.admin.blocks.text-image');
    expect(BlockType::TEXT_IMAGE->frontendView())->toBe('components.site.blocks.text-image');
    expect(BlockType::LOCATION->adminView())->toBe('components.admin.blocks.location');
    expect(BlockType::LOCATION->frontendView())->toBe('components.site.blocks.location');
    expect(BlockType::ACCORDION->adminView())->toBe('components.admin.blocks.accordion');
    expect(BlockType::ACCORDION->frontendView())->toBe('components.site.blocks.accordion');
});

it('lists all backed values', function (): void {
    expect(BlockType::values())->toBe(['hero', 'text-image', 'location', 'accordion', 'spacer']);
});

it('seeds the accordion default content shape', function (): void {
    $content = BlockType::ACCORDION->defaultContent();

    expect($content)->toMatchArray([
        'icon' => 'chevron',
        'exclusive' => true,
        'hasBackground' => false,
    ]);
    expect($content['items'])->toHaveCount(1);
    expect($content['items'][0]['title'])->toBe([]);
    expect($content['items'][0]['body'])->toBe([]);
    expect($content['items'][0]['id'])->toBeString()->not->toBeEmpty();
});

it('seeds the location default content shape', function (): void {
    $content = BlockType::LOCATION->defaultContent();

    expect($content)->toMatchArray([
        'map' => '',
        'phone' => '',
        'email' => '',
        'reverseLayout' => false,
        'hasBackground' => false,
    ]);
    expect($content['directions']['enabled'])->toBeFalse();
    expect($content['directions']['bg'])->toBeNull();
    expect($content['directions']['textColor'])->toBeNull();
});

it('derives the location title from the heading, falling back to the label', function (): void {
    expect(BlockType::LOCATION->editorTitle(['heading' => ['en' => '<p>Find us</p>']], 'en'))->toBe('Find us');
    expect(BlockType::LOCATION->editorTitle([], 'en'))->toBe('Location');
});

it('derives an editor title from content, stripping html', function (): void {
    expect(BlockType::HERO->editorTitle(['heading' => ['en' => 'My hero']], 'en'))->toBe('My hero');
    expect(BlockType::TEXT_IMAGE->editorTitle(['heading' => ['en' => '<p>Rich <strong>copy</strong> here</p>']], 'en'))->toBe('Rich copy here');
});

it('derives the text + image title from the heading, ignoring the body', function (): void {
    $content = ['heading' => ['en' => 'The heading wins'], 'body' => ['en' => '<p>Body copy</p>']];

    expect(BlockType::TEXT_IMAGE->editorTitle($content, 'en'))->toBe('The heading wins');
    expect(BlockType::TEXT_IMAGE->editorTitle(['body' => ['en' => '<p>Body only</p>']], 'en'))->toBe('Text + Image');
});

it('falls back to the label when content text is empty', function (): void {
    expect(BlockType::HERO->editorTitle([], 'en'))->toBe('Hero');
    expect(BlockType::SPACER->editorTitle(['size' => 'large'], 'en'))->toBe('Spacer');
});

it('truncates the text + image title at fifty characters', function (): void {
    $content = ['heading' => ['en' => '<p>'.str_repeat('a', 60).'</p>']];

    expect(BlockType::TEXT_IMAGE->editorTitle($content, 'en'))->toBe(str_repeat('a', 50).'...');
});
