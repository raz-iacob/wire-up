<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\BlockType;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

trait HasBlockBuilder
{
    /**
     * @var array<int|string, array{id: string, type: string, position: int, content: array<string, mixed>}>
     */
    public array $blocks = [];

    public ?string $selectedBlock = null;

    public ?int $insertPosition = null;

    public function updated(string $name): void
    {
        if (preg_match('/^blocks\.[^.]+\.content\.(ctaPrimary|ctaSecondary)\.link\.type$/', $name) === 1) {
            $path = Str::after($name, 'blocks.');
            data_set($this->blocks, Str::beforeLast($path, '.type').'.value', '');
        }

    }

    public function openBlockPicker(?int $position = null): void
    {
        $this->insertPosition = $position;

        Flux::modal('block-picker')->show();

        $this->dispatch('block-picker-opened');
    }

    public function addBlock(string $type): void
    {
        $blockType = BlockType::tryFrom($type);

        if ($blockType === null) {
            return;
        }

        $id = 'new-'.Str::uuid()->toString();
        $content = $blockType->defaultContent();

        $blocks = array_values($this->blocks);
        $position = $this->insertPosition ?? count($blocks);

        array_splice($blocks, $position, 0, [[
            'id' => $id,
            'type' => $blockType->value,
            'position' => $position,
            'content' => $content,
        ]]);

        $this->blocks = collect($blocks)
            ->mapWithKeys(fn (array $block, int $index): array => [
                $block['id'] => [...$block, 'position' => $index],
            ])
            ->all();

        $this->insertPosition = null;

        Flux::modal('block-picker')->close();

        if (($firstItemId = data_get($content, 'items.0.id')) !== null) {
            $this->dispatch('open-block-item', id: $firstItemId);
        }
    }

    public function addAccordionItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = ['id' => $itemId, 'title' => [], 'body' => []];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function removeAccordionItem(string $id, int $index): void
    {
        if (! isset($this->blocks[$id]['content']['items'][$index])) {
            return;
        }

        unset($this->blocks[$id]['content']['items'][$index]);

        $this->blocks[$id]['content']['items'] = array_values($this->blocks[$id]['content']['items']);
    }

    #[Renderless]
    public function reorderAccordionItems(string $itemId, int $position): void
    {
        foreach ($this->blocks as $blockId => $block) {
            $items = $block['content']['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            $from = collect($items)->search(fn (array $item): bool => ($item['id'] ?? null) === $itemId);

            if ($from === false) {
                continue;
            }

            $moved = $items[$from];
            array_splice($items, $from, 1);
            array_splice($items, $position, 0, [$moved]);

            $this->blocks[$blockId]['content']['items'] = array_values($items);

            return;
        }
    }

    #[On('block-item-media-updated')]
    public function syncBlockItemMedia(string $blockId, string $itemId, string $field, mixed $value): void
    {
        if (! isset($this->blocks[$blockId]['content']['items'])) {
            return;
        }

        foreach ($this->blocks[$blockId]['content']['items'] as $index => $item) {
            if (($item['id'] ?? null) === $itemId) {
                $this->blocks[$blockId]['content']['items'][$index][$field] = $value;

                return;
            }
        }
    }

    public function addTestimonialItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = ['id' => $itemId, 'quote' => [], 'author' => [], 'role' => [], 'avatar' => null, 'rating' => 0];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function addSponsorItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = ['id' => $itemId, 'logo' => null, 'name' => [], 'link' => '', 'tier' => ''];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function removeSponsorItem(string $id, int $index): void
    {
        if (! isset($this->blocks[$id]['content']['items'][$index])) {
            return;
        }

        unset($this->blocks[$id]['content']['items'][$index]);

        $this->blocks[$id]['content']['items'] = array_values($this->blocks[$id]['content']['items']);
    }

    public function addFeatureItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = [
            'id' => $itemId,
            'image' => null,
            'title' => [],
            'body' => [],
            'cta' => [
                'enabled' => false,
                'text' => [],
                'link' => ['type' => 'url', 'value' => '', 'newTab' => false],
                'bg' => null,
                'textColor' => null,
            ],
        ];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function removeFeatureItem(string $id, int $index): void
    {
        if (! isset($this->blocks[$id]['content']['items'][$index])) {
            return;
        }

        unset($this->blocks[$id]['content']['items'][$index]);

        $this->blocks[$id]['content']['items'] = array_values($this->blocks[$id]['content']['items']);
    }

    #[Renderless]
    public function reorderFeatureItems(string $itemId, int $position): void
    {
        foreach ($this->blocks as $blockId => $block) {
            $items = $block['content']['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            $from = collect($items)->search(fn (array $item): bool => ($item['id'] ?? null) === $itemId);

            if ($from === false) {
                continue;
            }

            $moved = $items[$from];
            array_splice($items, $from, 1);
            array_splice($items, $position, 0, [$moved]);

            $this->blocks[$blockId]['content']['items'] = array_values($items);

            return;
        }
    }

    #[Renderless]
    public function reorderSponsorItems(string $itemId, int $position): void
    {
        foreach ($this->blocks as $blockId => $block) {
            $items = $block['content']['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            $from = collect($items)->search(fn (array $item): bool => ($item['id'] ?? null) === $itemId);

            if ($from === false) {
                continue;
            }

            $moved = $items[$from];
            array_splice($items, $from, 1);
            array_splice($items, $position, 0, [$moved]);

            $this->blocks[$blockId]['content']['items'] = array_values($items);

            return;
        }
    }

    public function removeTestimonialItem(string $id, int $index): void
    {
        if (! isset($this->blocks[$id]['content']['items'][$index])) {
            return;
        }

        unset($this->blocks[$id]['content']['items'][$index]);

        $this->blocks[$id]['content']['items'] = array_values($this->blocks[$id]['content']['items']);
    }

    #[Renderless]
    public function reorderTestimonialItems(string $itemId, int $position): void
    {
        foreach ($this->blocks as $blockId => $block) {
            $items = $block['content']['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            $from = collect($items)->search(fn (array $item): bool => ($item['id'] ?? null) === $itemId);

            if ($from === false) {
                continue;
            }

            $moved = $items[$from];
            array_splice($items, $from, 1);
            array_splice($items, $position, 0, [$moved]);

            $this->blocks[$blockId]['content']['items'] = array_values($items);

            return;
        }
    }

    public function addButtonItem(string $id): void
    {
        if (! isset($this->blocks[$id]) || count($this->blocks[$id]['content']['items'] ?? []) >= 3) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = [
            'id' => $itemId,
            'text' => [],
            'variant' => 'primary',
            'link' => ['type' => 'url', 'value' => '', 'newTab' => false],
        ];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function removeButtonItem(string $id, int $index): void
    {
        if (! isset($this->blocks[$id]['content']['items'][$index])) {
            return;
        }

        unset($this->blocks[$id]['content']['items'][$index]);

        $this->blocks[$id]['content']['items'] = array_values($this->blocks[$id]['content']['items']);
    }

    #[Renderless]
    public function reorderButtonItems(string $itemId, int $position): void
    {
        foreach ($this->blocks as $blockId => $block) {
            $items = $block['content']['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            $from = collect($items)->search(fn (array $item): bool => ($item['id'] ?? null) === $itemId);

            if ($from === false) {
                continue;
            }

            $moved = $items[$from];
            array_splice($items, $from, 1);
            array_splice($items, $position, 0, [$moved]);

            $this->blocks[$blockId]['content']['items'] = array_values($items);

            return;
        }
    }

    public function addStatItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = ['id' => $itemId, 'value' => [], 'label' => []];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function removeStatItem(string $id, int $index): void
    {
        $this->removeBlockItem($id, $index);
    }

    #[Renderless]
    public function reorderStatItems(string $itemId, int $position): void
    {
        $this->reorderBlockItems($itemId, $position);
    }

    public function addTeamItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = [
            'id' => $itemId,
            'photo' => null,
            'name' => [],
            'role' => [],
            'bio' => [],
            'socials' => ['email' => '', 'website' => '', 'linkedin' => '', 'x' => '', 'instagram' => ''],
        ];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function removeTeamItem(string $id, int $index): void
    {
        $this->removeBlockItem($id, $index);
    }

    #[Renderless]
    public function reorderTeamItems(string $itemId, int $position): void
    {
        $this->reorderBlockItems($itemId, $position);
    }

    public function addPricingItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $itemId = (string) Str::uuid();
        $this->blocks[$id]['content']['items'][] = [
            'id' => $itemId,
            'name' => [],
            'price' => [],
            'period' => [],
            'description' => [],
            'features' => [],
            'featured' => false,
            'badge' => [],
            'cta' => [
                'enabled' => false,
                'text' => [],
                'link' => ['type' => 'url', 'value' => '', 'newTab' => false],
                'bg' => null,
                'textColor' => null,
            ],
        ];
        $this->dispatch('open-block-item', id: $itemId);
    }

    public function removePricingItem(string $id, int $index): void
    {
        $this->removeBlockItem($id, $index);
    }

    #[Renderless]
    public function reorderPricingItems(string $itemId, int $position): void
    {
        $this->reorderBlockItems($itemId, $position);
    }

    public function addContactBuiltin(string $id, string $key): void
    {
        if (! isset($this->blocks[$id]) || ! in_array($key, ['name', 'email', 'phone', 'subject', 'message'], true)) {
            return;
        }

        $order = $this->blocks[$id]['content']['fieldOrder'] ?? [];

        if (in_array($key, $order, true)) {
            return;
        }

        $order[] = $key;
        $this->blocks[$id]['content']['fieldOrder'] = $order;

        if (! isset($this->blocks[$id]['content']['fields'][$key])) {
            $this->blocks[$id]['content']['fields'][$key] = ['required' => false, 'label' => [], 'placeholder' => [], 'column' => 'left'];
        }
    }

    public function addContactField(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $fieldId = (string) Str::uuid();

        $this->blocks[$id]['content']['customFields'][] = [
            'id' => $fieldId,
            'label' => [],
            'type' => 'text',
            'required' => false,
            'options' => '',
            'column' => 'left',
        ];

        $this->blocks[$id]['content']['fieldOrder'][] = $fieldId;
        $this->dispatch('open-block-item', id: $fieldId);
    }

    public function removeContactField(string $id, string $token): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $this->blocks[$id]['content']['fieldOrder'] = array_values(array_filter(
            $this->blocks[$id]['content']['fieldOrder'] ?? [],
            fn (string $orderToken): bool => $orderToken !== $token,
        ));

        $this->blocks[$id]['content']['customFields'] = array_values(array_filter(
            $this->blocks[$id]['content']['customFields'] ?? [],
            fn (array $field): bool => ($field['id'] ?? null) !== $token,
        ));
    }

    #[Renderless]
    public function reorderContactFields(string $itemId, int $position): void
    {
        $parts = explode('::', $itemId, 2);

        if (count($parts) !== 2) {
            return;
        }

        [$blockId, $token] = $parts;
        $order = $this->blocks[$blockId]['content']['fieldOrder'] ?? null;

        if (! is_array($order)) {
            return;
        }

        $from = array_search($token, $order, true);

        if ($from === false) {
            return;
        }

        array_splice($order, $from, 1);
        array_splice($order, $position, 0, [$token]);

        $this->blocks[$blockId]['content']['fieldOrder'] = array_values($order);
    }

    public function reorderBlocks(string $id, int $position): void
    {
        $ids = array_map(strval(...), array_keys($this->blocks));

        $from = array_search($id, $ids, true);

        if ($from === false) {
            return;
        }

        array_splice($ids, $from, 1);
        array_splice($ids, $position, 0, [$id]);

        $this->blocks = collect($ids)
            ->mapWithKeys(fn (string $blockId, int $index): array => [
                $blockId => [...$this->blocks[$blockId], 'position' => $index],
            ])
            ->all();
    }

    public function confirmRemoveBlock(string $id): void
    {
        $this->selectedBlock = $id;

        Flux::modal('remove-block')->show();
    }

    public function removeBlock(): void
    {
        $this->blocks = collect($this->blocks)
            ->reject(fn (array $block): bool => (string) $block['id'] === $this->selectedBlock)
            ->values()
            ->mapWithKeys(fn (array $block, int $index): array => [
                $block['id'] => [...$block, 'position' => $index],
            ])
            ->all();

        $this->selectedBlock = null;

        Flux::modal('remove-block')->close();
    }

    /**
     * @param  array<int|string, array{id: string, type: string, position: int, content: array<string, mixed>}>  $blocks
     * @return array<int|string, array{id: string, type: string, position: int, content: array<string, mixed>}>
     */
    protected function withBlockDefaults(array $blocks): array
    {
        return collect($blocks)
            ->map(function (array $block): array {
                $type = BlockType::tryFrom($block['type']);

                if ($type !== null) {
                    $block['content'] = array_replace_recursive($type->defaultContent(), $block['content']);
                }

                if (is_array($block['content']['items'] ?? null)) {
                    $block['content']['items'] = array_map(function (array $item): array {
                        if (empty($item['id'])) {
                            $item['id'] = (string) Str::uuid();
                        }

                        return $item;
                    }, $block['content']['items']);
                }

                return $block;
            })
            ->all();
    }

    protected function normalizeBlockAnchors(): void
    {
        $seen = [];

        foreach ($this->blocks as $id => $block) {
            if (! BlockType::from($block['type'])->hasAnchor()) {
                continue;
            }

            $anchor = Str::slug((string) data_get($block, 'content.anchor', ''));

            if ($anchor !== '' && in_array($anchor, $seen, true)) {
                $suffix = 2;

                while (in_array("{$anchor}-{$suffix}", $seen, true)) {
                    $suffix++;
                }

                $anchor = "{$anchor}-{$suffix}";
            }

            if ($anchor !== '') {
                $seen[] = $anchor;
            }

            $this->blocks[$id]['content']['anchor'] = $anchor;
        }
    }

    protected function removeBlockItem(string $id, int $index): void
    {
        if (! isset($this->blocks[$id]['content']['items'][$index])) {
            return;
        }

        unset($this->blocks[$id]['content']['items'][$index]);

        $this->blocks[$id]['content']['items'] = array_values($this->blocks[$id]['content']['items']);
    }

    protected function reorderBlockItems(string $itemId, int $position): void
    {
        foreach ($this->blocks as $blockId => $block) {
            $items = $block['content']['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            $from = collect($items)->search(fn (array $item): bool => ($item['id'] ?? null) === $itemId);

            if ($from === false) {
                continue;
            }

            $moved = $items[$from];
            array_splice($items, $from, 1);
            array_splice($items, $position, 0, [$moved]);

            $this->blocks[$blockId]['content']['items'] = array_values($items);

            return;
        }
    }
}
