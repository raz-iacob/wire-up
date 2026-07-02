<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasTranslations;
use Carbon\CarbonInterface;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property-read int $id
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Translation> $translations
 * @property-read string $name
 * @property-read Collection<int, Record> $records
 * @property-read Collection<int, Page> $pages
 */
final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return MorphToMany<Record, $this>
     */
    public function records(): MorphToMany
    {
        return $this->morphedByMany(Record::class, 'categorizable');
    }

    /**
     * @return MorphToMany<Page, $this>
     */
    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'categorizable');
    }

    /**
     * @return array<int, string>
     */
    protected function translatedAttributes(): array
    {
        return ['name'];
    }
}
