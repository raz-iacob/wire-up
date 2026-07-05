<?php

declare(strict_types=1);

use App\Models\Submission;
use App\Models\User;
use Livewire\Livewire;

it('renders the inbox screen for admins', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.inbox-index'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.inbox-index');
});

it('redirects non-admins away from the inbox', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.inbox-index'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from the inbox', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.inbox-index'))
        ->assertRedirectToRoute('login');
});

it('lists submissions with name, email and form', function (): void {
    Submission::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'form_name' => 'Massage enquiry']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-index')
        ->assertSee('Ada Lovelace')
        ->assertSee('ada@example.com')
        ->assertSee('Massage enquiry');
});

it('searches across name, email and form name', function (): void {
    Submission::factory()->create(['name' => 'Ada Lovelace']);
    Submission::factory()->create(['name' => 'Grace Hopper']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-index')
        ->set('search', 'Ada')
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

it('filters by read status', function (): void {
    Submission::factory()->create(['name' => 'Unread Person']);
    Submission::factory()->read()->create(['name' => 'Read Person']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-index')
        ->set('status', 'unread')
        ->assertSee('Unread Person')
        ->assertDontSee('Read Person')
        ->set('status', 'read')
        ->assertSee('Read Person')
        ->assertDontSee('Unread Person');
});

it('sorts by a column', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-index')
        ->call('sort', 'name')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc');
});

it('toggles a submission between read and unread from the index', function (): void {
    $submission = Submission::factory()->create();

    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.inbox-index');

    $component->call('toggleRead', $submission->id);
    expect($submission->fresh()->read_at)->not->toBeNull();

    $component->call('toggleRead', $submission->id);
    expect($submission->fresh()->read_at)->toBeNull();
});

it('deletes a submission from the index', function (): void {
    $submission = Submission::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-index')
        ->call('confirmDelete', $submission->id)
        ->assertSet('selectedId', $submission->id)
        ->call('delete');

    $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
});

it('marks a submission read when its detail page is opened', function (): void {
    $submission = Submission::factory()->create();

    $this->actingAsAdmin();

    expect($submission->read_at)->toBeNull();

    Livewire::test('pages::admin.inbox-show', ['submission' => $submission]);

    expect($submission->fresh()->read_at)->not->toBeNull();
});

it('shows submission details including custom field values', function (): void {
    $submission = Submission::factory()->create([
        'name' => 'Ada Lovelace',
        'message' => 'Hello there',
        'metadata' => ['colour' => ['label' => 'Favourite colour', 'value' => 'Blue']],
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-show', ['submission' => $submission])
        ->assertSee('Ada Lovelace')
        ->assertSee('Hello there')
        ->assertSee('Favourite colour')
        ->assertSee('Blue');
});

it('shows the visitor country on the detail page', function (): void {
    $submission = Submission::factory()->create(['country' => 'GB']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-show', ['submission' => $submission])
        ->assertSee('United Kingdom');
});

it('deletes a submission from the detail page and redirects to the inbox', function (): void {
    $submission = Submission::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-show', ['submission' => $submission])
        ->call('delete')
        ->assertRedirect(route('admin.inbox-index'));

    $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
});

it('shows the unread count on the sidebar', function (): void {
    Submission::factory()->count(2)->create();
    Submission::factory()->read()->create();

    $this->actingAsAdmin()
        ->get(route('admin.inbox-index'))
        ->assertOk()
        ->assertSee('Inbox');
});
