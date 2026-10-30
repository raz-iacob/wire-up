<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Models\Activity;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLog;
use App\Services\CartService;
use App\Services\Impersonation;
use Livewire\Livewire;

function impersonatedMember(array $attributes = []): User
{
    return User::factory()->member()->create(['name' => 'Ada Lovelace', 'active' => true, ...$attributes]);
}

/**
 * @return array{0: User, 1: User}
 */
function viewingAsMember(): array
{
    $admin = User::factory()->owner()->create(['name' => 'Fanny Mayer', 'active' => true]);
    $member = impersonatedMember(['remember_token' => 'member-remember-token']);

    test()->actingAs($member);
    session()->put(Impersonation::SESSION_KEY, $admin->id);

    return [$admin, $member];
}

it('logs an admin in as a member from the users list and opens their account', function (): void {
    $this->actingAsAdmin();
    $admin = auth()->user();
    $member = impersonatedMember();

    Livewire::test('pages::admin.users-index')
        ->assertSeeHtml('wire:click="impersonate('.$member->id.')"')
        ->assertDontSeeHtml('wire:click="impersonate('.$admin->id.')"')
        ->call('impersonate', $member->id)
        ->assertRedirect(route('account'));

    $this->assertAuthenticatedAs($member);

    expect(session(Impersonation::SESSION_KEY))->toBe($admin->id)
        ->and(Activity::query()->sole()->only(['event', 'user_id', 'subject_id']))->toBe(['event' => ActivityEvent::IMPERSONATION_STARTED, 'user_id' => $admin->id, 'subject_id' => $member->id]);
});

it('logs in as a member from their edit screen', function (): void {
    $this->actingAsAdmin();
    $member = impersonatedMember();

    Livewire::test('pages::admin.users-edit', ['user' => $member])
        ->assertSee('Log in as Ada Lovelace')
        ->call('impersonate')
        ->assertRedirect(route('account'));

    $this->assertAuthenticatedAs($member);
});

it('only lets people who may edit users log in as an active member', function (Closure $actor, Closure $target, bool $allowed): void {
    expect(Impersonation::canStart($actor(), $target()))->toBe($allowed);
})->with([
    'an owner, as a member' => [fn (): User => User::factory()->owner()->create(), fn (): User => impersonatedMember(), true],
    'nobody signed in' => [fn (): ?User => null, fn (): User => impersonatedMember(), false],
    'someone who may only view users' => [fn (): User => User::factory()->role(Role::query()->create(['key' => 'viewer', 'name' => 'Viewer', 'abilities' => ['users.view'], 'bypass' => false, 'is_protected' => false]))->create(), fn (): User => impersonatedMember(), false],
    'as a disabled member' => [fn (): User => User::factory()->owner()->create(), fn (): User => impersonatedMember(['active' => false]), false],
    'as staff' => [fn (): User => User::factory()->owner()->create(), fn (): User => User::factory()->admin()->create(['active' => true]), false],
]);

it('refuses to log in as someone who is not eligible, or while already viewing as someone', function (): void {
    $this->actingAsAdmin();
    $editor = User::factory()->admin()->create(['active' => true]);

    Livewire::test('pages::admin.users-edit', ['user' => $editor])
        ->assertDontSee('Log in as')
        ->call('impersonate')
        ->assertForbidden();

    session()->put(Impersonation::SESSION_KEY, auth()->id());

    expect(Impersonation::canStart(auth()->user(), impersonatedMember()))->toBeFalse();
});

it('shows a banner with a way back on every site page while viewing as a member', function (): void {
    viewingAsMember();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Viewing the site as Ada Lovelace.')
        ->assertSee(route('impersonation.stop'));

    $this->get(route('account'))->assertOk()->assertSee('Back to the admin');
});

it('shows no banner to a member who signed in themselves', function (): void {
    $this->actingAs(impersonatedMember())
        ->get(route('account'))
        ->assertOk()
        ->assertDontSee('Viewing the site as');
});

it('takes the admin back to the member’s profile without touching the member’s own sign-ins', function (): void {
    [$admin, $member] = viewingAsMember();

    $this->post(route('impersonation.stop'))->assertRedirect(route('admin.users-edit', $member));

    $this->assertAuthenticatedAs($admin);

    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse()
        ->and($member->refresh()->remember_token)->toBe('member-remember-token')
        ->and(Activity::query()->sole()->only(['event', 'user_id', 'subject_id', 'impersonator_id']))->toBe(['event' => ActivityEvent::IMPERSONATION_STOPPED, 'user_id' => $admin->id, 'subject_id' => $member->id, 'impersonator_id' => null]);
});

it('signs everyone out when the admin can no longer reach the admin', function (): void {
    [$admin] = viewingAsMember();
    $admin->update(['active' => false]);

    $this->post(route('impersonation.stop'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('sends a member who was not being viewed back to their account', function (): void {
    $this->actingAs(impersonatedMember())
        ->post(route('impersonation.stop'))
        ->assertRedirect(route('account'));
});

it('turns signing out into going back to the admin', function (): void {
    [$admin, $member] = viewingAsMember();

    $this->post(route('logout'))->assertRedirect(route('admin.users-edit', $member));
    $this->assertAuthenticatedAs($admin);

    [, $member] = viewingAsMember();

    Livewire::test('pages::account')->call('logout')->assertRedirect(route('admin.users-edit', $member));
});

it('refuses account changes, billing and checkout while viewing as a member', function (): void {
    [, $member] = viewingAsMember();
    $record = sellableRecord();
    resolve(CartService::class)->add($record, 1);

    Livewire::test('pages::account')
        ->set('name', 'Someone else')
        ->call('updateProfile')
        ->assertDispatched('toast-show', toastSays('This is not available while you view the site as someone else.'))
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->call('manageBilling')
        ->call('delete')
        ->assertHasNoErrors();

    Livewire::test('pages::cart')
        ->call('checkout')
        ->assertDispatched('toast-show', toastSays('This is not available while you view the site as someone else.'))
        ->assertNoRedirect();

    Livewire::test('site.buy-box', ['record' => $record])
        ->call('subscribe')
        ->assertDispatched('toast-show', toastSays('This is not available while you view the site as someone else.'))
        ->assertNoRedirect();

    expect($member->refresh()->name)->toBe('Ada Lovelace')
        ->and(User::query()->whereKey($member->id)->exists())->toBeTrue();

    stripe()->assertNothingSent();
});

it('leaves the member’s last seen time alone and logs no sign-in while viewing as them', function (): void {
    [, $member] = viewingAsMember();
    $member->update(['last_seen_at' => null]);

    $this->get(route('account'))->assertOk();

    expect($member->refresh()->last_seen_at)->toBeNull()
        ->and(Activity::query()->exists())->toBeFalse();
});

it('credits what happens while viewing as a member to the admin', function (): void {
    [$admin, $member] = viewingAsMember();

    ActivityLog::record(ActivityEvent::ORDER_CANCELLED, 'WU1234');
    $activity = Activity::query()->sole();

    expect($activity->only(['user_id', 'impersonator_id']))->toBe(['user_id' => $member->id, 'impersonator_id' => $admin->id])
        ->and($activity->actorName())->toBe('Fanny Mayer')
        ->and($activity->actingAs())->toBe('Ada Lovelace');

    session()->forget(Impersonation::SESSION_KEY);
    $this->actingAs($admin);

    Livewire::test('pages::admin.settings-activity')
        ->assertSeeInOrder(['Fanny Mayer', 'as Ada Lovelace'])
        ->set('person', (string) $admin->id)
        ->assertSee('WU1234');

    $member->delete();
    $admin->delete();

    expect($activity->fresh()->actorName())->toBe('Deleted user')
        ->and($activity->fresh()->actingAs())->toBe('Deleted user');
});
