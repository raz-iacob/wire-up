<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Translation;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasTranslations
{
    /** @var array<string, array<string, string>> */
    protected array $translatedTexts = [];

    /**
     * @return MorphMany<Translation, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * @return MorphOne<Translation, $this>
     */
    public function translation(): MorphOne
    {
        return $this->morphOne(Translation::class, 'translatable')
            ->ofMany(['id' => 'max'], fn (Builder $q) => $q->where('locale', app()->getLocale()));
    }

    public function deleteTranslations(): void
    {
        $this->translations()->delete();
        $this->unsetRelation('translations');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return $this
     *
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): self
    {
        collect($attributes)
            ->only($this->translatedAttributes())
            ->each(function (string|array $translation, string $key): void {
                $this->translatedTexts[$key] = is_string($translation) ? [app()->getLocale() => $translation] : $translation;
            });

        return parent::fill(collect($attributes)
            ->except($this->translatedAttributes())
            ->toArray());
    }

    /**
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        if (in_array($key, $this->translatedAttributes(), true)) {
            if (! $this->relationLoaded('translations')) {
                $this->load('translations');
            }

            $translation = $this->findTranslation($key);

            return $translation->body ?? '';
        }

        return parent::getAttribute($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        if (! $this->relationLoaded('translations')) {
            return $attributes;
        }

        foreach ($this->translatedAttributes() as $field) {
            $attributes[$field] = $this->translationsFor($field);
        }

        return $attributes;
    }

    protected static function bootHasTranslations(): void
    {
        static::created(fn (self $model) => $model->syncTranslations());
        static::saved(fn (self $model) => $model->syncTranslations());
        static::deleting(fn (self $model) => $model->deleteTranslations());
    }

    /**
     * @return array<int, string>
     */
    protected function translatedAttributes(): array
    {
        return ['title'];
    }

    /**
     * @return array<string, string>
     */
    protected function translationsFor(string $field): array
    {
        return $this->translations
            ->where('key', $field)
            ->filter(fn ($t): bool => ! empty($t->body))
            ->mapWithKeys(fn ($t): array => [$t->locale => $t->body])
            ->toArray();
    }

    protected function findTranslation(string $key): ?Translation
    {
        $translations = $this->translations->whereNotNull('body')->filter(fn ($t): bool => $t->body !== '');

        return $translations->first(fn ($t): bool => $t->key === $key && $t->locale === app()->getLocale())
            ?? $translations->first(fn ($t): bool => $t->key === $key);
    }

    protected function syncTranslations(): void
    {
        collect($this->translatedAttributes())->map(function (string $attribute): void {
            app('locales')->each(function (string $locale) use ($attribute): void {
                $this->addOrUpdateTranslation($locale, $attribute, $this->translatedTexts[$attribute][$locale] ?? '');
            });
        });
    }

    protected function addOrUpdateTranslation(string $locale, string $key, string $body): Translation
    {
        return $this->translations()->updateOrCreate([
            'locale' => $locale,
            'key' => $key,
            'translatable_type' => $this->getMorphClass(),
            'translatable_id' => $this->getKey(),
        ], [
            'body' => $body,
        ]);
    }
}
