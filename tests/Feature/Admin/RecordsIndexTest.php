<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
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
    $user = User::factory()->create(['active' => true, 'admin' => false]);

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
