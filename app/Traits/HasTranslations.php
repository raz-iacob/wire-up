<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Translation;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Query\JoinClause;

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
     * @return array<string, string>
     */
    public function translationsFor(string $field): array
    {
        return $this->translations
            ->where('key', $field)
            ->mapWithKeys(fn ($t): array => [$t->locale => $t->body])
            ->toArray();
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

    #[Scope]
    protected function orderByTranslation(Builder $query, string $field, string $order = 'ASC'): void
    {
        $table = $this->getTable();
        $type = $this->getMorphClass();
        $locale = app()->getLocale();

        $query
            ->leftJoin('translations', function (JoinClause $join) use ($table, $field, $type, $locale): void {
                $join->on('translations.translatable_id', '=', $table.'.id')
                    ->where('translations.translatable_type', $type)
                    ->where('translations.key', $field)
                    ->where('translations.locale', $locale);
            })
            ->orderBy('translations.body', $order)
            ->select($table.'.*')
            ->with('translations');
    }

    #[Scope]
    protected function orWhereTranslationLike(Builder $query, string $field, string $value): void
    {
        $this->whereTranslationLike($query, $field, $value, true);
    }

    #[Scope]
    protected function whereTranslationLike(Builder $query, string $field, string $value, bool $isOr = false): void
    {
        $method = $isOr ? 'orWhereHas' : 'whereHas';

        $query->$method('translations', function (Builder $query) use ($field, $value): void {
            $query->where('translations.key', $field)
                ->whereLike('translations.body', '%'.$value.'%')
                ->where('translations.locale', app()->getLocale());
        });
    }
}
