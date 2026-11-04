<?php

declare(strict_types=1);

use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

function errorLogDeveloper(): User
{
    $developer = User::factory()->developer()->create(['name' => 'Dana Developer', 'active' => true]);
    test()->actingAs($developer);

    return $developer;
}

it('opens only for developers, with the unresolved errors counted in the sidebar', function (): void {
    ErrorLog::factory()->count(3)->create();
    ErrorLog::factory()->resolved()->create();

    $this->actingAs(User::factory()->owner()->create(['active' => true]));

    $this->get(route('admin.settings-errors'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertDontSee(route('admin.settings-errors'));

    errorLogDeveloper();

    $this->get(route('admin.settings-errors'))->assertOk()->assertSee('Error log');

    Livewire::test('admin.sidebar-settings')->assertSeeInOrder([route('admin.settings-errors'), '3', 'Error log']);
});

it('lists unresolved errors first by default, latest first, and filters and searches them', function (): void {
    ErrorLog::factory()->create(['type' => 'App\\Exceptions\\OldProblem', 'message' => 'Older trouble', 'last_seen_at' => now()->subDay()]);
    ErrorLog::factory()->create(['type' => 'App\\Exceptions\\NewProblem', 'message' => 'Newer trouble', 'occurrences' => 1234]);
    ErrorLog::factory()->resolved()->create(['type' => 'App\\Exceptions\\FixedProblem', 'message' => 'Fixed trouble']);
    errorLogDeveloper();

    Livewire::test('pages::admin.settings-errors')
        ->assertSeeInOrder(['NewProblem: Newer trouble', '1,234', 'Unresolved', 'OldProblem: Older trouble'])
        ->assertDontSee('Fixed trouble')
        ->set('status', 'resolved')
        ->assertSee('Fixed trouble')
        ->assertDontSee('Newer trouble')
        ->set('status', 'all')
        ->assertSee(['Fixed trouble', 'Newer trouble', 'Older trouble'])
        ->set('search', 'older')
        ->assertSee('Older trouble')
        ->assertDontSee('Newer trouble')
        ->set('search', 'nothing like this')
        ->assertSee('No errors match these filters.');

    ErrorLog::query()->update(['resolved_at' => now()]);

    Livewire::test('pages::admin.settings-errors')->assertSee('No unresolved errors.');
});

it('opens an error from a report link and shows everything about it', function (): void {
    $member = User::factory()->member()->create(['name' => 'Mia Member']);
    $error = ErrorLog::factory()->create([
        'type' => QueryException::class,
        'message' => 'SQLSTATE[HY000]: General error',
        'file' => 'app/Actions/CreateOrderAction.php',
        'line' => 42,
        'address' => 'POST https://example.com/checkout',
        'user_id' => $member->id,
        'trace' => ['app/Actions/CreateOrderAction.php:42 App\\Actions\\CreateOrderAction->handle()', 'vendor/laravel/framework/src/Illuminate/Routing/Route.php:254 Illuminate\\Routing\\Route->run()'],
        'occurrences' => 7,
    ]);
    errorLogDeveloper();

    Livewire::withQueryParams(['error' => $error->id])
        ->test('pages::admin.settings-errors')
        ->assertSet('showDetails', true)
        ->assertSee([
            QueryException::class,
            'SQLSTATE[HY000]: General error',
            'app/Actions/CreateOrderAction.php:42',
            'POST https://example.com/checkout',
            'Mia Member',
            'App\\Actions\\CreateOrderAction->handle()',
            'Wire Up v0.6.0',
        ])
        ->set('showDetails', false)
        ->assertSet('viewingId', null)
        ->call('show', $error->id)
        ->assertSet('showDetails', true)
        ->assertSet('viewingId', $error->id);
});

it('resolves and reopens errors one at a time and in bulk, noting who resolved them', function (): void {
    $first = ErrorLog::factory()->create();
    $second = ErrorLog::factory()->create();
    $developer = errorLogDeveloper();

    $component = Livewire::test('pages::admin.settings-errors')
        ->call('resolve', $first->id)
        ->assertDispatched('toast-show', toastSays('Error marked as resolved.'));

    expect($first->refresh()->isResolved())->toBeTrue()
        ->and($first->resolved_by)->toBe($developer->id);

    $component->call('show', $first->id)->assertSee('Dana Developer');

    $component->call('reopen', $first->id)->assertDispatched('toast-show', toastSays('Error reopened.'));

    expect($first->refresh()->isResolved())->toBeFalse();

    $component->set('selected', [$first->id, $second->id])
        ->call('resolveSelected')
        ->assertDispatched('toast-show', toastSays('2 errors marked as resolved.'))
        ->assertSet('selected', [])
        ->set('selected', [$first->id])
        ->call('reopenSelected')
        ->assertDispatched('toast-show', toastSays('1 error reopened.'));

    expect($first->refresh()->isResolved())->toBeFalse()
        ->and($second->refresh()->isResolved())->toBeTrue();
});

it('deletes errors one at a time and in bulk', function (): void {
    $errors = ErrorLog::factory()->count(3)->create();
    errorLogDeveloper();

    Livewire::test('pages::admin.settings-errors')
        ->call('show', $errors[0]->id)
        ->call('confirmDelete', $errors[0]->id)
        ->call('delete')
        ->assertDispatched('toast-show', toastSays('Error deleted.'))
        ->assertSet('showDetails', false)
        ->set('selected', [$errors[1]->id, $errors[2]->id])
        ->call('deleteSelected')
        ->assertDispatched('toast-show', toastSays('2 errors deleted.'));

    expect(ErrorLog::query()->exists())->toBeFalse();
});
