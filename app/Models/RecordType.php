<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RecordTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $key
 * @property-read string $slug_prefix
 * @property-read string $icon
 * @property-read string $name
 * @property-read array<int, array<string, mixed>> $fields
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class RecordType extends Model
{
    /** @use HasFactory<RecordTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'fields' => 'array',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
