<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Slug;
use BackedEnum;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait HasSlugs
{
    /**
     * @return MorphMany<Slug, $this>
     */
    public function slugs(): MorphMany
    {
        return $this->morphMany(Slug::class, 'sluggable');
    }

    public function getSlug(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->slugs->first(fn (Slug $slug): bool => $slug->locale === $locale)->slug ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function getSlugsArray(): array
    {
        return $this->slugs->pluck('slug', 'locale')->toArray();
    }

    public function setSlugs(): void
    {
        $currentLocale = app()->getLocale();
        app('localization')->getActiveLocaleCodes()->each(function (string $locale): void {
            app()->setLocale($locale);
            $rawValue = $this->buildRawValueForLocale($locale);
            $slug = $this->resolveUniqueSlug(Str::slug($rawValue), $locale);

            $this->upsertSlug($locale, $slug);
        });

        app()->setLocale($currentLocale);
    }

    /**
     * @param  array<string, string>  $slugs
     */
    public function updateSlugs(array $slugs): void
    {
        foreach ($slugs as $locale => $slug) {
            $this->upsertSlug($locale, $this->resolveUniqueSlug($slug, $locale));
        }
    }

    protected static function bootHasSlugs(): void
    {
        static::deleting(fn (self $model) => $model->deleteSlugs());
    }

    /**
     * @return array<int, string>
     */
    protected function slugAttributes(): array
    {
        return ['title'];
    }

    /**
     * @return Attribute<string, null>
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->getSlug(),
        );
    }

    protected function buildRawValueForLocale(string $locale): string
    {
        return collect($this->slugAttributes())
            ->map(function (string $attr): ?string {
                throw_unless(isset($this->{$attr}), InvalidArgumentException::class, "You must define the field {$attr} in your model");

                return $this->normalizeAttributeValue($this->{$attr});
            })
            ->filter()
            ->join(' ');
    }

    protected function normalizeAttributeValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return is_string($value) ? $value : null;
    }

    protected function upsertSlug(string $locale, string $slug): void
    {
        $this->slugs()->updateOrCreate(
            ['locale' => $locale],
            [
                'slug' => $slug,
                'sluggable_id' => $this->id,
                'sluggable_type' => $this->getMorphClass(),
            ]
        );
    }

    protected function resolveUniqueSlug(string $baseSlug, string $locale): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (
            Slug::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->where(function ($q): void {
                    $q->where('sluggable_id', '!=', $this->id)
                        ->orWhere('sluggable_type', '!=', $this->getMorphClass());
                })
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forSlug(Builder $query, string $slug, ?string $locale = null): void
    {
        $query->with(['slugs'])->whereHas('slugs', function (Builder $query) use ($slug, $locale): void {
            $query->where('slug', $slug)
                ->where('locale', $locale ?? app()->getLocale());
        });
    }

    protected function deleteSlugs(): void
    {
        $this->slugs()->delete();
    }
}
