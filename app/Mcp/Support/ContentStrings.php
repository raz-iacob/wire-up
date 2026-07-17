<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use Illuminate\Support\Str;

final readonly class ContentStrings
{
    /**
     * @return array<int, string>
     */
    public static function targetLocales(): array
    {
        $localization = resolve('localization');
        $default = $localization->getDefaultLocale();

        return $localization->getActiveLocaleCodes()
            ->reject(fn (string $code): bool => $code === $default)
            ->values()
            ->all();
    }

    public static function resolve(string $type, int $id): Page|Record|null
    {
        return $type === 'record'
            ? Record::query()->with(['recordType', 'blocks', 'slugs', 'translations'])->find($id)
            : Page::query()->with(['blocks', 'slugs', 'translations'])->find($id);
    }

    /**
     * @return array<int, array{key: string, source: string, current: string}>
     */
    public static function extract(Page|Record $model, string $locale): array
    {
        $default = resolve('localization')->getDefaultLocale();
        $codes = resolve('localization')->getActiveLocaleCodes()->all();

        $out = [];

        foreach (['title', 'description'] as $attribute) {
            $map = $model->translationsFor($attribute);
            $source = (string) ($map[$default] ?? '');

            if ($source !== '') {
                $out[] = ['key' => $attribute, 'source' => $source, 'current' => (string) ($map[$locale] ?? '')];
            }
        }

        $model->loadMissing('blocks');

        foreach ($model->blocks as $block) {
            self::walk($block->content ?? [], "blocks.{$block->id}", $default, $locale, $codes, $out);
        }

        if ($model instanceof Record) {
            self::walk(is_array($model->data) ? $model->data : [], 'data', $default, $locale, $codes, $out);
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $translations
     * @return array{attributes: array<string, mixed>, unknown: array<int, string>, applied: int, published: bool}
     */
    public static function apply(Page|Record $model, string $locale, array $translations): array
    {
        $known = [];
        foreach (self::extract($model, $locale) as $string) {
            $known[$string['key']] = true;
        }

        $titleMap = $model->translationsFor('title');
        $descriptionMap = $model->translationsFor('description');
        $blocks = self::blocksById($model);
        $data = $model instanceof Record && is_array($model->data) ? $model->data : [];

        $unknown = [];
        $applied = 0;
        $touched = ['title' => false, 'description' => false, 'blocks' => false, 'data' => false];

        foreach ($translations as $key => $value) {
            if (! is_string($key) || ! isset($known[$key])) {
                $unknown[] = (string) $key;

                continue;
            }

            $text = is_string($value) ? mb_trim($value) : '';
            $applied++;

            match (true) {
                $key === 'title' => self::flag($titleMap, $locale, $text, $touched, 'title'),
                $key === 'description' => self::flag($descriptionMap, $locale, $text, $touched, 'description'),
                str_starts_with($key, 'blocks.') => self::flagPath($blocks, self::blockPath($key, $locale), $text, $touched, 'blocks'),
                default => self::flagPath($data, mb_substr($key, 5).".{$locale}", $text, $touched, 'data'),
            };
        }

        return [
            'attributes' => self::attributes($model, $locale, $titleMap, $descriptionMap, $blocks, $data, $touched, $applied),
            'unknown' => $unknown,
            'applied' => $applied,
            'published' => $applied > 0 && (string) ($titleMap[$locale] ?? '') !== '',
        ];
    }

    /**
     * @param  array<string, string>  $titleMap
     * @param  array<string, string>  $descriptionMap
     * @param  array<int|string, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $data
     * @param  array<string, bool>  $touched
     * @return array<string, mixed>
     */
    private static function attributes(Page|Record $model, string $locale, array $titleMap, array $descriptionMap, array $blocks, array $data, array $touched, int $applied): array
    {
        $status = $model->computed_status;
        $attributes = ['status' => $status];

        if ($status === ContentStatus::SCHEDULED) {
            $attributes['published_at'] = $model->published_at;
        }

        if ($touched['title']) {
            $attributes['title'] = $titleMap;
        }

        if ($touched['description']) {
            $attributes['description'] = $descriptionMap;
        }

        if ($touched['blocks']) {
            $attributes['blocks'] = array_values($blocks);
        }

        if ($touched['data']) {
            $attributes['data'] = $data;
        }

        $targetTitle = (string) ($titleMap[$locale] ?? '');

        if ($applied > 0 && $targetTitle !== '') {
            if ((string) ($model->getSlugsArray()[$locale] ?? '') === '') {
                $attributes['slugs'] = [$locale => Str::slug($targetTitle)];
            }

            $publishedLocales = $model->published_locales;

            if (! in_array($locale, $publishedLocales, true)) {
                $publishedLocales[] = $locale;
            }

            $attributes['metadata'] = [
                ...($model->metadata ?? []),
                'published_locales' => array_values($publishedLocales),
            ];
        }

        return $attributes;
    }

    /**
     * @param  array<string, string>  $map
     * @param  array<string, bool>  $touched
     */
    private static function flag(array &$map, string $locale, string $text, array &$touched, string $flag): void
    {
        $map[$locale] = $text;
        $touched[$flag] = true;
    }

    /**
     * @param  array<int|string, mixed>  $target
     * @param  array<string, bool>  $touched
     */
    private static function flagPath(array &$target, string $path, string $text, array &$touched, string $flag): void
    {
        data_set($target, $path, $text);
        $touched[$flag] = true;
    }

    private static function blockPath(string $key, string $locale): string
    {
        $parts = explode('.', $key);
        $id = $parts[1];
        $contentPath = implode('.', array_slice($parts, 2));

        return "{$id}.content.{$contentPath}.{$locale}";
    }

    /**
     * @return array<int|string, array{id: int, type: string, content: array<string, mixed>}>
     */
    private static function blocksById(Page|Record $model): array
    {
        $model->loadMissing('blocks');

        $blocks = [];

        foreach ($model->blocks as $block) {
            $blocks[$block->id] = [
                'id' => $block->id,
                'type' => $block->type->value,
                'content' => is_array($block->content) ? $block->content : [],
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $codes
     * @param  array<int, array{key: string, source: string, current: string}>  $out
     */
    private static function walk(mixed $node, string $path, string $default, string $locale, array $codes, array &$out): void
    {
        if (! is_array($node)) {
            return;
        }

        if (self::isLocaleMap($node, $codes)) {
            $source = $node[$default] ?? null;

            if (is_string($source) && $source !== '') {
                $out[] = ['key' => $path, 'source' => $source, 'current' => is_string($node[$locale] ?? null) ? $node[$locale] : ''];
            }

            return;
        }

        foreach ($node as $key => $value) {
            self::walk($value, "{$path}.{$key}", $default, $locale, $codes, $out);
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<int, string>  $codes
     */
    private static function isLocaleMap(array $node, array $codes): bool
    {
        if ($node === []) {
            return false;
        }

        return array_all($node, fn (mixed $value, int|string $key): bool => in_array((string) $key, $codes, true) && is_string($value));
    }
}
