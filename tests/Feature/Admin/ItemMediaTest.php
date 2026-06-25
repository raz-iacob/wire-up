<?php

declare(strict_types=1);

use Livewire\Livewire;

it('dispatches an update event keyed by block and item id when its value changes', function (): void {
    Livewire::test('admin.blocks.item-media', [
        'blockId' => 'b1',
        'itemId' => 'item-one',
        'field' => 'avatar',
        'value' => null,
    ])
        ->set('value', ['id' => 7, 'source' => 'media/photo.jpg'])
        ->assertDispatched('block-item-media-updated', fn (string $event, array $params): bool => $params['blockId'] === 'b1'
            && $params['itemId'] === 'item-one'
            && $params['field'] === 'avatar'
            && $params['value'] === ['id' => 7, 'source' => 'media/photo.jpg']);
});

it('seeds the inner media selector with the provided value', function (): void {
    Livewire::test('admin.blocks.item-media', [
        'blockId' => 'b1',
        'itemId' => 'item-one',
        'field' => 'logo',
        'value' => ['id' => 3, 'filename' => 'logo.svg'],
    ])->assertSet('value', ['id' => 3, 'filename' => 'logo.svg']);
});
