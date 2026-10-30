<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Actions\CreateMediaAction;
use App\Actions\CreatePageAction;
use App\Actions\CreateRecordAction;
use App\Actions\CreateRecordTypeAction;
use App\Actions\DeleteCategoryAction;
use App\Actions\DeletePageAction;
use App\Actions\DeleteRecordAction;
use App\Actions\DeleteRecordTypeAction;
use App\Actions\DeleteSubmissionAction;
use App\Actions\DeleteUserAction;
use App\Actions\DuplicatePageAction;
use App\Actions\DuplicateRecordAction;
use App\Actions\ImportRedirectsAction;
use App\Actions\InviteAdminAction;
use App\Actions\SetMediaPrivacyAction;
use App\Actions\UpdateCategoryAction;
use App\Actions\UpdatePageAction;
use App\Actions\UpdateRecordAction;
use App\Actions\UpdateRecordTypeAction;
use App\Actions\UpdateSettingsAction;
use App\Actions\UpdateUserAction;
use App\Enums\ActivityEvent;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Enums\RedirectOrigin;
use App\Enums\RevisionSource;
use App\Jobs\RunBackup;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\Slug;
use App\Models\Submission;
use App\Models\User;
use App\Services\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('records who did what to which item, and from where', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $page = Page::factory()->create(['title' => ['en' => 'About us']]);

    $this->actingAs($user);
    ActivityLog::record(ActivityEvent::PAGE_UPDATED, $page, ['note' => 'kept']);

    expect(Activity::query()->sole()->only(['user_id', 'source', 'event', 'subject_type', 'subject_id', 'subject_label', 'properties', 'ip']))->toBe([
        'user_id' => $user->id,
        'source' => RevisionSource::EDITOR,
        'event' => ActivityEvent::PAGE_UPDATED,
        'subject_type' => 'page',
        'subject_id' => $page->id,
        'subject_label' => 'About us',
        'properties' => ['note' => 'kept'],
        'ip' => '127.0.0.1',
    ]);
});

it('never lets a failed log write stop the change it records', function (): void {
    Exceptions::fake();
    Schema::drop('activities');

    ActivityLog::record(ActivityEvent::BACKUP_CREATED, 'backup.zip');

    Exceptions::assertReported(QueryException::class);
});

it('tells the system, the assistant and outside agents apart from people', function (): void {
    ActivityLog::record(ActivityEvent::BACKUP_CREATED, 'backup.zip');

    Context::add(RevisionSource::CONTEXT_KEY, RevisionSource::AGENT->value);
    ActivityLog::record(ActivityEvent::PAGE_CREATED);

    $this->actingAs(User::factory()->create());
    Context::add(RevisionSource::CONTEXT_KEY, RevisionSource::ASSISTANT->value);
    ActivityLog::record(ActivityEvent::PAGE_CREATED);

    expect(Activity::query()->orderBy('id')->pluck('source')->all())->toBe([RevisionSource::SYSTEM, RevisionSource::AGENT, RevisionSource::ASSISTANT])
        ->and(Activity::query()->orderBy('id')->first()->only(['user_id', 'subject_type', 'subject_label', 'properties']))->toBe(['user_id' => null, 'subject_type' => null, 'subject_label' => 'backup.zip', 'properties' => null]);
});

it('keeps a readable name for each kind of item', function (Closure $subject, string $label): void {
    ActivityLog::record(ActivityEvent::SETTINGS_UPDATED, $subject());

    expect(Activity::query()->sole()->subject_label)->toBe($label);
})->with([
    'page' => [fn (): Model => Page::factory()->create(['title' => ['en' => 'Home sweet home']]), 'Home sweet home'],
    'record' => [fn (): Model => Record::factory()->create(['title' => ['en' => 'Blue mug']]), 'Blue mug'],
    'file' => [fn (): Model => Media::factory()->create(['filename' => 'logo.png']), 'logo.png'],
    'user' => [fn (): Model => User::factory()->create(['name' => 'Grace Hopper']), 'Grace Hopper'],
    'role' => [fn (): Model => Role::query()->where('key', 'editor')->firstOrFail(), 'Editor'],
    'content type' => [fn (): Model => RecordType::factory()->create(['name' => 'Recipes']), 'Recipes'],
    'category' => [fn (): Model => Category::factory()->create(['name' => ['en' => 'News']]), 'News'],
    'order' => [fn (): Model => Order::factory()->create(['reference' => 'WU1234']), 'WU1234'],
    'message from a named sender' => [fn (): Model => Submission::factory()->create(['name' => 'Alan Turing']), 'Alan Turing'],
    'message with only a form name' => [fn (): Model => Submission::factory()->create(['name' => null, 'email' => null, 'form_name' => 'Newsletter']), 'Newsletter'],
    'redirect' => [fn (): Model => Redirect::factory()->create(['from' => '/old-team']), '/old-team'],
]);

it('falls back to the id for any other kind of item', function (): void {
    Relation::morphMap(['slug' => Slug::class]);
    $slug = Page::factory()->create()->slugs()->create(['locale' => 'en', 'slug' => 'somewhere']);

    ActivityLog::record(ActivityEvent::SETTINGS_UPDATED, $slug);
    Relation::morphMap((array) config('models.map'), merge: false);

    expect(Activity::query()->sole()->subject_label)->toBe((string) $slug->id);
});

it('logs creating, duplicating and deleting pages and records', function (): void {
    $type = RecordType::factory()->create();

    $page = new CreatePageAction()->handle(['title' => ['en' => 'Team']]);
    $copy = new DuplicatePageAction()->handle($page);
    new DeletePageAction()->handle($copy);
    $record = new CreateRecordAction()->handle($type, ['title' => ['en' => 'Blue mug']]);
    new DeleteRecordAction()->handle(new DuplicateRecordAction()->handle($record));

    expect(loggedEvents())->toBe([
        ActivityEvent::PAGE_CREATED,
        ActivityEvent::PAGE_DUPLICATED,
        ActivityEvent::PAGE_DELETED,
        ActivityEvent::RECORD_CREATED,
        ActivityEvent::RECORD_DUPLICATED,
        ActivityEvent::RECORD_DELETED,
    ])->and(Activity::query()->where('event', ActivityEvent::PAGE_DELETED)->sole()->subject_label)->toBe('Copy of Team');
});

it('names what a save did to a page: published, scheduled, unpublished or just edited', function (): void {
    $page = new CreatePageAction()->handle(['title' => ['en' => 'Team']]);
    Activity::query()->delete();
    $save = fn (array $attributes) => new UpdatePageAction()->handle($page, $attributes);

    $save(['status' => ContentStatus::PUBLISHED]);
    $save(['status' => ContentStatus::PUBLISHED]);
    $save(['status' => ContentStatus::PUBLISHED, 'title' => ['en' => 'Our team']]);
    $save(['status' => ContentStatus::SCHEDULED, 'published_at' => now()->addWeek()]);
    $save(['status' => ContentStatus::DRAFT]);
    $save(['status' => ContentStatus::PRIVATE]);

    expect(loggedEvents())->toBe([
        ActivityEvent::PAGE_PUBLISHED,
        ActivityEvent::PAGE_UPDATED,
        ActivityEvent::PAGE_SCHEDULED,
        ActivityEvent::PAGE_UNPUBLISHED,
        ActivityEvent::PAGE_UPDATED,
    ]);
});

it('logs publishing a record', function (): void {
    $record = Record::factory()->create(['status' => ContentStatus::DRAFT]);

    new UpdateRecordAction()->handle($record, ['status' => ContentStatus::PUBLISHED]);

    expect(loggedEvents())->toBe([ActivityEvent::RECORD_PUBLISHED]);
});

it('logs changes to content types and categories, but not saves that change nothing', function (): void {
    $type = new CreateRecordTypeAction()->handle(['key' => 'recipe', 'name' => 'Recipes', 'slug_prefix' => 'recipes', 'icon' => 'cake', 'fields' => []]);
    new UpdateRecordTypeAction()->handle($type, ['name' => 'Dishes']);
    new UpdateRecordTypeAction()->handle($type, ['name' => 'Dishes']);
    new DeleteRecordTypeAction()->handle($type);

    $category = new CreateCategoryAction()->handle(['name' => ['en' => 'News']]);
    new UpdateCategoryAction()->handle($category, ['name' => ['en' => 'Updates']]);
    new UpdateCategoryAction()->handle($category, ['name' => ['en' => 'Updates']]);
    new DeleteCategoryAction()->handle($category);

    expect(loggedEvents())->toBe([
        ActivityEvent::RECORD_TYPE_CREATED,
        ActivityEvent::RECORD_TYPE_UPDATED,
        ActivityEvent::RECORD_TYPE_DELETED,
        ActivityEvent::CATEGORY_CREATED,
        ActivityEvent::CATEGORY_UPDATED,
        ActivityEvent::CATEGORY_DELETED,
    ]);
});

it('logs uploading, hiding, sharing and deleting files', function (): void {
    Storage::fake(config()->string('filesystems.media'));
    Storage::fake(config()->string('filesystems.private_media'));

    $document = new CreateMediaAction()->handle(Media::factory()->raw(['type' => MediaType::DOCUMENT, 'filename' => 'menu.pdf']));
    new SetMediaPrivacyAction()->handle($document, true);
    new SetMediaPrivacyAction()->handle($document, true);
    new SetMediaPrivacyAction()->handle($document, false);
    $document->delete();

    expect(loggedEvents())->toBe([
        ActivityEvent::MEDIA_UPLOADED,
        ActivityEvent::MEDIA_MADE_PRIVATE,
        ActivityEvent::MEDIA_MADE_PUBLIC,
        ActivityEvent::MEDIA_DELETED,
    ])->and(Activity::query()->pluck('subject_label')->unique()->all())->toBe(['menu.pdf']);
});

it('logs which settings changed, never their values', function (): void {
    new UpdateSettingsAction()->handle(['site_name' => 'Wire-Up', 'mail_password' => 'hunter2-secret']);
    new UpdateSettingsAction()->handle(['site_name' => 'Wire-Up', 'mail_password' => 'hunter2-secret']);

    $activity = Activity::query()->sole();

    expect($activity->event)->toBe(ActivityEvent::SETTINGS_UPDATED)
        ->and($activity->properties)->toBe(['keys' => ['site_name', 'mail_password']])
        ->and(json_encode($activity->getAttributes()))->not->toContain('hunter2');
});

it('logs invitations, role changes, disabled and enabled accounts and deleted accounts', function (): void {
    Mail::fake();
    $this->actingAsAdmin();
    $editor = Role::query()->where('key', 'editor')->firstOrFail();
    $admin = Role::query()->where('key', 'admin')->firstOrFail();

    $invited = resolve(InviteAdminAction::class)->handle(auth()->user(), 'Grace Hopper', 'grace@example.com', $editor)['user'];
    new UpdateUserAction()->handle($invited, ['role_id' => $admin->id]);
    new UpdateUserAction()->handle($invited, ['active' => false]);
    new UpdateUserAction()->handle($invited, ['active' => true]);
    new UpdateUserAction()->handle($invited, ['name' => 'Grace B. Hopper']);
    new DeleteUserAction()->handle($invited);

    expect(loggedEvents())->toBe([
        ActivityEvent::USER_INVITED,
        ActivityEvent::USER_ROLE_CHANGED,
        ActivityEvent::USER_DISABLED,
        ActivityEvent::USER_ENABLED,
        ActivityEvent::USER_DELETED,
    ])->and(Activity::query()->where('event', ActivityEvent::USER_INVITED)->sole()->properties)->toBe(['role' => 'Editor'])
        ->and(Activity::query()->where('event', ActivityEvent::USER_ROLE_CHANGED)->sole()->properties)->toBe(['from' => 'Editor', 'to' => 'Administrator']);
});

it('logs roles added, changed and removed in the roles screen', function (): void {
    $this->actingAsAdmin();
    Role::query()->create(['key' => 'temp', 'name' => 'Temp', 'abilities' => ['pages.view'], 'bypass' => false, 'is_protected' => false]);
    Livewire::test('pages::admin.settings-roles')->call('save');
    Activity::query()->delete();

    $component = Livewire::test('pages::admin.settings-roles')->call('addRole');
    $roles = collect($component->get('roles'));

    $component
        ->set('roles.'.($roles->count() - 1).'.name', 'Support')
        ->set('roles.'.$roles->search(fn (array $role): bool => $role['key'] === 'author').'.name', 'Writer')
        ->call('confirmRemove', $roles->firstWhere('key', 'temp')['_key'])
        ->call('removeRole')
        ->call('save')
        ->assertHasNoErrors();

    expect(Activity::query()->orderBy('id')->get()->map(fn (Activity $activity): array => [$activity->event, $activity->subject_label])->all())->toBe([
        [ActivityEvent::ROLE_DELETED, 'Temp'],
        [ActivityEvent::ROLE_UPDATED, 'Writer'],
        [ActivityEvent::ROLE_CREATED, 'Support'],
    ]);
});

it('logs redirects saved, imported and deleted', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->call('add')
        ->set('from', '/old')
        ->set('to', '/new')
        ->call('save');
    new ImportRedirectsAction()->handle([2 => ['from' => '/a', 'to' => '/b', 'status' => null], 3 => ['from' => '/admin', 'to' => '/c', 'status' => null]], RedirectOrigin::IMPORT);
    new ImportRedirectsAction()->handle([2 => ['from' => '/admin', 'to' => '/c', 'status' => null]], RedirectOrigin::IMPORT);
    Redirect::query()->where('from', '/old')->sole()->delete();

    expect(loggedEvents())->toBe([ActivityEvent::REDIRECT_SAVED, ActivityEvent::REDIRECTS_IMPORTED, ActivityEvent::REDIRECT_DELETED])
        ->and(Activity::query()->where('event', ActivityEvent::REDIRECTS_IMPORTED)->sole()->properties)->toBe(['added' => 1, 'updated' => 0]);
});

it('logs deleted messages', function (): void {
    new DeleteSubmissionAction()->handle(Submission::factory()->create(['name' => 'Alan Turing']));

    expect(Activity::query()->sole()->only(['event', 'subject_label']))->toBe(['event' => ActivityEvent::SUBMISSION_DELETED, 'subject_label' => 'Alan Turing']);
});

it('logs signing in, signing out and failed attempts', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => bcrypt('secret'), 'active' => true]);

    Livewire::test('pages::auth.login')->set('email', 'ada@example.com')->set('password', 'wrong')->call('login');
    Livewire::test('pages::auth.login')->set('email', 'nobody@example.com')->set('password', 'wrong')->call('login');
    Livewire::test('pages::auth.login')->set('email', 'ada@example.com')->set('password', 'secret')->call('login');
    $this->post(route('logout'));

    expect(Activity::query()->orderBy('id')->get()->map(fn (Activity $activity): array => [$activity->event, $activity->user_id, $activity->subject_label])->all())->toBe([
        [ActivityEvent::SIGN_IN_FAILED, $user->id, 'ada@example.com'],
        [ActivityEvent::SIGN_IN_FAILED, null, 'nobody@example.com'],
        [ActivityEvent::SIGNED_IN, $user->id, null],
        [ActivityEvent::SIGNED_OUT, $user->id, null],
    ]);
});

it('logs a lockout once, when the last allowed attempt fails', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'password' => bcrypt('secret'), 'active' => true]);

    foreach (range(1, 6) as $attempt) {
        Livewire::test('pages::auth.login')->set('email', 'ada@example.com')->set('password', 'wrong')->call('login');
    }

    expect(loggedEvents())->toBe([...array_fill(0, 4, ActivityEvent::SIGN_IN_FAILED), ActivityEvent::SIGN_IN_FAILED, ActivityEvent::LOCKED_OUT])
        ->and(Activity::query()->where('event', ActivityEvent::LOCKED_OUT)->sole()->subject_label)->toBe('ada@example.com');
});

it('logs a queued backup that fails for whoever asked for it', function (): void {
    new RunBackup(7)->failed(new RuntimeException('worker died'));

    expect(Activity::query()->sole()->only(['event', 'user_id', 'properties']))->toBe(['event' => ActivityEvent::BACKUP_FAILED, 'user_id' => 7, 'properties' => ['reason' => 'worker died']]);
});

it('prunes entries older than half a year', function (): void {
    $old = Activity::factory()->create(['created_at' => now()->subDays(Activity::DAYS_KEPT + 1)]);
    $recent = Activity::factory()->create(['created_at' => now()->subDays(Activity::DAYS_KEPT - 1)]);

    $this->artisan('model:prune', ['--model' => [Activity::class]])->assertSuccessful();

    expect(Activity::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(Activity::query()->whereKey($old->id)->exists())->toBeFalse();
});
