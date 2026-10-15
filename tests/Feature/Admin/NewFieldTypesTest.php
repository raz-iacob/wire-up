<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateContentTypeTool;
use App\Mcp\Tools\CreateRecordTool;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\ContentMarkdown;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newTypeField(string $key, string $type, array $overrides = []): array
{
    return ['key' => $key, 'type' => $type, 'label' => ['en' => ucfirst($key)], 'required' => false, 'translatable' => false, 'column' => true, 'sortable' => false, 'searchable' => false, 'filterable' => false, 'help' => '', 'options' => [], ...$overrides];
}

function newFieldsType(): RecordType
{
    return RecordType::factory()->create([
        'key' => 'listing',
        'slug_prefix' => 'listings',
        'fields' => [
            newTypeField('contact', 'email', ['translatable' => true]),
            newTypeField('shade', 'color'),
            newTypeField('labels', 'tags'),
            newTypeField('features', 'multi-select', ['options' => ['Garden', 'Garage', 'Pool'], 'filterable' => true]),
        ],
    ]);
}

function newFieldsRecord(RecordType $type, string $title = 'Cottage'): Record
{
    return Record::query()->findOrFail(resolve(CreateRecordAction::class)->handle($type, ['title' => $title])->id);
}

it('saves an email, a color, tags and several choices from the record editor', function (): void {
    $this->actingAsAdmin();
    $type = newFieldsType();
    $record = newFieldsRecord($type);

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->set('data.contact', 'owner@example.com')
        ->set('data.shade', '#3366ff')
        ->set('tagSearch.labels', '  Seaside ')
        ->call('addTag', 'labels')
        ->set('tagSearch.labels', 'Seaside')
        ->call('addTag', 'labels')
        ->set('tagSearch.labels', 'Quiet')
        ->call('addTag', 'labels')
        ->call('addTag', 'missing')
        ->assertSet('tagSearch.labels', '')
        ->set('data.features', ['Pool', 'Garden', ''])
        ->call('update')
        ->assertHasNoErrors();

    expect($record->refresh()->data)->toMatchArray([
        'contact' => 'owner@example.com',
        'shade' => '#3366ff',
        'labels' => ['Seaside', 'Quiet'],
        'features' => ['Pool', 'Garden'],
    ]);
});

it('rejects a bad email, a colour that is not hex and a choice outside the options', function (): void {
    $this->actingAsAdmin();
    $type = newFieldsType();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => newFieldsRecord($type)])
        ->set('data.contact', 'not-an-email')
        ->set('data.shade', 'blue')
        ->set('data.features', ['Helipad'])
        ->call('update')
        ->assertHasErrors(['data.contact', 'data.shade', 'data.features.0']);
});

it('shows list values joined in the admin list, collection cards and markdown', function (): void {
    $type = newFieldsType();
    $record = newFieldsRecord($type);
    $record->update(['data' => ['labels' => ['Seaside', 'Quiet'], 'features' => ['Pool']]]);

    expect($record->columnValue(newTypeField('labels', 'tags')))->toBe('Seaside, Quiet')
        ->and(ContentMarkdown::current()->render($record->refresh()))->toContain('**Labels:** Seaside, Quiet');
});

it('filters records by one of several choices and never sorts by a list', function (): void {
    $this->actingAsAdmin();
    $type = newFieldsType();
    newFieldsRecord($type, 'Pool House')->update(['data' => ['features' => ['Pool', 'Garden']]]);
    newFieldsRecord($type, 'Plain Flat')->update(['data' => ['features' => ['Garage']]]);

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('filters.features', 'Pool')
        ->assertSee('Pool House')
        ->assertDontSee('Plain Flat')
        ->assertSee('Features: Pool');

    $type->update(['fields' => [newTypeField('labels', 'tags', ['sortable' => true])]]);

    expect($type->refresh()->sortableFieldKeys())->toBeEmpty();
});

it('never saves the new types as translatable from the content types screen', function (): void {
    $this->actingAsAdmin();
    $type = newFieldsType();

    Livewire::test('pages::admin.settings-content-types')
        ->set('types.0.fields.0.translatable', true)
        ->call('update')
        ->assertHasNoErrors();

    expect($type->refresh()->fields[0]['translatable'])->toBeFalse();
});

it('lets agents create types and records with the new fields', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, [
        'name' => 'Houses',
        'slug_prefix' => 'houses',
        'fields' => [
            ['key' => 'labels', 'type' => 'tags', 'label' => 'Labels', 'translatable' => true, 'sortable' => true],
            ['key' => 'features', 'type' => 'multi-select', 'label' => 'Features', 'options' => ['Garden', 'Pool']],
        ],
    ])->assertOk();

    $type = RecordType::query()->where('slug_prefix', 'houses')->firstOrFail();

    expect($type->fields[0])->toMatchArray(['translatable' => false, 'sortable' => false]);

    WireUpServer::tool(CreateRecordTool::class, [
        'type' => $type->key,
        'title' => 'Beach House',
        'data' => ['labels' => 'Seaside, Quiet, Seaside', 'features' => ['Pool', 'Helipad']],
    ])->assertOk();

    expect(Record::query()->where('record_type_id', $type->id)->firstOrFail()->data)->toMatchArray([
        'labels' => ['Seaside', 'Quiet'],
        'features' => ['Pool'],
    ]);
});
