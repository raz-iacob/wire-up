<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Actions\UpdateRecordAction;
use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Block;
use App\Models\Page;
use App\Models\Record;
use App\Services\MediaItem;
use App\Services\SiteBundle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class Blocks
{
    /**
     * @var list<string>
     */
    private const array REPEATING_KEYS = ['items', 'customFields'];

    /**
     * @var list<string>
     */
    private const array LIST_KEYS = ['sources', 'recordIds', 'media', 'labels', 'files', 'fields', 'customFields'];

    /**
     * @var list<string>
     */
    private const array ITEM_LABEL_KEYS = ['title', 'name', 'label', 'quote', 'text', 'value'];

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function prepare(array $blocks): array
    {
        $prepared = [];

        foreach (array_filter(Pages::orderedBlocks($blocks), is_array(...)) as $block) {
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];
            $type = BlockType::tryFrom((string) ($block['type'] ?? ''));

            if ($type instanceof BlockType) {
                $content = self::localize($type, $content);
            }

            $prepared[] = [...$block, 'content' => self::refreshMedia(self::assignItemIds($content))];
        }

        return $prepared;
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    public static function localize(BlockType $type, array $content): array
    {
        $localized = self::wrapText($content, $type->defaultContent(), '', resolve('localization')->getDefaultLocale());

        return is_array($localized) ? $localized : $content;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function localizeItem(BlockType $type, array $values): array
    {
        $template = self::itemTemplate($type);
        $localized = $template === null ? $values : self::wrapText($values, $template, '', resolve('localization')->getDefaultLocale());

        return is_array($localized) ? $localized : $values;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function itemTemplate(BlockType $type): ?array
    {
        $template = $type->defaultContent()['items'][0] ?? null;

        return is_array($template) ? $template : null;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function problem(array $blocks): ?string
    {
        $icons = config()->array('menu.icons');
        $problems = [];
        $badIcon = false;

        foreach ($blocks as $position => $block) {
            $type = (string) ($block['type'] ?? '');
            $label = is_numeric($block['id'] ?? null) ? "Block {$block['id']} ({$type})" : 'Block #'.($position + 1)." ({$type})";
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];
            $seen = [];

            foreach (self::items($content) as $item) {
                $id = (string) ($item['id'] ?? '');

                if (isset($seen[$id])) {
                    $problems[] = "{$label} has two items with the id \"{$id}\". Item ids must be unique within a block.";
                }

                $seen[$id] = true;
                $icon = $item['icon'] ?? null;

                if ($type === BlockType::FEATURE_CARDS->value && ($item['media'] ?? null) === 'icon' && is_string($icon) && $icon !== '' && ! in_array($icon, $icons, true)) {
                    $problems[] = "{$label}, item \"{$id}\": there is no icon called \"{$icon}\".";
                    $badIcon = true;
                }
            }
        }

        if ($badIcon) {
            $problems[] = 'Available icons: '.implode(', ', $icons).'.';
        }

        return $problems === [] ? null : implode(' ', $problems);
    }

    /**
     * @param  Collection<int, Block>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function detailed(Collection $blocks): array
    {
        return $blocks
            ->map(fn (Block $block): array => [
                'id' => $block->id,
                'type' => $block->type->value,
                'content' => self::withoutDerivedKeys($block->content ?? []),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Block>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function outline(Collection $blocks): array
    {
        $locale = resolve('localization')->getDefaultLocale();

        return $blocks
            ->map(fn (Block $block): array => array_filter([
                'id' => $block->id,
                'type' => $block->type->value,
                'anchor' => (string) data_get($block->content, 'anchor', ''),
                'heading' => $block->plainTextField('heading', $locale),
                'excerpt' => $block->plainTextField('heading', $locale) === '' ? Str::limit($block->plainText($locale), 120) : '',
                'items' => array_map(fn (array $item): array => [
                    'id' => (string) ($item['id'] ?? ''),
                    'title' => self::itemLabel($item, $locale),
                ], self::items($block->content ?? [])),
            ], fn (mixed $value): bool => $value !== '' && $value !== []))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function save(Page|Record $model, array $blocks): void
    {
        if ($model instanceof Page) {
            Pages::update($model, ['blocks' => $blocks]);

            return;
        }

        $status = $model->computed_status;

        new UpdateRecordAction()->handle($model, [
            'status' => $status,
            ...($status === ContentStatus::SCHEDULED ? ['published_at' => $model->published_at] : []),
            'blocks' => $blocks,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return list<array<string, mixed>>
     */
    public static function items(array $content): array
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];

        return array_values(array_filter($items, is_array(...)));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    public static function withoutDerivedKeys(array $value): array
    {
        $clean = [];

        foreach ($value as $key => $child) {
            if (in_array($key, SiteBundle::DERIVED_MEDIA_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($child) ? self::withoutDerivedKeys($child) : $child;
        }

        return $clean;
    }

    private static function wrapText(mixed $value, mixed $default, string $key, string $locale): mixed
    {
        if (is_string($value) && $default === [] && ! in_array($key, self::LIST_KEYS, true)) {
            return [$locale => $value];
        }

        if (! is_array($value) || ! is_array($default) || $default === []) {
            return $value;
        }

        if (array_is_list($default)) {
            return array_is_list($value)
                ? array_map(fn (mixed $item): mixed => self::wrapText($item, $default[0], $key, $locale), $value)
                : $value;
        }

        foreach ($value as $childKey => $child) {
            if (array_key_exists($childKey, $default)) {
                $value[$childKey] = self::wrapText($child, $default[$childKey], (string) $childKey, $locale);
            }
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    private static function assignItemIds(array $content): array
    {
        foreach (self::REPEATING_KEYS as $key) {
            if (! is_array($content[$key] ?? null) || ! array_is_list($content[$key])) {
                continue;
            }

            $content[$key] = array_map(
                fn (mixed $item): mixed => is_array($item) && (string) ($item['id'] ?? '') === '' ? [...$item, 'id' => (string) Str::uuid()] : $item,
                $content[$key],
            );
        }

        return $content;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function refreshMedia(array $node): array
    {
        if (is_string($node['source'] ?? null) && array_key_exists('id', $node)) {
            $media = MediaItem::fromSource($node['source']);

            if ($media !== null) {
                $node['preview'] = $media['preview'];
                $node['crop_src'] = $media['crop_src'];
            }
        }

        foreach ($node as $key => $child) {
            if (is_array($child)) {
                $node[$key] = self::refreshMedia($child);
            }
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemLabel(array $item, string $locale): string
    {
        foreach (self::ITEM_LABEL_KEYS as $key) {
            $text = data_get($item, "{$key}.{$locale}");

            if (is_string($text) && $text !== '') {
                return Str::limit(mb_trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 80);
            }
        }

        return '';
    }
}
