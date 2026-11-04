<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Enums\BlockType;

final class BlockChanges
{
    /**
     * @var list<string>
     */
    public const array ACTIONS = ['update', 'add', 'remove', 'move', 'update-item', 'add-item', 'remove-item'];

    /**
     * @param  list<array{id: int|string, type: string, content: array<array-key, mixed>}>  $blocks
     */
    public function __construct(private array $blocks) {}

    /**
     * @param  list<array<string, mixed>>  $changes
     * @return list<array{id: int|string, type: string, content: array<array-key, mixed>}>|string
     */
    public function apply(array $changes): array|string
    {
        foreach ($changes as $index => $change) {
            $action = (string) ($change['action'] ?? '');
            $error = match ($action) {
                'update' => $this->update($change),
                'add' => $this->add($change, $index + 1),
                'remove' => $this->remove($change),
                'move' => $this->move($change),
                'update-item' => $this->updateItem($change),
                'add-item' => $this->addItem($change),
                default => $this->removeItem($change),
            };

            if ($error !== null) {
                return 'Change '.($index + 1)." ({$action}): {$error} Nothing was saved.";
            }
        }

        return $this->blocks;
    }

    /**
     * @param  array<array-key, mixed>  $current
     * @param  array<array-key, mixed>  $changes
     * @return array<array-key, mixed>
     */
    private static function merge(array $current, array $changes): array
    {
        foreach ($changes as $key => $value) {
            $existing = $current[$key] ?? null;
            $current[$key] = is_array($value) && ! array_is_list($value) && is_array($existing) && ! array_is_list($existing)
                ? self::merge($existing, $value)
                : $value;
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function update(array $change): ?string
    {
        $position = $this->find($change['block'] ?? null);

        if (is_string($position)) {
            return $position;
        }

        $block = $this->blocks[$position];
        $content = Blocks::localize(BlockType::from($block['type']), $this->content($change));
        $this->blocks[$position]['content'] = self::merge($block['content'], $content);

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function add(array $change, int $number): ?string
    {
        $type = BlockType::tryFrom((string) ($change['type'] ?? ''));

        if ($type === null) {
            return 'pass "type", one of: '.implode(', ', BlockType::values()).'.';
        }

        $at = $this->target($change, count($this->blocks));

        if (is_string($at)) {
            return $at;
        }

        array_splice($this->blocks, $at, 0, [['id' => "new-{$number}", 'type' => $type->value, 'content' => $this->content($change)]]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function remove(array $change): ?string
    {
        $position = $this->find($change['block'] ?? null);

        if (is_string($position)) {
            return $position;
        }

        array_splice($this->blocks, $position, 1);

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function move(array $change): ?string
    {
        $position = $this->find($change['block'] ?? null);

        if (is_string($position)) {
            return $position;
        }

        if (! isset($change['after']) && ! isset($change['before'])) {
            return 'pass "after" or "before" with the id of the block to move it next to.';
        }

        [$block] = array_splice($this->blocks, $position, 1);
        $at = $this->target($change, count($this->blocks));

        if (is_string($at)) {
            return $at;
        }

        array_splice($this->blocks, $at, 0, [$block]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function updateItem(array $change): ?string
    {
        $found = $this->findItem($change);

        if (is_string($found)) {
            return $found;
        }

        [$position, $itemIndex] = $found;
        $block = $this->blocks[$position];
        $values = Blocks::localizeItem(BlockType::from($block['type']), $this->content($change));
        $current = is_array($block['content']['items'][$itemIndex] ?? null) ? $block['content']['items'][$itemIndex] : [];
        $this->blocks[$position]['content']['items'][$itemIndex] = self::merge($current, $values);

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function addItem(array $change): ?string
    {
        $position = $this->find($change['block'] ?? null);

        if (is_string($position)) {
            return $position;
        }

        $block = $this->blocks[$position];

        if (Blocks::itemTemplate(BlockType::from($block['type'])) === null) {
            return "block {$block['id']} is a {$block['type']} block, which has no items.";
        }

        $items = $this->itemList($block);
        $at = count($items);
        $anchor = $change['after'] ?? $change['before'] ?? null;

        if ($anchor !== null) {
            $index = $this->itemIndex($items, $anchor);

            if ($index === null) {
                return $this->missingItem($block, $anchor);
            }

            $at = isset($change['after']) ? $index + 1 : $index;
        }

        $values = Blocks::localizeItem(BlockType::from($block['type']), $this->content($change));
        array_splice($items, $at, 0, [$values]);
        $this->blocks[$position]['content']['items'] = $items;

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function removeItem(array $change): ?string
    {
        $found = $this->findItem($change);

        if (is_string($found)) {
            return $found;
        }

        [$position, $itemIndex] = $found;
        $items = $this->itemList($this->blocks[$position]);
        array_splice($items, $itemIndex, 1);
        $this->blocks[$position]['content']['items'] = $items;

        return null;
    }

    private function find(mixed $id): int|string
    {
        if ($id === null || $id === '') {
            return 'pass "block" with the id of the block to change. Use get-page or get-record with "outline": true to see them.';
        }

        foreach ($this->blocks as $position => $block) {
            if ((string) $block['id'] === (string) $id) {
                return $position;
            }
        }

        return 'there is no block '.json_encode($id).'. The blocks are: '.$this->blockIds().'.';
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array{0: int, 1: int}|string
     */
    private function findItem(array $change): array|string
    {
        $position = $this->find($change['block'] ?? null);

        if (is_string($position)) {
            return $position;
        }

        $item = $change['item'] ?? null;

        if ($item === null || $item === '') {
            return 'pass "item" with the id of the item to change.';
        }

        $index = $this->itemIndex($this->itemList($this->blocks[$position]), $item);

        return $index === null ? $this->missingItem($this->blocks[$position], $item) : [$position, $index];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function target(array $change, int $end): int|string
    {
        foreach (['after' => 1, 'before' => 0] as $key => $offset) {
            if (isset($change[$key])) {
                $position = $this->find($change[$key]);

                return is_string($position) ? $position : $position + $offset;
            }
        }

        return $end;
    }

    /**
     * @param  array{id: int|string, type: string, content: array<array-key, mixed>}  $block
     * @return list<mixed>
     */
    private function itemList(array $block): array
    {
        $items = $block['content']['items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @param  list<mixed>  $items
     */
    private function itemIndex(array $items, mixed $id): ?int
    {
        foreach ($items as $index => $item) {
            if (is_array($item) && (string) ($item['id'] ?? '') === (string) $id) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array{id: int|string, type: string, content: array<array-key, mixed>}  $block
     */
    private function missingItem(array $block, mixed $id): string
    {
        $ids = array_map(fn (array $item): string => (string) ($item['id'] ?? ''), Blocks::items($block['content']));

        return "block {$block['id']} has no item ".json_encode($id).'. '.($ids === [] ? 'It has no items.' : 'Its items are: '.implode(', ', $ids).'.');
    }

    private function blockIds(): string
    {
        return implode(', ', array_map(fn (array $block): string => "{$block['id']} ({$block['type']})", $this->blocks));
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array<array-key, mixed>
     */
    private function content(array $change): array
    {
        return is_array($change['content'] ?? null) ? $change['content'] : [];
    }
}
