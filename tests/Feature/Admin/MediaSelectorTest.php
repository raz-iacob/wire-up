<?php

declare(strict_types=1);

use Livewire\Livewire;

function mediaPayload(int $id, ?string $filename = null): array
{
    return [
        'id' => $id,
        'preview' => 'https://example.com/media/'.$id.'.jpg',
        'filename' => $filename ?? 'media-'.$id.'.jpg',
        'alt_text' => 'Media '.$id,
        'mime_type' => 'image/jpeg',
        'thumbnail' => null,
        'icon' => 'photo',
        'size' => 1024,
        'duration' => null,
        'dimensions' => '1200 x 630',
        'created_at' => now()->toDateTimeString(),
    ];
}

it('renders the attach media button when nothing is selected', function (): void {
    Livewire::test('admin.media-selector')
        ->assertSee('Attach Media')
        ->assertSee('No media selected');
});

it('stores a single selected media item in single mode', function (): void {
    $component = Livewire::test('admin.media-selector', [
        'name' => 'og_image',
        'locale' => 'en',
    ]);

    $target = $component->instance()->targetKey();

    $component
        ->dispatch('media-selected', target: $target, media: [mediaPayload(1, 'hero.jpg'), mediaPayload(2, 'secondary.jpg')])
        ->assertSet('media.id', 1)
        ->assertSee('hero.jpg');
});

it('stores multiple selected media items when multiple mode is enabled', function (): void {
    $component = Livewire::test('admin.media-selector', [
        'name' => 'gallery',
        'locale' => 'en',
        'multiple' => true,
        'max' => 5,
    ]);

    $target = $component->instance()->targetKey();

    $component
        ->dispatch('media-selected', target: $target, media: [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg')])
        ->assertCount('media', 2)
        ->assertSet('media.0.filename', 'first.jpg')
        ->assertSet('media.1.filename', 'second.jpg');
});

it('replaces a selected media item when changing one slot in multiple mode', function (): void {
    $component = Livewire::test('admin.media-selector', [
        'name' => 'gallery',
        'locale' => 'en',
        'multiple' => true,
    ])
        ->set('media', [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg')])
        ->call('openLibrary', 1);

    $target = $component->instance()->targetKey();

    $component
        ->dispatch('media-selected', target: $target, media: [mediaPayload(3, 'replacement.jpg')])
        ->assertSet('media.0.id', 1)
        ->assertSet('media.1.id', 3)
        ->assertSet('media.1.filename', 'replacement.jpg');
});

it('removes a selected media item', function (): void {
    Livewire::test('admin.media-selector', [
        'multiple' => true,
    ])
        ->set('media', [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg')])
        ->call('removeMedia', 0)
        ->assertCount('media', 1)
        ->assertSet('media.0.id', 2);
});

it('opens a confirmation modal before removing an item', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg')])
        ->call('confirmRemove', 0)
        ->assertSet('showRemoveModal', true)
        ->assertSet('removeIndex', 0)
        ->assertCount('media', 2);
});

it('removes the item only after confirmation', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg')])
        ->call('confirmRemove', 0)
        ->call('removeConfirmed')
        ->assertSet('showRemoveModal', false)
        ->assertSet('removeIndex', null)
        ->assertCount('media', 1)
        ->assertSet('media.0.id', 2);
});

it('reorders selected media items', function (): void {
    Livewire::test('admin.media-selector', [
        'multiple' => true,
    ])
        ->set('media', [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg'), mediaPayload(3, 'third.jpg')])
        ->call('reorderMedia', 3, 0)
        ->assertSet('media.0.id', 3)
        ->assertSet('media.1.id', 1)
        ->assertSet('media.2.id', 2);
});

it('dispatches the library event when opening the selector', function (): void {
    Livewire::test('admin.media-selector', [
        'multiple' => true,
        'max' => 5,
    ])
        ->set('media', [mediaPayload(1, 'first.jpg')])
        ->call('openLibrary')
        ->assertDispatched('select-media');
});

it('defaults to a single default crop variant', function (): void {
    Livewire::test('admin.media-selector')
        ->assertSet('crops.default.w', 1200)
        ->assertSet('crops.default.h', 800);
});

it('stores a crop variant for a selected item in multiple mode', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1), mediaPayload(2)])
        ->call('setCrop', 1, 'default', ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 10, 'crop_y' => 20, 'w' => 1200, 'h' => 630])
        ->assertSet('media.1.crop.default.crop_w', 1200)
        ->assertSet('media.1.crop.default.crop_x', 10)
        ->assertSet('media.0.crop', []);
});

it('stores a crop variant for a selected item in single mode', function (): void {
    Livewire::test('admin.media-selector')
        ->set('media', mediaPayload(1))
        ->call('setCrop', 0, 'default', ['crop_w' => 100, 'crop_h' => 100, 'crop_x' => 0, 'crop_y' => 0, 'w' => 100, 'h' => 100])
        ->assertSet('media.crop.default.crop_w', 100);
});

it('ignores a crop for an unknown item index', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1)])
        ->call('setCrop', 5, 'default', ['crop_w' => 10])
        ->assertCount('media', 1)
        ->assertSet('media.0.crop', []);
});

it('ignores a crop for an unknown variant', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1)])
        ->call('setCrop', 0, 'unknown', ['crop_w' => 10])
        ->assertSet('media.0.crop', []);
});

it('keeps crop data when reordering items', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1), mediaPayload(2)])
        ->call('setCrop', 0, 'default', ['crop_w' => 50, 'crop_h' => 50, 'crop_x' => 0, 'crop_y' => 0, 'w' => 50, 'h' => 50])
        ->call('reorderMedia', 1, 1)
        ->assertSet('media.1.id', 1)
        ->assertSet('media.1.crop.default.crop_w', 50);
});

it('commits multiple crop variants in one pass and skips unknown variants', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1)])
        ->call('setCrops', 0, [
            'default' => ['crop_w' => 100, 'crop_h' => 50, 'crop_x' => 0, 'crop_y' => 0, 'w' => 100, 'h' => 50],
            'unknown' => ['crop_w' => 1],
        ])
        ->assertSet('media.0.crop.default.crop_w', 100)
        ->assertSet('media.0.crop.default.crop_h', 50)
        ->assertCount('media.0.crop', 1);
});

it('preserves existing crops when adding more media via the library', function (): void {
    $component = Livewire::test('admin.media-selector', ['multiple' => true, 'max' => 5]);
    $target = $component->instance()->targetKey();

    $component
        ->set('media', [mediaPayload(1), mediaPayload(2)])
        ->call('setCrop', 0, 'default', ['crop_w' => 100, 'crop_h' => 50, 'crop_x' => 0, 'crop_y' => 0, 'w' => 100, 'h' => 50]);

    $component
        ->dispatch('media-selected', target: $target, media: [mediaPayload(1), mediaPayload(2), mediaPayload(3)])
        ->assertCount('media', 3)
        ->assertSet('media.0.crop.default.crop_w', 100)
        ->assertSet('media.2.crop', []);
});

it('opens the caption modal preloaded with existing metadata', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true, 'withCaption' => true])
        ->set('media', [array_merge(mediaPayload(1), ['metadata' => ['caption' => 'Hello', 'alt' => 'An alt']])])
        ->call('editCaption', 0)
        ->assertSet('showCaptionModal', true)
        ->assertSet('captionIndex', 0)
        ->assertSet('captionText', 'Hello')
        ->assertSet('captionAlt', 'An alt');
});

it('saves caption and alt into the item metadata', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true, 'withCaption' => true])
        ->set('media', [mediaPayload(1)])
        ->call('editCaption', 0)
        ->set('captionText', 'A caption')
        ->set('captionAlt', 'Some alt')
        ->call('saveCaption')
        ->assertSet('showCaptionModal', false)
        ->assertSet('captionIndex', null)
        ->assertSet('media.0.metadata.caption', 'A caption')
        ->assertSet('media.0.metadata.alt', 'Some alt');
});

it('drops empty caption and alt values from metadata', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true, 'withCaption' => true])
        ->set('media', [mediaPayload(1)])
        ->call('editCaption', 0)
        ->set('captionText', '')
        ->set('captionAlt', '')
        ->call('saveCaption')
        ->assertSet('media.0.metadata', []);
});

it('ignores setCrops for an unknown item index', function (): void {
    Livewire::test('admin.media-selector', ['multiple' => true])
        ->set('media', [mediaPayload(1)])
        ->call('setCrops', 9, ['default' => ['crop_w' => 1]])
        ->assertSet('media.0.crop', []);
});
