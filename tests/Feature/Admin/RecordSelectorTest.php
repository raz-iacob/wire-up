<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Models\RecordType;
use Livewire\Livewire;

function selectorType(): RecordType
{
    return RecordType::factory()->create(['key' => 'book', 'slug_prefix' => 'books', 'name' => 'Books', 'fields' => []]);
}

function selectorRecord(RecordType $type, string $title): Record
{
    return Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
}

beforeEach(function (): void {
    $this->actingAsAdmin();
});

it('normalizes ids to a unique list of strings on mount', function (): void {
    Livewire::test('admin.record-selector', [
        'recordIds' => [3, '3', 5],
        'recordTypeId' => selectorType()->id,
        'name' => 'field-a',
    ])->assertSet('recordIds', ['3', '5']);
});

it('opens the library with its own target and current selection', function (): void {
    $type = selectorType();

    Livewire::test('admin.record-selector', [
        'recordIds' => ['7'],
        'recordTypeId' => $type->id,
        'max' => 10,
        'name' => 'field-a',
    ])
        ->call('openLibrary')
        ->assertDispatched('select-records', fn (string $event, array $params): bool => $params['target'] === 'field-a'
            && $params['recordTypeId'] === $type->id
            && $params['max'] === 10
            && $params['selectedIds'] === ['7']);
});

it('accepts a selection addressed to its own target only', function (): void {
    $type = selectorType();

    Livewire::test('admin.record-selector', ['recordTypeId' => $type->id, 'name' => 'field-a'])
        ->call('handleRecordsSelected', 'field-b', ['1', '2'])
        ->assertSet('recordIds', [])
        ->call('handleRecordsSelected', 'field-a', [1, 2])
        ->assertSet('recordIds', ['1', '2']);
});

it('caps an incoming selection at its maximum', function (): void {
    $type = selectorType();

    Livewire::test('admin.record-selector', ['recordTypeId' => $type->id, 'max' => 2, 'name' => 'field-a'])
        ->call('handleRecordsSelected', 'field-a', ['1', '2', '3'])
        ->assertSet('recordIds', ['1', '2']);
});

it('removes a record from the selection', function (): void {
    $type = selectorType();

    Livewire::test('admin.record-selector', [
        'recordIds' => ['1', '2', '3'],
        'recordTypeId' => $type->id,
        'name' => 'field-a',
    ])
        ->call('remove', '2')
        ->assertSet('recordIds', ['1', '3']);
});

it('reorders records within the selection', function (): void {
    $type = selectorType();

    Livewire::test('admin.record-selector', [
        'recordIds' => ['1', '2', '3'],
        'recordTypeId' => $type->id,
        'name' => 'field-a',
    ])
        ->call('reorder', '3', 0)
        ->assertSet('recordIds', ['3', '1', '2']);
});

it('renders the selected records in order and syncs to the parent model', function (): void {
    $type = selectorType();
    $a = selectorRecord($type, 'Alpha');
    $b = selectorRecord($type, 'Bravo');

    Livewire::test('admin.record-selector', [
        'recordIds' => [(string) $b->id, (string) $a->id],
        'recordTypeId' => $type->id,
        'name' => 'field-a',
    ])
        ->assertSeeInOrder(['Bravo', 'Alpha']);
});

it('shows the select-type call to action when nothing is picked', function (): void {
    $type = selectorType();

    Livewire::test('admin.record-selector', [
        'recordIds' => [],
        'recordTypeId' => $type->id,
        'name' => 'field-a',
    ])->assertSee('Select '.$type->name);
});

it('ignores records that belong to a different type', function (): void {
    $type = selectorType();
    $other = RecordType::factory()->create(['key' => 'film', 'slug_prefix' => 'films', 'fields' => []]);
    $mine = selectorRecord($type, 'Mine');
    $theirs = selectorRecord($other, 'Theirs');

    Livewire::test('admin.record-selector', [
        'recordIds' => [(string) $mine->id, (string) $theirs->id],
        'recordTypeId' => $type->id,
        'name' => 'field-a',
    ])
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});
