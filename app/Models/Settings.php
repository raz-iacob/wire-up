<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
     * @return array<string, mixed>
     */
    public static function cached(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return cache()->rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->get(['key', 'value'])->pluck('value', 'key')->all()
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(self::cached(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function set(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        self::flush();
    }

    public static function flush(): void
    {
        cache()->forget(self::CACHE_KEY);
        config()->set('site', self::cached());
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
}
