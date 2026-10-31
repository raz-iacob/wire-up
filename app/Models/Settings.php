<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PageCache;
use Carbon\CarbonInterface;
use Database\Factories\SettingsFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @property-read int $id
 * @property string $key
 * @property mixed $value
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Table(name: 'settings')]
final class Settings extends Model
{
    /** @use HasFactory<SettingsFactory> */
    use HasFactory;

    public const string CACHE_KEY = 'site-config';

    /**
     * @var array<int, string>
     */
    public const array ENCRYPTED_KEYS = [
        'pexels_api_key',
        'google_analytics_credentials',
        'google_maps_api_key',
        'slack_webhook_url',
        'ai_api_key',
        'mail_password',
        'stripe_secret_key',
        'stripe_webhook_secret',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function cached(): array
    {
        try {
            $warm = cache()->get(self::CACHE_KEY);

            if (is_array($warm)) {
                return self::decrypted($warm);
            }

            if (! Schema::hasTable('settings')) {
                return [];
            }

            return self::decrypted(cache()->rememberForever(
                self::CACHE_KEY,
                fn (): array => self::query()->get(['key', 'value'])->pluck('value', 'key')->all()
            ));
        } catch (QueryException) {
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(self::cached(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    public static function set(array $values): array
    {
        $changed = DB::transaction(function () use ($values): array {
            $changed = [];

            foreach ($values as $key => $value) {
                $setting = self::query()->firstOrNew(['key' => $key]);
                $stored = $setting->value;
                $setting->value = $value !== '' && self::decryptValue((string) $key, $stored) === $value ? $stored : self::encryptValue((string) $key, $value);

                if ($setting->isDirty()) {
                    $setting->save();
                    $changed[] = (string) $key;
                }
            }

            return $changed;
        });

        self::flush();

        return $changed;
    }

    public static function encryptValue(string $key, mixed $value): mixed
    {
        return in_array($key, self::ENCRYPTED_KEYS, true) && is_string($value) && $value !== '' ? Crypt::encryptString($value) : $value;
    }

    public static function decryptValue(string $key, mixed $value): mixed
    {
        if (! in_array($key, self::ENCRYPTED_KEYS, true) || ! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return '';
        }
    }

    public static function flush(): void
    {
        cache()->forget(self::CACHE_KEY);
        config()->set('site', self::cached());
        resolve(PageCache::class)->flush();
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'value' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function decrypted(array $values): array
    {
        foreach (array_intersect_key($values, array_flip(self::ENCRYPTED_KEYS)) as $key => $value) {
            $values[$key] = self::decryptValue($key, $value);
        }

        return $values;
    }
}
