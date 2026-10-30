<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\RevisionSource;
use App\Models\Activity;
use App\Models\Page;
use App\Models\Revision;
use App\Models\Role;
use App\Models\User;
use App\Services\UserGuard;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function developerAccount(): User
{
    return User::factory()->developer()->create(['name' => 'Razvan Dev', 'email' => 'dev@example.com', 'active' => true, 'last_seen_at' => now()]);
}

function ownerAccount(): User
{
    return User::factory()->owner()->create(['name' => 'Olivia Owner', 'active' => true]);
}

it('lets only developers through the developer-only gate', function (): void {
    $developer = developerAccount();

    expect(Gate::forUser($developer)->allows(Role::DEVELOPER))->toBeTrue()
        ->and(Gate::forUser($developer)->allows('settings.edit'))->toBeTrue()
        ->and(Gate::forUser(ownerAccount())->allows(Role::DEVELOPER))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows(Role::DEVELOPER))->toBeFalse();
});

it('keeps a developer out of an owner’s reach', function (): void {
    $developer = developerAccount();
    $owner = ownerAccount();
    $guard = resolve(UserGuard::class);

    expect($guard->canManage($owner, $developer))->toBeFalse()
        ->and($guard->canManage($developer, $developer))->toBeTrue()
        ->and($guard->canManage($developer, $owner))->toBeTrue();

    $this->actingAs($owner)->get(route('admin.users-edit', $developer))->assertForbidden();
    $this->actingAs($developer)->get(route('admin.users-edit', $owner))->assertOk();
});

it('never offers the developer role in the admin, but lets a developer keep it when saving', function (): void {
    $developer = developerAccount();
    $owner = ownerAccount();
    $developerRole = Role::query()->where('key', Role::DEVELOPER)->firstOrFail();

    $this->actingAs($developer);

    Livewire::test('pages::admin.users-index')
        ->assertDontSeeHtml('value="'.$developerRole->id.'"');

    Livewire::test('pages::admin.users-edit', ['user' => $owner])
        ->assertDontSeeHtml('value="'.$developerRole->id.'"')
        ->set('roleId', $developerRole->id)
        ->call('update')
        ->assertHasErrors('roleId');

    Livewire::test('pages::admin.users-edit', ['user' => $developer])
        ->assertSee('Developer')
        ->set('name', 'Razvan Iacob')
        ->call('update')
        ->assertHasNoErrors();

    expect($owner->refresh()->isDeveloper())->toBeFalse()
        ->and($developer->refresh()->only(['name', 'role_id']))->toBe(['name' => 'Razvan Iacob', 'role_id' => $developerRole->id]);
});

it('hides developers from owners in the users list, online users and the roles screen', function (): void {
    developerAccount();
    $this->actingAs(ownerAccount());

    Livewire::test('pages::admin.users-index')->assertSee('Olivia Owner')->assertDontSee('Razvan Dev')->assertDontSee('dev@example.com');
    Livewire::test('pages::admin.dashboard')->assertSee('Olivia Owner')->assertDontSee('Razvan Dev');
    Livewire::test('pages::admin.settings-roles')->assertSee('Owner')->assertDontSee('Developer');
});

it('shows developers to each other', function (): void {
    $this->actingAs(developerAccount());

    Livewire::test('pages::admin.users-index')->assertSee('Razvan Dev');
    Livewire::test('pages::admin.dashboard')->assertSee('Razvan Dev');
});

it('names a developer only as Developer in the activity log and page history for everyone else', function (): void {
    $developer = developerAccount();
    $owner = ownerAccount();
    Activity::factory()->create(['event' => ActivityEvent::SIGNED_IN, 'user_id' => $developer->id, 'source' => RevisionSource::EDITOR]);
    $revision = Revision::factory()->for(Page::factory(), 'revisionable')->create(['user_id' => $developer->id]);

    $this->actingAs($owner);

    expect($revision->load('user.role')->authorName())->toBe('Developer');

    Livewire::test('pages::admin.settings-activity')
        ->assertSee('Developer')
        ->assertDontSee('Razvan Dev')
        ->set('search', 'Razvan')
        ->assertSee('No activity matches these filters.');

    $this->actingAs($developer);

    expect($revision->fresh()->load('user.role')->authorName())->toBe('Razvan Dev');

    Livewire::test('pages::admin.settings-activity')->assertSee('Razvan Dev');
});
