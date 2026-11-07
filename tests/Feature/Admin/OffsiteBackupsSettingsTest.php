<?php

declare(strict_types=1);

use App\Integrations\IntegrationRegistry;
use App\Integrations\OffsiteBackups;
use App\Models\Settings;
use App\Services\SettingsService;
use Livewire\Livewire;
use Tests\Support\FakeOffsiteStorage;

it('lives on the Backups screen instead of the integrations screen', function (): void {
    $this->actingAsAdmin();

    $this->get(route('admin.settings-backups'))->assertOk()->assertSeeLivewire('admin.offsite-backups');
    $this->get(route('admin.settings-integrations'))->assertOk()->assertDontSeeLivewire('admin.offsite-backups');

    expect(array_filter(IntegrationRegistry::cards(), fn (object $card): bool => $card instanceof OffsiteBackups))->toBeEmpty();
});

it('shows the storage fields once off-site copies are switched on, suggesting the site’s domain and auto', function (): void {
    config()->set('app.url', 'https://www.example.com');

    $this->actingAsAdmin();

    Livewire::test('admin.offsite-backups')
        ->assertSet('enabled', false)
        ->assertSee('Off-site copies')
        ->assertDontSee('Leave blank for Amazon S3.')
        ->set('enabled', true)
        ->assertSee('Leave blank for Amazon S3.')
        ->assertSet('form.offsite_folder', 'www.example.com')
        ->assertSet('form.offsite_region', 'auto')
        ->assertSet('keep', 14);
});

it('connects off-site storage, keeps the secret write-only and saves how many copies to keep', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.offsite-backups')
        ->set('enabled', true)
        ->set('form.offsite_endpoint', ' https://account.r2.cloudflarestorage.com/ ')
        ->set('form.offsite_region', '')
        ->set('form.offsite_bucket', 'site-backups')
        ->set('form.offsite_folder', '/sites/example.com/')
        ->set('form.offsite_access_key', ' access-key-id ')
        ->set('form.offsite_secret_key', ' secret-access-key ')
        ->set('keep', 30)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.offsite_secret_key', '')
        ->assertSee('Every backup is also copied to site-backups/sites/example.com.')
        ->assertDispatched('integrations-updated')
        ->assertDispatched('toast-show', toastSays('Off-site copies saved.'));

    expect(Settings::get('offsite_endpoint'))->toBe('https://account.r2.cloudflarestorage.com')
        ->and(Settings::get('offsite_region'))->toBe('auto')
        ->and(Settings::get('offsite_bucket'))->toBe('site-backups')
        ->and(Settings::get('offsite_folder'))->toBe('sites/example.com')
        ->and(Settings::get('offsite_access_key'))->toBe('access-key-id')
        ->and(Settings::get('offsite_secret_key'))->toBe('secret-access-key')
        ->and(resolve(SettingsService::class)->offsiteBackupsToKeep())->toBe(30);

    Livewire::test('admin.offsite-backups')
        ->assertSet('enabled', true)
        ->assertSet('form.offsite_secret_key', '')
        ->assertSee('…-key')
        ->set('form.offsite_bucket', 'renamed-bucket')
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::get('offsite_bucket'))->toBe('renamed-bucket')
        ->and(Settings::get('offsite_secret_key'))->toBe('secret-access-key');
});

it('explains what is wrong with the storage details', function (string $field, mixed $value, string $message): void {
    $this->actingAsAdmin();

    $component = Livewire::test('admin.offsite-backups')
        ->set('enabled', true)
        ->set('form.offsite_bucket', 'site-backups')
        ->set('form.offsite_access_key', 'access-key-id')
        ->set('form.offsite_secret_key', 'secret-access-key')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);

    expect($component->errors()->first($field))->toBe($message)
        ->and(Settings::get('offsite_bucket'))->toBeNull();
})->with([
    'endpoint without a scheme' => ['form.offsite_endpoint', 'account.r2.cloudflarestorage.com', 'Enter the endpoint as a full address, like https://example.r2.cloudflarestorage.com.'],
    'region with spaces' => ['form.offsite_region', 'us east', 'Enter the region exactly as your storage provider shows it, like auto or us-east-1.'],
    'bucket with spaces' => ['form.offsite_bucket', 'my bucket', 'Enter the bucket name exactly as your storage provider shows it.'],
    'folder escaping upwards' => ['form.offsite_folder', '../other-site', 'Use only letters, numbers, dots, dashes and slashes in the folder.'],
    'folder with spaces' => ['form.offsite_folder', 'my folder', 'Use only letters, numbers, dots, dashes and slashes in the folder.'],
    'missing access key' => ['form.offsite_access_key', '', 'The access key ID field is required.'],
    'too many copies' => ['keep', 101, 'The copies to keep field must not be greater than 100.'],
]);

it('tests the typed storage details without saving them', function (): void {
    $disk = FakeOffsiteStorage::connect();
    config()->set('site.offsite_bucket', '');

    $this->actingAsAdmin();

    Livewire::test('admin.offsite-backups')
        ->set('enabled', true)
        ->set('form.offsite_bucket', 'typed-bucket')
        ->set('form.offsite_folder', 'example.com')
        ->set('form.offsite_access_key', 'access-key-id')
        ->set('form.offsite_secret_key', 'secret-access-key')
        ->call('test')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', toastSays('Connected. Backups will be copied to typed-bucket/example.com.'));

    expect($disk->allFiles())->toBeEmpty()
        ->and(Settings::get('offsite_bucket'))->toBeNull();
});

it('passes on why the storage refused the test', function (): void {
    FakeOffsiteStorage::connectRefusing('SignatureDoesNotMatch', 'The request signature we calculated does not match the signature you provided.');

    $this->actingAsAdmin();

    Livewire::test('admin.offsite-backups')
        ->set('form.offsite_bucket', 'site-backups')
        ->set('form.offsite_access_key', 'access-key-id')
        ->set('form.offsite_secret_key', 'wrong-secret')
        ->call('test')
        ->assertDispatched('toast-show', toastSays('The storage refused the test: SignatureDoesNotMatch: The request signature we calculated does not match the signature you provided.'));
});

it('stops testing after five attempts a minute', function (): void {
    FakeOffsiteStorage::connect();

    $this->actingAsAdmin();

    $component = Livewire::test('admin.offsite-backups');

    foreach (range(1, 5) as $attempt) {
        $component->call('test');
    }

    $component->call('test')
        ->assertDispatched('toast-show', toastSays('That’s a lot of tests. Please wait a moment and try again.'));
});

it('turns off-site copies off and forgets the storage keys', function (): void {
    config()->set('app.url', 'https://www.example.com');
    Settings::set(['offsite_bucket' => 'site-backups', 'offsite_folder' => 'shared', 'offsite_access_key' => 'access-key-id', 'offsite_secret_key' => 'secret-access-key']);

    $this->actingAsAdmin();

    Livewire::test('admin.offsite-backups')
        ->assertSet('enabled', true)
        ->set('enabled', false)
        ->assertSee('Saving turns off off-site copies and forgets the storage keys.')
        ->call('save')
        ->assertDispatched('integrations-updated')
        ->assertDispatched('toast-show', toastSays('Off-site copies are off.'))
        ->assertSet('form.offsite_bucket', '')
        ->assertSet('form.offsite_folder', 'www.example.com')
        ->assertDontSee('Saving turns off off-site copies');

    expect(Settings::get('offsite_bucket'))->toBe('')
        ->and(Settings::get('offsite_secret_key'))->toBe('')
        ->and(resolve(OffsiteBackups::class)->connected(config()->array('site')))->toBeFalse();
});
