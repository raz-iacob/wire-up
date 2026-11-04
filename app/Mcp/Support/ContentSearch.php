<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Actions\UpdateRecordAction;
use App\Enums\ContentStatus;
use App\Enums\FieldType;
use App\Models\Page;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class ContentSearch
{
    /**
     * @var list<FieldType>
     */
    private const array TEXT_FIELDS = [FieldType::TEXT, FieldType::TEXTAREA, FieldType::RICH_TEXT];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'find' => ['required', 'string', 'min:2', 'max:200'],
            'ignore_case' => ['boolean'],
            'locale' => ['nullable', 'string', Rule::in(resolve('localization')->getActiveLocaleCodes()->all())],
            'pages' => ['list'],
            'pages.*' => ['integer'],
            'records' => ['list'],
            'records.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'find.required' => 'Pass "find": the text to look for.',
            'find.min' => '"find" must be at least 2 characters.',
            'find.max' => '"find" may not be longer than 200 characters.',
            'ignore_case.boolean' => '"ignore_case" must be true or false.',
            'locale.in' => '"locale" must be one of the site\'s languages: '.resolve('localization')->getActiveLocaleCodes()->implode(', ').'.',
            'pages.list' => '"pages" must be a list of page ids.',
            'pages.*.integer' => '"pages" must be a list of page ids.',
            'records.list' => '"records" must be a list of record ids.',
            'records.*.integer' => '"records" must be a list of record ids.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(Page|Record $model): array
    {
        $summary = $model instanceof Page ? Pages::summary($model) : Records::recordSummary($model);

        return [
            'type' => $model instanceof Page ? 'page' : 'record',
            'id' => $model->id,
            'title' => $summary['title'],
            'url' => $summary['url'],
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return LazyCollection<int, Page|Record>
     */
    public static function models(array $scope): LazyCollection
    {
        $pageIds = is_array($scope['pages'] ?? null) ? $scope['pages'] : null;
        $recordIds = is_array($scope['records'] ?? null) ? $scope['records'] : null;
        $limited = $pageIds !== null || $recordIds !== null;

        $pages = Page::query()
            ->with(['slugs', 'translations', 'blocks'])
            ->when($limited, fn (Builder $query): Builder => $query->whereIn('id', $pageIds ?? []))
            ->lazyById(100);

        $records = Record::query()
            ->with(['recordType', 'slugs', 'translations', 'blocks'])
            ->when($limited, fn (Builder $query): Builder => $query->whereIn('id', $recordIds ?? []))
            ->lazyById(100);

        return $pages->concat($records);
    }

    /**
     * @return list<array{path: string, key: string, locale: string, text: string}>
     */
    public static function strings(Page|Record $model): array
    {
        $codes = resolve('localization')->getActiveLocaleCodes()->all();
        $out = [];

        foreach (['title', 'description'] as $attribute) {
            foreach ($model->translationsFor($attribute) as $locale => $text) {
                if ($text !== '') {
                    $out[] = ['path' => "{$attribute}.{$locale}", 'key' => $attribute, 'locale' => (string) $locale, 'text' => $text];
                }
            }
        }

        foreach ($model->blocks as $block) {
            self::walk($block->content ?? [], "blocks.{$block->id}.content", "blocks.{$block->id}", $codes, $out);
        }

        if ($model instanceof Record) {
            self::recordData($model, $codes, $out);
        }

        return $out;
    }

    public static function count(string $text, string $find, bool $ignoreCase): int
    {
        $total = 0;

        foreach (self::segments($text) as $index => $segment) {
            $total += $index % 2 === 0 ? (int) preg_match_all(self::pattern($find, $ignoreCase), $segment) : 0;
        }

        return $total;
    }

    public static function excerpt(string $text, string $find, bool $ignoreCase): string
    {
        $plain = (string) Str::of(html_entity_decode(strip_tags((string) preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?>/i', ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish();
        $position = $ignoreCase ? mb_stripos($plain, $find) : mb_strpos($plain, $find);
        $start = max(0, (int) $position - 50);

        return ($start > 0 ? '…' : '').Str::limit(mb_substr($plain, $start), 120 + mb_strlen($find));
    }

    public static function replaceIn(Page|Record $model, string $find, string $replacement, bool $ignoreCase, ?string $locale): int
    {
        $containers = [
            'title' => $model->translationsFor('title'),
            'description' => $model->translationsFor('description'),
            'blocks' => [],
            'data' => $model instanceof Record && is_array($model->data) ? $model->data : [],
        ];

        foreach ($model->blocks as $block) {
            $containers['blocks'][$block->id] = ['id' => $block->id, 'type' => $block->type->value, 'content' => $block->content ?? []];
        }

        $total = 0;
        $touched = [];

        foreach (self::strings($model) as $string) {
            $count = $locale === null || $string['locale'] === $locale ? self::count($string['text'], $find, $ignoreCase) : 0;

            if ($count > 0) {
                data_set($containers, $string['path'], self::replace($string['text'], $find, $replacement, $ignoreCase));
                $touched[Str::before($string['path'], '.')] = true;
                $total += $count;
            }
        }

        if ($total > 0) {
            self::save($model, array_intersect_key([...$containers, 'blocks' => array_values($containers['blocks'])], $touched));
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function save(Page|Record $model, array $attributes): void
    {
        if ($model instanceof Page) {
            Pages::update($model, $attributes);

            return;
        }

        $status = $model->computed_status;

        new UpdateRecordAction()->handle($model, [
            ...$attributes,
            'status' => $status,
            ...($status === ContentStatus::SCHEDULED ? ['published_at' => $model->published_at] : []),
        ]);
    }

    private static function replace(string $text, string $find, string $replacement, bool $ignoreCase): string
    {
        $segments = self::segments($text);

        foreach ($segments as $index => $segment) {
            if ($index % 2 === 0) {
                $segments[$index] = (string) preg_replace_callback(self::pattern($find, $ignoreCase), fn (): string => $replacement, $segment);
            }
        }

        return implode('', $segments);
    }

    /**
     * @return list<string>
     */
    private static function segments(string $text): array
    {
        return preg_split('/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    }

    private static function pattern(string $find, bool $ignoreCase): string
    {
        return '/'.preg_quote($find, '/').'/u'.($ignoreCase ? 'i' : '');
    }

    /**
     * @param  array<int, string>  $codes
     * @param  list<array{path: string, key: string, locale: string, text: string}>  $out
     */
    private static function recordData(Record $record, array $codes, array &$out): void
    {
        $data = is_array($record->data) ? $record->data : [];
        $default = resolve('localization')->getDefaultLocale();

        foreach ($record->recordType->fields as $field) {
            $key = (string) ($field['key'] ?? '');
            $value = $data[$key] ?? null;

            if (! in_array(FieldType::tryFrom((string) ($field['type'] ?? '')), self::TEXT_FIELDS, true)) {
                continue;
            }

            if (is_string($value) && $value !== '') {
                $out[] = ['path' => "data.{$key}", 'key' => "data.{$key}", 'locale' => $default, 'text' => $value];
            }

            self::walk($value, "data.{$key}", "data.{$key}", $codes, $out);
        }
    }

    /**
     * @param  array<int, string>  $codes
     * @param  list<array{path: string, key: string, locale: string, text: string}>  $out
     */
    private static function walk(mixed $node, string $path, string $key, array $codes, array &$out): void
    {
        if (! is_array($node)) {
            return;
        }

        if (ContentStrings::isLocaleMap($node, $codes)) {
            foreach ($node as $locale => $text) {
                if (is_string($text) && $text !== '') {
                    $out[] = ['path' => "{$path}.{$locale}", 'key' => $key, 'locale' => (string) $locale, 'text' => $text];
                }
            }

            return;
        }

        foreach ($node as $childKey => $child) {
            self::walk($child, "{$path}.{$childKey}", "{$key}.{$childKey}", $codes, $out);
        }
    }
}
