<?php

declare(strict_types=1);

use App\Models\Settings;
use Livewire\Livewire;
use Tests\Support\FakeOffsiteStorage;

it('suggests the site’s domain as the folder and auto as the region', function (): void {
    config()->set('app.url', 'https://www.example.com');

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.offsite-backups')
        ->assertSet('form.offsite_folder', 'www.example.com')
        ->assertSet('form.offsite_region', 'auto')
        ->assertSee('Off-site backups');
});

it('connects off-site storage and keeps the secret write-only', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.offsite-backups')
        ->set('form.offsite_endpoint', ' https://account.r2.cloudflarestorage.com/ ')
        ->set('form.offsite_region', '')
        ->set('form.offsite_bucket', 'site-backups')
        ->set('form.offsite_folder', '/sites/example.com/')
        ->set('form.offsite_access_key', ' access-key-id ')
        ->set('form.offsite_secret_key', ' secret-access-key ')
        ->call('connect')
        ->assertHasNoErrors()
        ->assertSet('form.offsite_secret_key', '')
        ->assertDispatched('toast-show', toastSays('Off-site backups connected.'));

    expect(Settings::get('offsite_endpoint'))->toBe('https://account.r2.cloudflarestorage.com')
        ->and(Settings::get('offsite_region'))->toBe('auto')
        ->and(Settings::get('offsite_bucket'))->toBe('site-backups')
        ->and(Settings::get('offsite_folder'))->toBe('sites/example.com')
        ->and(Settings::get('offsite_access_key'))->toBe('access-key-id')
        ->and(Settings::get('offsite_secret_key'))->toBe('secret-access-key');

    Livewire::test('admin.integrations.offsite-backups')
        ->assertSet('form.offsite_secret_key', '')
        ->assertSee('…-key')
        ->set('form.offsite_bucket', 'renamed-bucket')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('offsite_bucket'))->toBe('renamed-bucket')
        ->and(Settings::get('offsite_secret_key'))->toBe('secret-access-key');
});

it('explains what is wrong with the storage details', function (string $field, string $value, string $message): void {
    $this->actingAsAdmin();

    $component = Livewire::test('admin.integrations.offsite-backups')
        ->set('form.offsite_bucket', 'site-backups')
        ->set('form.offsite_access_key', 'access-key-id')
        ->set('form.offsite_secret_key', 'secret-access-key')
        ->set("form.{$field}", $value)
        ->call('connect')
        ->assertHasErrors("form.{$field}");

    expect($component->errors()->first("form.{$field}"))->toBe($message);
})->with([
    'endpoint without a scheme' => ['offsite_endpoint', 'account.r2.cloudflarestorage.com', 'Enter the endpoint as a full address, like https://example.r2.cloudflarestorage.com.'],
    'region with spaces' => ['offsite_region', 'us east', 'Enter the region exactly as your storage provider shows it, like auto or us-east-1.'],
    'bucket with spaces' => ['offsite_bucket', 'my bucket', 'Enter the bucket name exactly as your storage provider shows it.'],
    'folder escaping upwards' => ['offsite_folder', '../other-site', 'Use only letters, numbers, dots, dashes and slashes in the folder.'],
    'folder with spaces' => ['offsite_folder', 'my folder', 'Use only letters, numbers, dots, dashes and slashes in the folder.'],
    'missing access key' => ['offsite_access_key', '', 'The access key ID field is required.'],
]);

it('tests the typed storage details without saving them', function (): void {
    $disk = FakeOffsiteStorage::connect();
    config()->set('site.offsite_bucket', '');

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.offsite-backups')
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

    Livewire::test('admin.integrations.offsite-backups')
        ->set('form.offsite_bucket', 'site-backups')
        ->set('form.offsite_access_key', 'access-key-id')
        ->set('form.offsite_secret_key', 'wrong-secret')
        ->call('test')
        ->assertDispatched('toast-show', toastSays('The storage refused the test: SignatureDoesNotMatch: The request signature we calculated does not match the signature you provided.'));
});

it('stops testing after five attempts a minute', function (): void {
    FakeOffsiteStorage::connect();

    $this->actingAsAdmin();

    $component = Livewire::test('admin.integrations.offsite-backups');

    foreach (range(1, 5) as $attempt) {
        $component->call('test');
    }

    $component->call('test')
        ->assertDispatched('toast-show', toastSays('That’s a lot of tests. Please wait a moment and try again.'));
});

it('disconnects off-site storage', function (): void {
    Settings::set(['offsite_bucket' => 'site-backups', 'offsite_access_key' => 'access-key-id', 'offsite_secret_key' => 'secret-access-key']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.offsite-backups')
        ->call('disconnect')
        ->assertDispatched('toast-show', toastSays('Disconnected.'));

    expect(Settings::get('offsite_bucket'))->toBe('')
        ->and(Settings::get('offsite_secret_key'))->toBe('');
});
