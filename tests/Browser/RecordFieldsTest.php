<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Models\Record;
use App\Models\RecordType;

it('adds a typed tag to a tags field and saves it', function (): void {
    $type = RecordType::factory()->create(['key' => 'listing', 'slug_prefix' => 'listings', 'fields' => [
        ['key' => 'labels', 'type' => 'tags', 'label' => ['en' => 'Labels'], 'required' => false, 'translatable' => false],
    ]]);
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Cottage']);

    $this->actingAsAdmin();

    $input = '[wire\\:key="field-labels-shared"] input';

    visit(route('admin.records-edit', [$type, $record]))
        ->click($input)
        ->typeSlowly($input, 'Seaside')
        ->waitForText('Add "Seaside"')
        ->click('[wire\\:key="field-labels-shared"] [data-flux-option-create]')
        ->wait(1)
        ->assertPresent('[wire\\:key="field-labels-shared"] ui-selected-remove')
        ->keys($input, 'Escape')
        ->click('button[type="submit"]:visible:has-text("Update")')
        ->assertSee('Changes saved.')
        ->assertNoJavascriptErrors();

    expect(Record::query()->findOrFail($record->id)->data['labels'] ?? null)->toBe(['Seaside']);
});
