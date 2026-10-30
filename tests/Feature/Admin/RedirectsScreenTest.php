<?php

declare(strict_types=1);

use App\Enums\RedirectOrigin;
use App\Models\NotFoundPath;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function redirectsViewer(): User
{
    $role = Role::query()->firstOrCreate(['key' => 'redirects-viewer'], ['name' => 'Viewer', 'abilities' => ['settings.view'], 'bypass' => false, 'is_protected' => false]);

    return User::factory()->role($role)->create(['active' => true]);
}

it('lists redirects with their type and use, linked from the settings sidebar', function (): void {
    Redirect::factory()->create(['from' => '/old-team', 'to' => '/people', 'origin' => RedirectOrigin::AUTOMATIC, 'hits' => 1234, 'last_hit_at' => now()]);
    Redirect::factory()->create(['from' => '/summer', 'to' => 'https://shop.example.com/sale', 'status_code' => 302]);

    $this->actingAsAdmin()
        ->get(route('admin.settings-redirects'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-redirects')
        ->assertSee(route('admin.settings-redirects'))
        ->assertSeeInOrder(['/summer', 'https://shop.example.com/sale', 'Temporary', 'Never', '/old-team', 'Automatic', '/people', 'Permanent', '1,234', now()->format('M d, Y H:i')]);
});

it('searches redirects by either address', function (): void {
    Redirect::factory()->create(['from' => '/old-team', 'to' => '/people']);
    Redirect::factory()->create(['from' => '/summer', 'to' => '/offers']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->set('search', 'peop')
        ->assertSee('/old-team')
        ->assertDontSee('/summer')
        ->set('search', 'nothing-like-this')
        ->assertSee('No redirects match your search.');
});

it('says how redirects appear when there are none yet', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->assertSee('Changing the web address of a published page adds one automatically.')
        ->assertSee('Nothing yet.');
});

it('adds a redirect from the form', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->call('add')
        ->set('from', '/Old-Page/')
        ->set('to', '/new-page')
        ->set('statusCode', 302)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', toastSays('Redirect saved.'))
        ->assertSet('from', '');

    $redirect = Redirect::query()->sole();

    expect($redirect->from)->toBe('/old-page')
        ->and($redirect->status_code)->toBe(302)
        ->and($redirect->origin)->toBe(RedirectOrigin::MANUAL);
});

it('explains what is wrong with a redirect in plain words', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->call('save')
        ->assertHasErrors(['from' => 'The old address field is required.', 'to' => 'The new address field is required.'])
        ->set('from', '/same')
        ->set('to', '/same/')
        ->call('save')
        ->assertHasErrors(['to' => 'The new address is the same as the old one.']);

    expect(Redirect::query()->exists())->toBeFalse();
});

it('edits a redirect in place', function (): void {
    $redirect = Redirect::factory()->create(['from' => '/old', 'to' => '/first']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->call('edit', $redirect->id)
        ->assertSet('from', '/old')
        ->assertSet('to', '/first')
        ->set('from', '/older')
        ->set('to', 'https://example.com/second')
        ->call('save')
        ->assertHasNoErrors();

    expect(Redirect::query()->sole()->only(['id', 'from', 'to']))->toBe(['id' => $redirect->id, 'from' => '/older', 'to' => 'https://example.com/second']);
});

it('deletes a redirect after confirming', function (): void {
    $redirect = Redirect::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->call('confirmDelete', $redirect->id)
        ->assertSet('deletingId', $redirect->id)
        ->call('delete')
        ->assertSet('deletingId', null)
        ->assertDispatched('toast-show', toastSays('Redirect deleted.'));

    expect(Redirect::query()->exists())->toBeFalse();
});

it('imports redirects from a csv and summarises what it skipped', function (): void {
    Redirect::factory()->create(['from' => '/existing', 'to' => '/before']);

    $csv = UploadedFile::fake()->createWithContent('redirects.csv', "from,to\n/old-page,/new-page\n/existing,/after\n/admin,/x\n");

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->set('csv', $csv)
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('csv', null)
        ->assertSee('1 added, 1 updated, 1 skipped.')
        ->assertSee('Line 4')
        ->assertSee('This address belongs to the admin and cannot be redirected.')
        ->call('dismissSummary')
        ->assertSet('importSummary', null);

    expect(Redirect::query()->orderBy('from')->pluck('to', 'from')->all())->toBe(['/existing' => '/after', '/old-page' => '/new-page'])
        ->and(Redirect::query()->where('from', '/old-page')->sole()->origin)->toBe(RedirectOrigin::IMPORT);
});

it('refuses a csv with more redirects than one import takes', function (): void {
    $lines = implode("\n", array_map(fn (int $line): string => "/old-{$line},/new", range(1, 5001)));

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->set('csv', UploadedFile::fake()->createWithContent('redirects.csv', $lines))
        ->call('import')
        ->assertHasErrors(['csv' => 'Import up to 5,000 redirects at a time.']);

    expect(Redirect::query()->exists())->toBeFalse();
});

it('lists not found addresses and turns one into a redirect', function (): void {
    $missing = NotFoundPath::factory()->create(['path' => '/old-menu', 'hits' => 42, 'referrer' => 'https://partner.example.com']);
    NotFoundPath::factory()->create(['path' => '/rarely', 'hits' => 2]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->assertSeeInOrder(['/old-menu', 'From https://partner.example.com', '42', '/rarely'])
        ->call('redirectNotFound', $missing->id)
        ->assertSet('from', '/old-menu')
        ->assertSet('editingId', null)
        ->set('to', '/menu')
        ->call('save')
        ->assertHasNoErrors();

    expect(Redirect::query()->sole()->to)->toBe('/menu')
        ->and(NotFoundPath::query()->pluck('path')->all())->toBe(['/rarely']);
});

it('dismisses a not found address', function (): void {
    $missing = NotFoundPath::factory()->create(['path' => '/robots-noise']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-redirects')
        ->call('dismiss', $missing->id)
        ->assertDontSee('/robots-noise');

    expect(NotFoundPath::query()->exists())->toBeFalse();
});

it('shows redirects to someone who may only view settings without letting them change any', function (): void {
    $redirect = Redirect::factory()->create(['from' => '/old-team']);

    $this->actingAs(redirectsViewer());

    Livewire::test('pages::admin.settings-redirects')
        ->assertSee('/old-team')
        ->assertDontSee('wire:click="add"', escape: false)
        ->assertDontSee('Import CSV')
        ->call('edit', $redirect->id)
        ->assertForbidden();
});

it('keeps every change behind the settings edit permission', function (string $method, array $arguments): void {
    $this->actingAs(redirectsViewer());

    Livewire::test('pages::admin.settings-redirects')
        ->call($method, ...$arguments)
        ->assertForbidden();
})->with([
    'add' => ['add', []],
    'redirect not found' => ['redirectNotFound', [1]],
    'save' => ['save', []],
    'confirm delete' => ['confirmDelete', [1]],
    'delete' => ['delete', []],
    'dismiss' => ['dismiss', [1]],
    'import' => ['import', []],
]);
