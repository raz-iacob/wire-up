<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LocaleFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $code
 * @property-read string $name
 * @property-read string|null $endonym
 * @property-read string|null $script
 * @property-read bool $rtl
 * @property-read bool $active
 * @property-read bool $active
 * @property-read bool $published
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Locale extends Model
{
    /** @use HasFactory<LocaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'code' => 'string',
            'name' => 'string',
            'endonym' => 'string',
            'script' => 'string',
            'rtl' => 'boolean',
            'active' => 'boolean',
            'published' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Locale>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', 1);
    }
}
