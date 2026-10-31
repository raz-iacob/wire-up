<?php

declare(strict_types=1);

use App\Models\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('caches settings as a key/value map', function (): void {
    Settings::set(['site_name' => 'Acme']);

    expect(Settings::cached())->toMatchArray(['site_name' => 'Acme']);
});

it('boots without the cache table so artisan runs before the first migration', function (): void {
    config(['cache.default' => 'database']);
    app()->forgetInstance('cache');
    app()->forgetInstance('cache.store');
    Schema::dropIfExists('cache');

    expect(Settings::cached())->toBeEmpty();
});

it('returns an empty array when the database is unreachable', function (): void {
    Schema::shouldReceive('hasTable')->andThrow(unreachableDatabase());

    expect(Settings::cached())->toBeEmpty();
});

it('returns an empty array when the settings table is absent', function (): void {
    Schema::shouldReceive('hasTable')->with('settings')->andReturnFalse();

    expect(Settings::cached())->toBeEmpty();
});

it('stores secret settings encrypted in the database and the cache, and reads them back in plain text', function (): void {
    Settings::set(['mail_password' => 'hunter2', 'site_name' => 'Acme']);

    $stored = json_decode((string) DB::table('settings')->where('key', 'mail_password')->value('value'), true);

    expect($stored)->not->toBe('hunter2')
        ->and(Crypt::decryptString($stored))->toBe('hunter2')
        ->and(cache()->get(Settings::CACHE_KEY)['mail_password'])->toBe($stored)
        ->and(DB::table('settings')->where('key', 'site_name')->value('value'))->toBe('"Acme"')
        ->and(Settings::get('mail_password'))->toBe('hunter2')
        ->and(config('site.mail_password'))->toBe('hunter2');
});

it('counts saving the same secret again as no change', function (): void {
    expect(Settings::set(['mail_password' => 'hunter2']))->toBe(['mail_password'])
        ->and(Settings::set(['mail_password' => 'hunter2']))->toBeEmpty()
        ->and(Settings::set(['mail_password' => 'swordfish']))->toBe(['mail_password'])
        ->and(Settings::get('mail_password'))->toBe('swordfish');
});

it('treats a secret it can no longer decrypt as not set, and can still clear it', function (): void {
    DB::table('settings')->insert(['key' => 'ai_api_key', 'value' => json_encode('not-encrypted-with-this-key')]);
    Settings::flush();

    expect(Settings::get('ai_api_key'))->toBe('')
        ->and(Settings::set(['ai_api_key' => '']))->toBe(['ai_api_key'])
        ->and(DB::table('settings')->where('key', 'ai_api_key')->value('value'))->toBe('""');
});
