<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Role;
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
    Submission::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    Submission::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

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

it('marks the selected messages as read or unread', function (): void {
    $unread = Submission::factory()->create();
    $read = Submission::factory()->read()->create();
    $untouched = Submission::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-index')
        ->set('selected', [(string) $unread->id, (string) $read->id])
        ->call('markSelectedRead')
        ->assertDispatched('toast-show', toastSays('1 message marked as read.'));

    expect($unread->fresh()->isRead())->toBeTrue()
        ->and($untouched->fresh()->isRead())->toBeFalse();

    Livewire::test('pages::admin.inbox-index')
        ->set('selected', [(string) $unread->id, (string) $read->id])
        ->call('markSelectedUnread')
        ->assertDispatched('toast-show', toastSays('2 messages marked as unread.'));

    expect($unread->fresh()->isRead())->toBeFalse()
        ->and($read->fresh()->isRead())->toBeFalse();
});

it('deletes the selected messages for staff who may delete them', function (): void {
    $doomed = Submission::factory()->count(2)->create();
    $kept = Submission::factory()->create();
    $viewer = User::factory()->role(Role::factory()->create(['abilities' => ['inbox.view'], 'bypass' => false]))->create(['active' => true]);
    $ids = $doomed->map(fn (Submission $submission): string => (string) $submission->id)->all();

    $this->actingAs($viewer);

    Livewire::test('pages::admin.inbox-index')
        ->assertDontSeeHtml('wire:click="deleteSelected"')
        ->set('selected', $ids)
        ->call('deleteSelected')
        ->assertForbidden();

    expect(Submission::query()->count())->toBe(3);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-index')
        ->set('selected', $ids)
        ->call('deleteSelected')
        ->assertDispatched('toast-show', toastSays('2 messages deleted.'));

    expect(Submission::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('exports the filtered messages with each extra form field as its own column', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 29)->setTime(9, 30));
    $page = Page::factory()->create(['title' => 'Contact']);
    Submission::factory()->for($page)->create([
        'form_name' => 'Quote request',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Hello',
        'country' => 'GB',
        'metadata' => [['label' => 'Budget', 'value' => '500'], ['label' => 'Newsletter', 'value' => true]],
    ]);
    Submission::factory()->create(['name' => 'Grace Hopper', 'metadata' => [['label' => 'Company', 'value' => 'Navy']]]);
    Submission::factory()->read()->create(['name' => 'Already read', 'metadata' => [['label' => 'Ignored', 'value' => 'x']]]);

    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.inbox-index')
        ->set('status', 'unread')
        ->set('sortDirection', 'asc')
        ->set('sortBy', 'name')
        ->call('export')
        ->assertFileDownloaded('inbox-2026-10-29.csv');

    $rows = downloadedCsv($component);

    expect($rows[0])->toBe(['Date', 'Form', 'Name', 'Email', 'Phone', 'Subject', 'Message', 'Budget', 'Newsletter', 'Company', 'Page', 'Country'])
        ->and($rows)->toHaveCount(3)
        ->and($rows[1])->toBe(['2026-10-29 09:30', 'Quote request', 'Ada Lovelace', 'ada@example.com', '', '', 'Hello', '500', 'Yes', '', 'Contact', 'United Kingdom'])
        ->and(array_slice($rows[2], 7, 3))->toBe(['', '', 'Navy']);
});
