<?php

declare(strict_types=1);

use App\Jobs\RunSystemUpdate;
use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('wireup.version_file', storage_path('framework/testing/wireup-'.Str::random(8).'/version'));
});

afterEach(function (): void {
    File::deleteDirectory(dirname((string) config('wireup.version_file')));
});

function seedAvailableUpdate(): void
{
    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    Cache::forever('wireup:update:latest', [
        'version' => 'v1.0.0',
        'checked_at' => now()->toIso8601String(),
        'notes' => [['version' => 'v1.0.0', 'notes' => ['Added dark mode']]],
    ]);
}

function settingsViewer(): User
{
    $role = Role::query()->create(['key' => 'viewer', 'name' => 'Viewer', 'abilities' => ['settings.view'], 'bypass' => false, 'is_protected' => false]);

    return User::factory()->role($role)->create(['active' => true]);
}

it('can render the updates screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-updates'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-updates');
});

it('redirects guests away from the updates screen', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-updates'))
        ->assertRedirectToRoute('login');
});

it('redirects non-admin users away from the updates screen', function (): void {
    $user = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.settings-updates'))
        ->assertRedirectToRoute('home');
});

it('shows the versions and the pending release notes', function (): void {
    $this->actingAsAdmin();
    seedAvailableUpdate();

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('v0.9.0')
        ->assertSee('v1.0.0')
        ->assertSee('Update available')
        ->assertSee('Added dark mode')
        ->assertSee('Update now');
});

it('shows a fallback when a pending release has no notes', function (): void {
    $this->actingAsAdmin();
    seedAvailableUpdate();
    Cache::forever('wireup:update:latest', ['version' => 'v1.0.0', 'checked_at' => now()->toIso8601String(), 'notes' => []]);

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('No release notes.');
});

it('hides the update action from users without the edit permission', function (): void {
    $this->actingAs(settingsViewer());
    seedAvailableUpdate();

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('Update available')
        ->assertDontSee('Update now');
});

it('checks for updates on demand', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: "## v1.0.0\n\n- Fresh notes"),
    ]);

    $this->actingAsAdmin();
    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    Livewire::test('pages::admin.settings-updates')
        ->call('checkNow')
        ->assertDispatched('updates-checked');

    expect(resolve(UpdateService::class)->latestVersion())->toBe('v1.0.0');
});

it('forbids checking for updates without the edit permission', function (): void {
    $this->actingAs(settingsViewer());

    Livewire::test('pages::admin.settings-updates')
        ->call('checkNow')
        ->assertForbidden();
});

it('queues the update from the admin', function (): void {
    Queue::fake();
    $this->actingAsAdmin();
    seedAvailableUpdate();

    Livewire::test('pages::admin.settings-updates')
        ->call('startUpdate');

    Queue::assertPushed(RunSystemUpdate::class, fn (RunSystemUpdate $job): bool => $job->tag === 'v1.0.0');

    expect(resolve(UpdateService::class)->state()['status'])->toBe('pending');
});

it('does not queue an update when none is available', function (): void {
    Queue::fake();
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-updates')
        ->call('startUpdate');

    Queue::assertNothingPushed();
});

it('does not queue an update while one is already underway', function (): void {
    Queue::fake();
    $this->actingAsAdmin();
    seedAvailableUpdate();
    resolve(UpdateService::class)->markPending('v1.0.0');

    Livewire::test('pages::admin.settings-updates')
        ->call('startUpdate');

    Queue::assertNothingPushed();
});

it('forbids starting an update without the edit permission', function (): void {
    $this->actingAs(settingsViewer());
    seedAvailableUpdate();

    Livewire::test('pages::admin.settings-updates')
        ->call('startUpdate')
        ->assertForbidden();
});

it('saves the automatic updates setting after confirmation', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-updates')
        ->set('auto_update', true)
        ->call('confirmAutoUpdate')
        ->assertHasNoErrors();

    expect(Settings::get('auto_update'))->toBeTrue();
});

it('reverts the automatic updates switch when the confirmation is cancelled', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-updates')
        ->set('auto_update', true)
        ->call('cancelAutoUpdate')
        ->assertSet('auto_update', false);

    expect(Settings::get('auto_update'))->toBeNull();
});

it('forbids saving the automatic updates setting without the edit permission', function (): void {
    $this->actingAs(settingsViewer());

    Livewire::test('pages::admin.settings-updates')
        ->call('confirmAutoUpdate')
        ->assertForbidden();
});

it('forbids toggling the automatic updates switch without the edit permission', function (): void {
    $this->actingAs(settingsViewer());

    Livewire::test('pages::admin.settings-updates')
        ->set('auto_update', true)
        ->assertForbidden();
});

it('shows progress while an update is underway', function (): void {
    $this->actingAsAdmin();
    seedAvailableUpdate();
    resolve(UpdateService::class)->markPending('v1.0.0');

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('Updating to v1.0.0')
        ->assertSee('Waiting for the queue worker to start the update')
        ->assertSeeHtml('wire:poll.3s');
});

it('shows the running step while an update is underway', function (): void {
    $this->actingAsAdmin();
    seedAvailableUpdate();
    resolve(UpdateService::class)->markRunning('v1.0.0', 'Run database migrations');

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('Run database migrations')
        ->assertSeeHtml('wire:poll.3s');
});

it('shows the outcome of a finished update', function (): void {
    $this->actingAsAdmin();
    resolve(UpdateService::class)->markFinished('v1.0.0');

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('Updated to v1.0.0.')
        ->assertSee('Dismiss');
});

it('shows the failure details of a failed update', function (): void {
    $this->actingAsAdmin();
    resolve(UpdateService::class)->markFailed('v1.0.0', 'Run database migrations', 'migration exploded');

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('Update to v1.0.0 failed at: Run database migrations')
        ->assertSee('migration exploded')
        ->assertSee('php artisan up');
});

it('warns when an update stalls', function (): void {
    $this->actingAsAdmin();
    seedAvailableUpdate();
    resolve(UpdateService::class)->markPending('v1.0.0');

    $this->travel(11)->minutes();

    Livewire::test('pages::admin.settings-updates')
        ->assertSee('queue worker is running');
});

it('dismisses a finished update state', function (): void {
    $this->actingAsAdmin();
    resolve(UpdateService::class)->markFinished('v1.0.0');

    Livewire::test('pages::admin.settings-updates')
        ->call('dismissState')
        ->assertDispatched('updates-checked');

    expect(resolve(UpdateService::class)->state()['status'])->toBe('idle');
});

it('shows an update badge on the settings sidebar item when a release is available', function (): void {
    $this->actingAsAdmin();

    $without = Livewire::test('admin.sidebar-settings')->html();

    seedAvailableUpdate();

    $with = Livewire::test('admin.sidebar-settings')->html();

    expect(str_contains($without, '>1<'))->toBeFalse()
        ->and(str_contains($with, '>1<'))->toBeTrue();
});

it('refreshes the settings sidebar badge after a check', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('admin.sidebar-settings');

    seedAvailableUpdate();

    $component->dispatch('updates-checked');

    expect(str_contains($component->html(), '>1<'))->toBeTrue();
});

it('keeps the admin reachable while the site is in maintenance mode', function (): void {
    config()->set('app.maintenance.driver', 'cache');
    config()->set('app.maintenance.store', 'array');

    $this->artisan('down')->assertExitCode(0);

    $this->actingAsAdmin()->get(route('admin.dashboard'))->assertOk();
    $this->get('/')->assertServiceUnavailable();

    $this->artisan('up')->assertExitCode(0);
});
