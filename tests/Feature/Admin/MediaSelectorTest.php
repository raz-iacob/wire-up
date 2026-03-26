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
    Livewire::test('media-selector')
        ->assertSee('Attach Media')
        ->assertSee('No media selected');
});

it('stores a single selected media item in single mode', function (): void {
    $component = Livewire::test('media-selector', [
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
    $component = Livewire::test('media-selector', [
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
    $component = Livewire::test('media-selector', [
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
    Livewire::test('media-selector', [
        'multiple' => true,
    ])
        ->set('media', [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg')])
        ->call('removeMedia', 0)
        ->assertCount('media', 1)
        ->assertSet('media.0.id', 2);
});

it('reorders selected media items', function (): void {
    Livewire::test('media-selector', [
        'multiple' => true,
    ])
        ->set('media', [mediaPayload(1, 'first.jpg'), mediaPayload(2, 'second.jpg'), mediaPayload(3, 'third.jpg')])
        ->call('reorderMedia', 3, 0)
        ->assertSet('media.0.id', 3)
        ->assertSet('media.1.id', 1)
        ->assertSet('media.2.id', 2);
});

it('dispatches the library event when opening the selector', function (): void {
    Livewire::test('media-selector', [
        'multiple' => true,
        'max' => 5,
    ])
        ->set('media', [mediaPayload(1, 'first.jpg')])
        ->call('openLibrary')
        ->assertDispatched('select-media');
});
