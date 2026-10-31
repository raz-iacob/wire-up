<?php

declare(strict_types=1);

use App\Models\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

function encryptSecretSettingsMigration(): object
{
    return require database_path('migrations/'.basename((string) collect(glob(database_path('migrations/*_encrypt_secret_settings.php')))->first()));
}

function storedSetting(string $key): mixed
{
    return json_decode((string) DB::table('settings')->where('key', $key)->value('value'), true);
}

it('encrypts the secrets an existing site saved in plain text', function (): void {
    DB::table('settings')->insert([
        ['key' => 'stripe_secret_key', 'value' => json_encode('sk_test_plain')],
        ['key' => 'mail_password', 'value' => json_encode('hunter2')],
        ['key' => 'pexels_api_key', 'value' => json_encode('')],
        ['key' => 'site_name', 'value' => json_encode('Acme')],
    ]);
    cache()->forever(Settings::CACHE_KEY, ['stripe_secret_key' => 'sk_test_plain']);

    encryptSecretSettingsMigration()->up();

    expect(Crypt::decryptString(storedSetting('stripe_secret_key')))->toBe('sk_test_plain')
        ->and(Crypt::decryptString(storedSetting('mail_password')))->toBe('hunter2')
        ->and(storedSetting('pexels_api_key'))->toBe('')
        ->and(storedSetting('site_name'))->toBe('Acme')
        ->and(cache()->has(Settings::CACHE_KEY))->toBeFalse()
        ->and(Settings::get('stripe_secret_key'))->toBe('sk_test_plain');
});

it('leaves secrets that are already encrypted alone', function (): void {
    Settings::set(['ai_api_key' => 'sk-ant-key']);
    $encrypted = storedSetting('ai_api_key');

    encryptSecretSettingsMigration()->up();

    expect(storedSetting('ai_api_key'))->toBe($encrypted)
        ->and(Settings::get('ai_api_key'))->toBe('sk-ant-key');
});
