<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\RecordType;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('lets members reach nothing in the admin panel', function (): void {
    $member = User::factory()->member()->create(['active' => true]);

    $this->actingAs($member)
        ->fromRoute('home')
        ->get(route('admin.dashboard'))
        ->assertRedirectToRoute('home');
});

it('gives every admin role access to the dashboard', function (string $role): void {
    RecordType::factory()->create();
    $user = User::factory()->{$role}()->create(['active' => true]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();
})->with(['owner', 'admin', 'editor', 'author']);

it('grants editors content areas but forbids users and settings', function (): void {
    $editor = User::factory()->editor()->create(['active' => true]);

    $this->actingAs($editor);

    $this->get(route('admin.pages-index'))->assertOk();
    $this->get(route('admin.categories-index'))->assertOk();
    $this->get(route('admin.inbox-index'))->assertOk();

    $this->get(route('admin.users-index'))->assertForbidden();
    $this->get(route('admin.settings-general'))->assertForbidden();
});

it('restricts authors to records only', function (): void {
    $recordType = RecordType::factory()->create();
    $author = User::factory()->author()->create(['active' => true]);

    $this->actingAs($author);

    $this->get(route('admin.records-index', $recordType))->assertOk();
    $this->get(route('admin.pages-index'))->assertForbidden();
    $this->get(route('admin.categories-index'))->assertForbidden();
});

it('lets a role manage only its granted record types', function (): void {
    $allowed = RecordType::factory()->create(['key' => 'product', 'slug_prefix' => 'products']);
    $forbidden = RecordType::factory()->create(['key' => 'event', 'slug_prefix' => 'events']);

    $user = User::factory()
        ->for(Role::factory()->state(['abilities' => ['records.product.view']]))
        ->create(['active' => true]);

    $this->actingAs($user);

    $this->get(route('admin.records-index', $allowed))->assertOk();
    $this->get(route('admin.records-index', $forbidden))->assertForbidden();
});

it('lets owners bypass every ability gate', function (): void {
    RecordType::factory()->create();
    $owner = User::factory()->owner()->create();

    foreach (PermissionRegistry::abilityKeys() as $ability) {
        expect(Gate::forUser($owner)->allows($ability))->toBeTrue();
    }
});

it('stops a view-only role from creating or editing pages', function (): void {
    $role = Role::factory()->create(['abilities' => ['pages.view'], 'bypass' => false]);
    $user = User::factory()->for($role)->create(['active' => true]);
    $page = Page::factory()->create();

    $this->actingAs($user);

    $this->get(route('admin.pages-index'))->assertOk();
    $this->get(route('admin.pages-edit', $page))->assertForbidden();

    Livewire::test('pages::admin.pages-index')->call('create')->assertForbidden();
});

it('stops an author from deleting a record', function (): void {
    $type = RecordType::factory()->create(['key' => 'product', 'slug_prefix' => 'products']);
    $author = User::factory()->author()->create(['active' => true]);

    $this->actingAs($author);

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('title', 'A new product')
        ->call('create')
        ->assertHasNoErrors();

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->call('delete', 999)
        ->assertForbidden();
});

it('resolves gates from the assigned role abilities', function (): void {
    $editor = User::factory()->editor()->create();

    expect(Gate::forUser($editor)->allows('pages.view'))->toBeTrue();
    expect(Gate::forUser($editor)->allows('users.edit'))->toBeFalse();
});

it('does not resolve non-permission abilities for a non-bypass role', function (): void {
    $member = User::factory()->member()->create();

    expect(Gate::forUser($member)->allows('some-non-permission-gate'))->toBeFalse();
});
