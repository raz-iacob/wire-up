<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;
use Livewire\Livewire;

function productType(): RecordType
{
    return RecordType::factory()->create([
        'name' => 'Products',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'price', 'type' => 'number', 'label' => ['en' => 'Price'], 'required' => false, 'translatable' => false, 'column' => true, 'sortable' => true, 'searchable' => false, 'help' => '', 'options' => []],
            ['key' => 'summary', 'type' => 'text', 'label' => ['en' => 'Summary'], 'required' => false, 'translatable' => true, 'column' => false, 'sortable' => false, 'searchable' => true, 'help' => '', 'options' => []],
        ],
    ]);
}

it('can render the records index screen', function (): void {
    $type = productType();

    $this->actingAsAdmin()
        ->fromRoute('admin.dashboard')
        ->get(route('admin.records-index', $type))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.records-index');
});

it('redirects authenticated non-admin users away from records index', function (): void {
    $type = productType();
    $user = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.records-index', $type))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from records index', function (): void {
    $type = productType();

    $this->fromRoute('home')
        ->get(route('admin.records-index', $type))
        ->assertRedirectToRoute('login');
});

it('shows a View link to the public record url', function (): void {
    $type = productType();
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Blue Widget']);
    $slug = $record->slugs()->where('locale', 'en')->value('slug');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertSee('View')
        ->assertSee(route('record', ['products', $slug]));
});

it('shows a thumbnail in the title cell when the type has an image field', function (): void {
    $type = RecordType::factory()->create([
        'slug_prefix' => 'gear',
        'fields' => [['key' => 'photo', 'type' => 'photo', 'translatable' => false]],
    ]);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Widget']]);
    $image = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/widget.jpg']);
    $record->media()->attach($image->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0]);

    $thumbnail = Record::query()->with(['media', 'recordType'])->find($record->id)->primaryImageUrl(200);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertSee($thumbnail, false);
});

it('does not render thumbnails for a type without an image field', function (): void {
    $type = productType();
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Blue Widget']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertDontSee('object-cover');
});

it('duplicates a record via the modal with an editable title', function (): void {
    $type = productType();
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Blue Widget']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertSee('Duplicate')
        ->call('duplicate', $record->id)
        ->assertSet('duplicateTitle', 'Copy of Blue Widget')
        ->set('duplicateTitle', 'My Custom Copy')
        ->call('confirmDuplicate')
        ->assertRedirect();

    expect(Record::query()->where('record_type_id', $type->id)->count())->toBe(2)
        ->and(Record::query()->whereTranslationLike('title', 'My Custom Copy')->exists())->toBeTrue();
});

it('lists records of the type with a custom column value', function (): void {
    $type = productType();
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Blue Widget', 'data' => ['price' => 42]]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertSee('Blue Widget')
        ->assertSee('Price')
        ->assertSee('42');
});

it('only shows records belonging to the type', function (): void {
    $type = productType();
    $other = RecordType::factory()->create(['name' => 'Services', 'slug_prefix' => 'services']);

    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Mine']);
    resolve(CreateRecordAction::class)->handle($other, ['title' => 'Theirs']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

it('can search records by title', function (): void {
    $type = productType();
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Findable']);
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Hidden']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('search', 'Findable')
        ->assertSee('Findable')
        ->assertDontSee('Hidden');
});

it('can search records by a searchable custom field', function (): void {
    $type = productType();
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'One', 'data' => ['summary' => ['en' => 'sparkly item']]]);
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Two', 'data' => ['summary' => ['en' => 'boring item']]]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('search', 'sparkly')
        ->assertSee('One')
        ->assertDontSee('Two');
});

it('can sort records by title', function (): void {
    $type = productType();
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Alpha']);
    resolve(CreateRecordAction::class)->handle($type, ['title' => 'Beta']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->call('sort', 'title')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['Alpha', 'Beta'])
        ->call('sort', 'title')
        ->assertSeeInOrder(['Beta', 'Alpha']);
});

it('can add a new record and redirects to its editor', function (): void {
    $type = productType();

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('title', 'Fresh Record')
        ->call('create');

    $response->assertHasNoErrors();

    $this->assertDatabaseHas('records', ['record_type_id' => $type->id, 'status' => 'draft']);
});

it('validates the title when adding a record', function (): void {
    $type = productType();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('title', '')
        ->call('create')
        ->assertHasErrors(['title']);
});

it('can delete a record', function (): void {
    $type = productType();
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Doomed']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->call('delete', $record->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('records', ['id' => $record->id]);
});

function filterableProductType(): RecordType
{
    return RecordType::factory()->create([
        'name' => 'Products',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'sold', 'type' => 'boolean', 'label' => ['en' => 'Sold'], 'required' => false, 'translatable' => false, 'column' => true, 'sortable' => false, 'searchable' => false, 'filterable' => true, 'help' => '', 'options' => []],
            ['key' => 'condition', 'type' => 'select', 'label' => ['en' => 'Condition'], 'required' => false, 'translatable' => false, 'column' => false, 'sortable' => false, 'searchable' => false, 'filterable' => true, 'help' => '', 'options' => ['New', 'Refurbished']],
            ['key' => 'summary', 'type' => 'text', 'label' => ['en' => 'Summary'], 'required' => false, 'translatable' => false, 'column' => false, 'sortable' => false, 'searchable' => false, 'filterable' => true, 'help' => '', 'options' => []],
        ],
    ]);
}

it('offers a filter menu for filterable boolean and select fields only', function (): void {
    $type = filterableProductType();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertSee('Sold')
        ->assertSee('Condition')
        ->assertDontSee('Summary');

    expect(array_column($type->filterableFields(), 'key'))->toBe(['sold', 'condition']);
});

it('filters records by a boolean field', function (): void {
    $type = filterableProductType();
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Warp Core'], 'data' => ['sold' => true]]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Green Giant'], 'data' => ['sold' => false]]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertSee('Warp Core')
        ->assertSee('Green Giant')
        ->set('filters.sold', '1')
        ->assertSee('Warp Core')
        ->assertDontSee('Green Giant')
        ->set('filters.sold', '0')
        ->assertDontSee('Warp Core')
        ->assertSee('Green Giant')
        ->set('filters.sold', '')
        ->assertSee('Warp Core')
        ->assertSee('Green Giant');
});

it('filters records by a select field option', function (): void {
    $type = filterableProductType();
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Fresh Build'], 'data' => ['condition' => 'New']]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Older Build'], 'data' => ['condition' => 'Refurbished']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('filters.condition', 'New')
        ->assertSee('Fresh Build')
        ->assertDontSee('Older Build');
});

it('combines a field filter with the status filter and search', function (): void {
    $type = filterableProductType();
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Sold Draft'], 'data' => ['sold' => true], 'status' => ContentStatus::DRAFT]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Sold Live'], 'data' => ['sold' => true], 'status' => ContentStatus::PUBLISHED]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Unsold Live'], 'data' => ['sold' => false], 'status' => ContentStatus::PUBLISHED]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('filters.sold', '1')
        ->set('status', ContentStatus::PUBLISHED->value)
        ->assertSee('Sold Live')
        ->assertDontSee('Sold Draft')
        ->assertDontSee('Unsold Live');
});

it('ignores a filter whose field is not marked filterable', function (): void {
    $type = RecordType::factory()->create([
        'slug_prefix' => 'widgets',
        'fields' => [
            ['key' => 'sold', 'type' => 'boolean', 'label' => ['en' => 'Sold'], 'translatable' => false, 'filterable' => false, 'options' => []],
        ],
    ]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Widget A'], 'data' => ['sold' => true]]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Widget B'], 'data' => ['sold' => false]]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('filters.sold', '1')
        ->assertSee('Widget A')
        ->assertSee('Widget B');
});

it('filters records by a translatable select field in the active locale', function (): void {
    $type = RecordType::factory()->create([
        'slug_prefix' => 'tools',
        'fields' => [
            ['key' => 'grade', 'type' => 'select', 'label' => ['en' => 'Grade'], 'translatable' => true, 'filterable' => true, 'options' => ['Pro', 'Basic']],
        ],
    ]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Pro Tool'], 'data' => ['grade' => ['en' => 'Pro']]]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Basic Tool'], 'data' => ['grade' => ['en' => 'Basic']]]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('filters.grade', 'Pro')
        ->assertSee('Pro Tool')
        ->assertDontSee('Basic Tool');
});

it('returns to the first page when a filter or search changes', function (): void {
    $type = filterableProductType();

    foreach (range(1, 25) as $index) {
        Record::factory()->create([
            'record_type_id' => $type->id,
            'title' => ['en' => "Widget {$index}"],
            'data' => ['sold' => $index > 20],
        ]);
    }

    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('filters.sold', '1')
        ->assertSet('paginators.page', 1)
        ->call('gotoPage', 2)
        ->set('search', 'Widget')
        ->assertSet('paginators.page', 1)
        ->call('gotoPage', 2)
        ->set('status', 'draft')
        ->assertSet('paginators.page', 1);
});
