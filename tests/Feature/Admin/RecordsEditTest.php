<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;
use Livewire\Livewire;

function typeWithFields(): RecordType
{
    return RecordType::factory()->create([
        'name' => 'Products',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'price', 'type' => 'number', 'label' => ['en' => 'Price'], 'required' => false, 'translatable' => false, 'column' => true, 'sortable' => false, 'searchable' => false, 'help' => '', 'options' => []],
            ['key' => 'summary', 'type' => 'text', 'label' => ['en' => 'Summary'], 'required' => true, 'translatable' => true, 'column' => false, 'sortable' => false, 'searchable' => false, 'help' => '', 'options' => []],
            ['key' => 'photo', 'type' => 'photo', 'label' => ['en' => 'Photo'], 'required' => false, 'translatable' => false, 'column' => false, 'sortable' => false, 'searchable' => false, 'help' => '', 'options' => []],
        ],
    ]);
}

function makeRecord(RecordType $type, string $title = 'Sample'): Record
{
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => $title]);

    return Record::query()->findOrFail($record->id);
}

it('can render the records edit screen', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type);

    $this->actingAsAdmin()
        ->fromRoute('admin.records-index', $type)
        ->get(route('admin.records-edit', [$type, $record]))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.records-edit')
        ->assertSeeLivewire('admin.media-selector');
});

it('returns 404 when the record does not belong to the type', function (): void {
    $type = typeWithFields();
    $other = RecordType::factory()->create(['slug_prefix' => 'services']);
    $record = makeRecord($other);

    $this->actingAsAdmin()
        ->get(route('admin.records-edit', [$type, $record]))
        ->assertNotFound();
});

it('redirects non-admins away from records edit', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type);
    $user = User::factory()->create(['active' => true, 'admin' => false]);

    $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.records-edit', [$type, $record]))
        ->assertRedirectToRoute('home');
});

it('populates the form on mount', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type, 'Widget');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->assertSet('record.id', $record->id)
        ->assertSet('title.en', 'Widget')
        ->assertSet('status', ContentStatus::DRAFT);
});

it('can update the record title, status, slug and custom data', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->set('status', ContentStatus::PUBLISHED)
        ->set('title.en', 'Renamed Widget')
        ->set('slugs.en', 'renamed-widget')
        ->set('data.price', 99)
        ->set('data.summary.en', 'a short summary')
        ->call('update')
        ->assertHasNoErrors();

    $record->refresh();

    expect($record->title)->toBe('Renamed Widget')
        ->and($record->status)->toBe(ContentStatus::PUBLISHED)
        ->and($record->data['price'])->toBe(99)
        ->and($record->getSlug('en'))->toBe('renamed-widget');
});

it('namespaces the slug under the type prefix', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->set('status', ContentStatus::PUBLISHED)
        ->set('title.en', 'Slugged')
        ->set('slugs.en', 'slugged')
        ->set('data.summary.en', 'x')
        ->call('update')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('slugs', [
        'sluggable_id' => $record->id,
        'sluggable_type' => 'record',
        'slug' => 'slugged',
        'base_path' => 'products',
    ]);
});

it('shows the record type slug prefix on the web address field', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->assertSee('products/');
});

it('requires a translatable required field for published locales', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->set('status', ContentStatus::PUBLISHED)
        ->set('title.en', 'Has Title')
        ->set('slugs.en', 'has-title')
        ->set('data.summary.en', '')
        ->call('update')
        ->assertHasErrors(['data.summary.en']);
});

it('strips money formatting and stores a numeric value', function (): void {
    $type = RecordType::factory()->create([
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'price', 'type' => 'money', 'label' => ['en' => 'Price'], 'required' => false, 'translatable' => false, 'column' => true, 'sortable' => false, 'searchable' => false, 'help' => '', 'options' => []],
        ],
    ]);
    $record = makeRecord($type);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->set('status', ContentStatus::DRAFT)
        ->set('title.en', 'Priced')
        ->set('data.price', '1,234.56')
        ->call('update')
        ->assertHasNoErrors();

    expect($record->refresh()->data['price'])->toBe(1234.56);
});

it('can attach a media field value', function (): void {
    $type = typeWithFields();
    $record = makeRecord($type);
    $media = Media::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->set('status', ContentStatus::DRAFT)
        ->set('title.en', 'With Photo')
        ->set('data.summary.en', 'x')
        ->set('media.photo.en', [['id' => $media->id]])
        ->call('update')
        ->assertHasNoErrors();

    $record->refresh()->load('media');

    expect($record->media)->toHaveCount(1)
        ->and($record->media->first()->pivot->role)->toBe('photo');
});
