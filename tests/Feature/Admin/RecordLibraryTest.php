<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Models\RecordType;
use Livewire\Livewire;

function libraryType(): RecordType
{
    return RecordType::factory()->create([
        'key' => 'book',
        'slug_prefix' => 'books',
        'name' => 'Books',
        'fields' => [
            ['key' => 'sku', 'type' => 'text', 'column' => true, 'searchable' => true],
        ],
    ]);
}

function libraryRecord(RecordType $type, string $title, string $sku = ''): Record
{
    return Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'data' => ['sku' => $sku],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
}

beforeEach(function (): void {
    $this->actingAsAdmin();
});

it('loads records of the requested type when opened', function (): void {
    $type = libraryType();
    libraryRecord($type, 'Alpha');
    libraryRecord($type, 'Beta');

    Livewire::test('admin.record-library')
        ->call('handleSelectRecords', 'target-a', $type->id, 30, [])
        ->assertSet('showLibrary', true)
        ->assertSet('recordTypeId', $type->id)
        ->assertSee('Alpha')
        ->assertSee('Beta');
});

it('filters records by title and searchable fields', function (): void {
    $type = libraryType();
    libraryRecord($type, 'Wireless Keyboard', 'AAA-1');
    libraryRecord($type, 'Mouse', 'ZZZ-keyboard');
    libraryRecord($type, 'Monitor', 'MON-9');

    Livewire::test('admin.record-library')
        ->call('handleSelectRecords', 'target-a', $type->id, 30, [])
        ->set('search', 'keyboard')
        ->assertSee('Wireless Keyboard')
        ->assertSee('Mouse')
        ->assertDontSee('Monitor');
});

it('toggles selection and respects the maximum', function (): void {
    $type = libraryType();
    $one = libraryRecord($type, 'One');
    $two = libraryRecord($type, 'Two');

    Livewire::test('admin.record-library')
        ->call('handleSelectRecords', 'target-a', $type->id, 1, [])
        ->call('toggle', $one->id)
        ->assertSet('selectedIds', [$one->id])
        ->call('toggle', $two->id)
        ->assertSet('selectedIds', [$one->id])
        ->call('toggle', $one->id)
        ->assertSet('selectedIds', []);
});

it('dispatches the ordered selection as strings on insert and closes', function (): void {
    $type = libraryType();
    $one = libraryRecord($type, 'One');
    $two = libraryRecord($type, 'Two');

    Livewire::test('admin.record-library')
        ->call('handleSelectRecords', 'target-a', $type->id, 30, [])
        ->call('toggle', $two->id)
        ->call('toggle', $one->id)
        ->call('insert')
        ->assertSet('showLibrary', false)
        ->assertDispatched('records-selected', fn (string $event, array $params): bool => $params['target'] === 'target-a'
            && $params['ids'] === [(string) $two->id, (string) $one->id]);
});

it('pre-selects the ids it was opened with', function (): void {
    $type = libraryType();
    $one = libraryRecord($type, 'One');

    Livewire::test('admin.record-library')
        ->call('handleSelectRecords', 'target-a', $type->id, 30, [(string) $one->id])
        ->assertSet('selectedIds', [$one->id]);
});
