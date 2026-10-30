<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\RevisionSource;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $attributes
 */
function activityAbout(ActivityEvent $event, ?Model $subject = null, array $attributes = []): Activity
{
    return Activity::factory()->create([
        'event' => $event,
        'subject_type' => $subject?->getMorphClass(),
        'subject_id' => $subject?->getKey(),
        'subject_label' => $subject instanceof Model ? 'Subject '.$subject->getKey() : null,
        ...$attributes,
    ]);
}

function activityViewer(array $abilities): User
{
    $role = Role::query()->firstOrCreate(['key' => 'activity-'.implode('-', $abilities)], ['name' => 'Viewer', 'abilities' => $abilities, 'bypass' => false, 'is_protected' => false]);

    return User::factory()->role($role)->create(['active' => true]);
}

it('lists who did what and when, newest first, linked from the settings sidebar', function (): void {
    $ada = User::factory()->create(['name' => 'Ada Lovelace']);
    activityAbout(ActivityEvent::SIGNED_IN, attributes: ['user_id' => $ada->id, 'source' => RevisionSource::EDITOR, 'ip' => '203.0.113.9', 'created_at' => now()->subHour()]);
    activityAbout(ActivityEvent::SETTINGS_UPDATED, attributes: ['properties' => ['keys' => ['site_name', 'mail_password']]]);

    $this->actingAsAdmin()
        ->get(route('admin.settings-activity'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-activity')
        ->assertSee(route('admin.settings-activity'))
        ->assertSeeInOrder(['Changed settings', 'Site Name, Mail Password', 'Ada Lovelace', 'Signed in'])
        ->assertSee('203.0.113.9')
        ->assertSee('Entries are kept for 180 days.');
});

it('links each item that still exists to where it is managed', function (): void {
    $page = Page::factory()->create();
    $record = Record::factory()->create();
    $category = Category::factory()->create();
    $type = RecordType::factory()->create();
    $user = User::factory()->create();
    $role = Role::query()->where('key', 'editor')->firstOrFail();
    $order = Order::factory()->create();
    $submission = Submission::factory()->create();
    $redirect = Redirect::factory()->create(['from' => '/old-team']);

    foreach ([$page, $record, $category, $type, $user, $role, $order, $submission, $redirect] as $subject) {
        activityAbout(ActivityEvent::SETTINGS_UPDATED, $subject);
    }

    activityAbout(ActivityEvent::MEDIA_UPLOADED, Media::factory()->create(), ['subject_label' => 'photo.jpg']);
    activityAbout(ActivityEvent::PAGE_DELETED, attributes: ['subject_type' => 'page', 'subject_id' => 999999, 'subject_label' => 'Gone page']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-activity')
        ->assertSee(route('admin.pages-edit', $page))
        ->assertSee(route('admin.records-edit', [$record->recordType, $record]))
        ->assertSee(route('admin.categories-edit', $category))
        ->assertSee(route('admin.settings-content-types'))
        ->assertSee(route('admin.users-edit', $user))
        ->assertSee(route('admin.settings-roles'))
        ->assertSee(route('admin.orders-show', $order))
        ->assertSee(route('admin.inbox-show', $submission))
        ->assertSee(route('admin.settings-redirects', ['q' => '/old-team']))
        ->assertSee('photo.jpg')
        ->assertSee('Gone page');
});

it('spells out the details of each kind of change', function (ActivityEvent $event, ?array $properties, string $details): void {
    activityAbout($event, attributes: ['properties' => $properties]);

    expect(Activity::query()->sole()->details())->toBe($details === '' ? null : $details);
})->with([
    'settings' => [ActivityEvent::SETTINGS_UPDATED, ['keys' => ['ai_api_key']], 'Ai Api Key'],
    'role change' => [ActivityEvent::USER_ROLE_CHANGED, ['from' => 'Editor', 'to' => 'Administrator'], 'Editor → Administrator'],
    'role given to someone without one' => [ActivityEvent::USER_ROLE_CHANGED, ['from' => null, 'to' => 'Editor'], 'No role → Editor'],
    'invitation' => [ActivityEvent::USER_INVITED, ['role' => 'Editor'], 'As Editor'],
    'refund' => [ActivityEvent::ORDER_REFUNDED, ['amount' => 1250, 'currency' => 'USD'], '$12.50'],
    'redirect import' => [ActivityEvent::REDIRECTS_IMPORTED, ['added' => 3, 'updated' => 1], '3 added, 1 updated'],
    'failed backup' => [ActivityEvent::BACKUP_FAILED, ['reason' => 'disk full'], 'disk full'],
    'failed backup without a reason' => [ActivityEvent::BACKUP_FAILED, null, ''],
    'sign-in' => [ActivityEvent::SIGNED_IN, null, ''],
]);

it('names people, deleted accounts, the assistant, agents and the system', function (): void {
    $ada = User::factory()->create(['name' => 'Ada Lovelace']);

    $named = activityAbout(ActivityEvent::PAGE_CREATED, attributes: ['user_id' => $ada->id, 'source' => RevisionSource::ASSISTANT]);
    $deleted = activityAbout(ActivityEvent::PAGE_CREATED, attributes: ['user_id' => 999999, 'source' => RevisionSource::EDITOR]);
    $stranger = activityAbout(ActivityEvent::SIGN_IN_FAILED, attributes: ['source' => RevisionSource::EDITOR]);
    $agent = activityAbout(ActivityEvent::PAGE_CREATED, attributes: ['source' => RevisionSource::AGENT]);
    $system = activityAbout(ActivityEvent::BACKUP_CREATED);

    expect([$named->actorName(), $deleted->actorName(), $stranger->actorName(), $agent->actorName(), $system->actorName()])
        ->toBe(['Ada Lovelace', 'Deleted user', 'Someone', 'AI agent', 'System']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-activity')->assertSeeInOrder(['Ada Lovelace', 'AI assistant']);
});

it('filters by area and person and searches items and names', function (): void {
    $this->actingAsAdmin();
    $grace = User::factory()->create(['name' => 'Grace Hopper', 'role' => 'editor']);
    activityAbout(ActivityEvent::PAGE_PUBLISHED, attributes: ['user_id' => $grace->id, 'subject_label' => 'Spring menu']);
    activityAbout(ActivityEvent::SIGNED_IN, attributes: ['user_id' => auth()->id(), 'subject_label' => null]);
    activityAbout(ActivityEvent::BACKUP_CREATED, attributes: ['subject_label' => 'backup-manual.zip']);

    Livewire::test('pages::admin.settings-activity')
        ->assertSee('Grace Hopper')
        ->set('area', 'content')
        ->assertSee('Spring menu')
        ->assertDontSee('backup-manual.zip')
        ->assertSee('Published page')
        ->set('area', '')
        ->set('person', (string) $grace->id)
        ->assertSee('Spring menu')
        ->assertDontSee('backup-manual.zip')
        ->call('clearFilters')
        ->assertSet('area', '')
        ->assertSet('person', '')
        ->set('search', 'grace')
        ->assertSee('Spring menu')
        ->assertDontSee('backup-manual.zip')
        ->set('search', 'manual.zip')
        ->assertSee('backup-manual.zip')
        ->assertDontSee('Spring menu')
        ->set('search', 'nothing like this')
        ->assertSee('No activity matches these filters.');
});

it('says when nothing has been logged yet', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-activity')->assertSee('Nothing yet.');
});

it('shows the log only to people allowed to see it', function (): void {
    $this->actingAs(activityViewer(['settings.view']))
        ->get(route('admin.settings-activity'))
        ->assertForbidden();

    $this->actingAs(activityViewer(['settings.view']))
        ->get(route('admin.settings-general'))
        ->assertDontSee(route('admin.settings-activity'));

    $this->actingAs(activityViewer(['settings.view', 'activity.view']))
        ->get(route('admin.settings-activity'))
        ->assertOk();
});
