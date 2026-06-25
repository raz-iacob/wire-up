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
    expect(BlockType::values())->toBe(['hero', 'text-image', 'location', 'accordion', 'gallery', 'video', 'testimonials', 'sponsors', 'feature-cards', 'contact-form', 'spacer']);
});

it('seeds the contact form default content shape', function (): void {
    $content = BlockType::CONTACT_FORM->defaultContent();

    expect($content)->toMatchArray([
        'formName' => '',
        'layout' => 'stacked',
        'hasBackground' => false,
        'heading' => [],
        'description' => [],
        'submitText' => [],
        'successMessage' => [],
        'recipient' => '',
        'fieldOrder' => ['name', 'email', 'message'],
        'customFields' => [],
    ]);
    expect($content['fields']['name'])->toBe(['required' => true, 'label' => [], 'placeholder' => [], 'column' => 'left']);
    expect($content['fields']['email'])->toBe(['required' => true, 'label' => [], 'placeholder' => [], 'column' => 'left']);
    expect($content['fields']['message'])->toBe(['required' => true, 'label' => [], 'placeholder' => [], 'column' => 'right']);
    expect($content['fields']['phone'])->toBe(['required' => false, 'label' => [], 'placeholder' => [], 'column' => 'left']);
    expect($content['fields']['subject'])->toBe(['required' => false, 'label' => [], 'placeholder' => [], 'column' => 'left']);
});

it('derives the contact form title from the heading, falling back to the label', function (): void {
    expect(BlockType::CONTACT_FORM->editorTitle(['heading' => ['en' => '<p>Get in touch</p>']], 'en'))->toBe('Get in touch');
    expect(BlockType::CONTACT_FORM->editorTitle([], 'en'))->toBe('Contact Form');
});

it('seeds the testimonials default content shape', function (): void {
    $content = BlockType::TESTIMONIALS->defaultContent();

    expect($content)->toMatchArray([
        'layout' => 'grid',
        'columns' => 3,
        'hasBackground' => false,
        'amberStars' => false,
        'cardBg' => null,
        'cardText' => null,
        'heading' => [],
        'intro' => [],
    ]);
    expect($content['items'])->toHaveCount(1);
    expect($content['items'][0]['quote'])->toBe([]);
    expect($content['items'][0]['author'])->toBe([]);
    expect($content['items'][0]['role'])->toBe([]);
    expect($content['items'][0]['avatar'])->toBeNull();
    expect($content['items'][0]['rating'])->toBe(0);
    expect($content['items'][0]['id'])->toBeString()->not->toBeEmpty();
});

it('derives the testimonials title from the heading, falling back to the label', function (): void {
    expect(BlockType::TESTIMONIALS->editorTitle(['heading' => ['en' => '<p>What clients say</p>']], 'en'))->toBe('What clients say');
    expect(BlockType::TESTIMONIALS->editorTitle([], 'en'))->toBe('Testimonials');
});

it('seeds the sponsors default content shape', function (): void {
    $content = BlockType::SPONSORS->defaultContent();

    expect($content)->toMatchArray([
        'layout' => 'grid',
        'columns' => 4,
        'hasBackground' => false,
        'grayscale' => false,
        'showNames' => false,
        'heading' => [],
        'intro' => [],
    ]);
    expect($content['items'])->toHaveCount(1);
    expect($content['items'][0]['logo'])->toBeNull();
    expect($content['items'][0]['name'])->toBe([]);
    expect($content['items'][0]['link'])->toBe('');
    expect($content['items'][0]['tier'])->toBe('');
    expect($content['items'][0]['id'])->toBeString()->not->toBeEmpty();
});

it('derives the sponsors title from the heading, falling back to the label', function (): void {
    expect(BlockType::SPONSORS->editorTitle(['heading' => ['en' => '<p>Our partners</p>']], 'en'))->toBe('Our partners');
    expect(BlockType::SPONSORS->editorTitle([], 'en'))->toBe('Sponsors');
});

it('seeds the feature cards default content shape', function (): void {
    $content = BlockType::FEATURE_CARDS->defaultContent();

    expect($content)->toMatchArray([
        'columns' => 3,
        'imageHeight' => 'medium',
        'imageRounded' => false,
        'hasBackground' => false,
        'cardStyle' => true,
        'cardBg' => null,
        'cardText' => null,
        'heading' => [],
        'intro' => [],
    ]);
    expect($content['items'])->toHaveCount(1);
    expect($content['items'][0]['image'])->toBeNull();
    expect($content['items'][0]['title'])->toBe([]);
    expect($content['items'][0]['body'])->toBe([]);
    expect($content['items'][0]['cta'])->toBe([
        'enabled' => false,
        'text' => [],
        'link' => ['type' => 'url', 'value' => '', 'newTab' => false],
        'bg' => null,
        'textColor' => null,
    ]);
    expect($content['items'][0]['id'])->toBeString()->not->toBeEmpty();
});

it('derives the feature cards title from the heading, falling back to the label', function (): void {
    expect(BlockType::FEATURE_CARDS->editorTitle(['heading' => ['en' => '<p>Why choose us</p>']], 'en'))->toBe('Why choose us');
    expect(BlockType::FEATURE_CARDS->editorTitle([], 'en'))->toBe('Feature Cards');
});

it('seeds the video default content shape', function (): void {
    $content = BlockType::VIDEO->defaultContent();

    expect($content)->toMatchArray([
        'source' => 'upload',
        'video' => null,
        'url' => '',
        'poster' => null,
        'aspect' => '16:9',
        'autoplay' => false,
        'loop' => false,
        'muted' => false,
        'controls' => true,
        'hasBackground' => false,
        'heading' => [],
        'intro' => [],
    ]);
});

it('derives the video title from the heading, falling back to the label', function (): void {
    expect(BlockType::VIDEO->editorTitle(['heading' => ['en' => '<p>Watch this</p>']], 'en'))->toBe('Watch this');
    expect(BlockType::VIDEO->editorTitle([], 'en'))->toBe('Video');
});

it('seeds the gallery default content shape', function (): void {
    $content = BlockType::GALLERY->defaultContent();

    expect($content)->toMatchArray([
        'media' => [],
        'columns' => 3,
        'lightbox' => true,
        'hasBackground' => false,
    ]);
});

it('derives the gallery title from the heading, falling back to the label', function (): void {
    expect(BlockType::GALLERY->editorTitle(['heading' => ['en' => '<p>Our work</p>']], 'en'))->toBe('Our work');
    expect(BlockType::GALLERY->editorTitle([], 'en'))->toBe('Gallery');
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
