<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Submission;
use App\Models\User;
use Livewire\Livewire;

it('can render the dashboard screen', function (): void {
    $response = $this->actingAsAdmin()
        ->fromRoute('home')
        ->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.dashboard');
});

it('shows a media gallery launcher in the sidebar', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Media')
        ->assertSee("Livewire.dispatch('select-media', { target: 'media-gallery'", false);
});

it('redirects authenticated non-admin users away from dashboard', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'role' => 'member',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.dashboard'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from dashboard', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.dashboard'));

    $response->assertRedirectToRoute('login');
});

it('shows content breakdown and recent activity', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'product', 'slug_prefix' => 'products', 'name' => 'Products', 'fields' => [],
    ]);
    Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Live Widget'],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.dashboard')
        ->assertOk()
        ->assertSee('Products')
        ->assertSee('Live Widget')
        ->assertSee('Published items');
});

it('shows unread message count and latest messages', function (): void {
    Submission::factory()->create(['name' => 'Ada Lovelace', 'read_at' => null]);
    Submission::factory()->create(['name' => 'Alan Turing', 'read_at' => now()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Ada Lovelace')
        ->assertSee('Unread messages');
});

it('lists users who are currently online', function (): void {
    User::factory()->create(['name' => 'Recently Active', 'last_seen_at' => now()->subMinutes(2)]);
    User::factory()->create(['name' => 'Long Gone', 'last_seen_at' => now()->subDays(3)]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Recently Active')
        ->assertDontSee('Long Gone');
});

it('shows who edited an item in recent activity when multiple staff exist', function (): void {
    $type = RecordType::factory()->create(['key' => 'product', 'slug_prefix' => 'products', 'fields' => []]);
    $editor = User::factory()->create(['name' => 'Grace Hopper', 'role' => 'owner', 'active' => true]);
    User::factory()->create(['name' => 'View Admin', 'role' => 'owner', 'active' => true]);

    $this->actingAs($editor);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Team Item']]);

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Team Item')
        ->assertSee('by Grace Hopper');
});

it('hides the editor in recent activity when only one staff user exists', function (): void {
    $type = RecordType::factory()->create(['key' => 'product', 'slug_prefix' => 'products', 'fields' => []]);
    $admin = User::factory()->create(['name' => 'Solo Admin', 'role' => 'owner', 'active' => true]);

    $this->actingAs($admin);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Solo Item']]);

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Solo Item')
        ->assertDontSee('by Solo Admin');
});
