<?php

declare(strict_types=1);

use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;
use Livewire\Livewire;

it('renders the content types screen for admins', function (): void {
    $this->actingAsAdmin();

    $this->get(route('admin.settings-content-types'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-content-types');
});

it('redirects guests away from the content types screen', function (): void {
    $this->get(route('admin.settings-content-types'))->assertRedirect(route('login'));
});

it('redirects non-admins away from the content types screen', function (): void {
    $this->actingAs(User::factory()->create(['active' => true, 'admin' => false]));

    $this->get(route('admin.settings-content-types'))->assertRedirectToRoute('home');
});

it('hydrates existing types on mount', function (): void {
    $this->actingAsAdmin();
    RecordType::factory()->create(['name' => 'Product', 'slug_prefix' => 'products']);

    $component = Livewire::test('pages::admin.settings-content-types');

    expect($component->get('types'))->toHaveCount(1)
        ->and($component->get('types.0.slug_prefix'))->toBe('products');
});

it('adds preset and custom rows in memory without persisting', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-content-types')
        ->call('addPreset', 'product')
        ->assertCount('types', 1)
        ->call('addCustom')
        ->assertCount('types', 2);

    expect(RecordType::query()->count())->toBe(0);
});

it('saves new preset and custom types on update', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-content-types')
        ->call('addPreset', 'product')
        ->call('addCustom')
        ->set('types.1.name', 'Gadget')
        ->set('types.1.slug_prefix', 'gadgets')
        ->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('content-types-updated');

    $this->assertDatabaseHas('record_types', ['key' => 'product', 'slug_prefix' => 'products']);
    $this->assertDatabaseHas('record_types', ['slug_prefix' => 'gadgets']);
});

it('updates an existing type on update', function (): void {
    $this->actingAsAdmin();
    $type = RecordType::factory()->create(['slug_prefix' => 'old', 'name' => 'Old']);

    Livewire::test('pages::admin.settings-content-types')
        ->set('types.0.name', 'New')
        ->set('types.0.slug_prefix', 'new')
        ->call('update')
        ->assertHasNoErrors();

    expect($type->fresh()->slug_prefix)->toBe('new')
        ->and($type->fresh()->name)->toBe('New');
});

it('rejects reserved and duplicate URL prefixes on update', function (string $prefix): void {
    $this->actingAsAdmin();
    RecordType::factory()->create(['slug_prefix' => 'products']);

    Livewire::test('pages::admin.settings-content-types')
        ->call('addCustom')
        ->set('types.1.name', 'Thing')
        ->set('types.1.slug_prefix', $prefix)
        ->call('update')
        ->assertHasErrors('types.1.slug_prefix');
})->with([
    'reserved' => ['admin'],
    'duplicate' => ['products'],
]);

it('rejects two new types sharing a URL prefix', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-content-types')
        ->call('addCustom')
        ->set('types.0.name', 'One')
        ->set('types.0.slug_prefix', 'dup')
        ->call('addCustom')
        ->set('types.1.name', 'Two')
        ->set('types.1.slug_prefix', 'dup')
        ->call('update')
        ->assertHasErrors('types.1.slug_prefix');

    expect(RecordType::query()->count())->toBe(0);
});

it('adds, removes and reorders fields', function (): void {
    $this->actingAsAdmin();
    RecordType::factory()->create(['fields' => []]);

    $component = Livewire::test('pages::admin.settings-content-types');
    $key = $component->get('types.0._key');

    $component->call('addField', $key, 'text');
    $component->call('addField', $key, 'number');
    expect($component->get('types.0.fields'))->toHaveCount(2);

    $firstField = $component->get('types.0.fields.0._key');
    $secondField = $component->get('types.0.fields.1._key');

    $component->call('reorderFields', $secondField, 0);
    expect($component->get('types.0.fields.0._key'))->toBe($secondField);

    $component->call('removeField', $firstField);
    expect($component->get('types.0.fields'))->toHaveCount(1);
});

it('rejects duplicate and reserved field keys on update', function (array $fields, string $errorKey): void {
    $this->actingAsAdmin();
    RecordType::factory()->create(['slug_prefix' => 'items', 'name' => 'Item']);

    $component = Livewire::test('pages::admin.settings-content-types');

    $rows = array_map(fn (array $field): array => [...$field, '_key' => uniqid('', true)], $fields);

    $component->set('types.0.fields', $rows)
        ->call('update')
        ->assertHasErrors($errorKey);
})->with([
    'duplicate keys' => [
        [
            ['key' => 'price', 'type' => 'number', 'label' => 'Price', 'required' => false, 'translatable' => false, 'help' => '', 'options' => ''],
            ['key' => 'price', 'type' => 'text', 'label' => 'Price 2', 'required' => false, 'translatable' => false, 'help' => '', 'options' => ''],
        ],
        'types.0.fields.0.key',
    ],
    'reserved key' => [
        [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => false, 'translatable' => false, 'help' => '', 'options' => ''],
        ],
        'types.0.fields.0.key',
    ],
]);

it('persists reordered positions on update', function (): void {
    $this->actingAsAdmin();
    $first = RecordType::factory()->create(['position' => 0]);
    $second = RecordType::factory()->create(['position' => 1]);

    $component = Livewire::test('pages::admin.settings-content-types');
    $firstKey = $component->get('types.0._key');

    $component->call('reorderTypes', $firstKey, 1)->call('update')->assertHasNoErrors();

    expect($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(0);
});

it('deletes a saved type immediately on confirm and drops unsaved rows', function (): void {
    $this->actingAsAdmin();
    $type = RecordType::factory()->create();

    $component = Livewire::test('pages::admin.settings-content-types');
    $savedKey = $component->get('types.0._key');

    $component->call('addCustom');
    $unsavedKey = $component->get('types.1._key');

    $component->call('confirmRemove', $unsavedKey)->call('removeType');
    expect($component->get('types'))->toHaveCount(1);
    $this->assertModelExists($type);

    $component->call('confirmRemove', $savedKey)->call('removeType')
        ->assertDispatched('content-types-updated');
    expect($component->get('types'))->toHaveCount(0);
    $this->assertModelMissing($type);

    $component->call('update')->assertHasNoErrors();
});

it('blocks removing a content type that still has records', function (): void {
    $this->actingAsAdmin();
    $type = RecordType::factory()->create();
    Record::factory()->create(['record_type_id' => $type->id]);

    $component = Livewire::test('pages::admin.settings-content-types');
    $key = $component->get('types.0._key');

    $component->call('confirmRemove', $key);
    expect($component->get('removeRecordCount'))->toBe(1);

    $component->call('removeType');
    expect($component->get('types'))->toHaveCount(1);
    $this->assertModelExists($type);
});
