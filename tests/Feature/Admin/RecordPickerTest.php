<?php

declare(strict_types=1);

use App\Models\Page;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

it('normalizes its initial value to strings', function (): void {
    Livewire::test('admin.blocks.record-picker', [
        'blockId' => 'b1',
        'recordTypeId' => 1,
        'value' => [4, '4', 9],
    ])->assertSet('value', ['4', '9']);
});

it('dispatches a block-records-updated event keyed by block when its value changes', function (): void {
    Livewire::test('admin.blocks.record-picker', [
        'blockId' => 'b1',
        'recordTypeId' => 1,
        'value' => [],
    ])
        ->set('value', ['3', '7'])
        ->assertDispatched('block-records-updated', fn (string $event, array $params): bool => $params['blockId'] === 'b1'
            && $params['value'] === ['3', '7']);
});

it('writes selected record ids into the matching block content', function (): void {
    $page = Page::factory()->create();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('blocks', [
            'b1' => ['id' => 'b1', 'type' => 'collection', 'position' => 0, 'content' => ['recordIds' => []]],
        ])
        ->call('syncBlockRecords', 'b1', [5, '5', 8])
        ->assertSet('blocks.b1.content.recordIds', ['5', '8']);
});

it('ignores a records update for an unknown block', function (): void {
    $page = Page::factory()->create();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('blocks', [
            'b1' => ['id' => 'b1', 'type' => 'collection', 'position' => 0, 'content' => ['recordIds' => ['1']]],
        ])
        ->call('syncBlockRecords', 'missing', ['9'])
        ->assertSet('blocks.b1.content.recordIds', ['1']);
});
