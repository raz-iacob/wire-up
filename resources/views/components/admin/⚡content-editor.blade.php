<?php

declare(strict_types=1);

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Reactive;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int|string, array{id: string, type: string, content: array<string, mixed>}>
     */
    #[Modelable]
    public array $blocks = [];

    #[Reactive]
    public string $locale = 'en';

    public bool $multiLocale = false;

    public ?string $selected = null;

    /**
     * @var array<string, array{label: string, icon: string, content: array<string, mixed>}>
     */
    public array $types = [
        'hero' => [
            'label' => 'Hero',
            'icon' => 'photo',
            'content' => ['align' => 'center'],
        ],
        'text-image' => [
            'label' => 'Text + Image',
            'icon' => 'view-columns',
            'content' => ['reverseLayout' => false],
        ],
        'spacer' => [
            'label' => 'Spacer',
            'icon' => 'arrows-up-down',
            'content' => ['size' => 'medium'],
        ],
    ];

    public function add(string $type): void
    {
        if (! isset($this->types[$type])) {
            return;
        }

        $id = 'new-'.Str::uuid()->toString();

        $this->blocks[$id] = [
            'id' => $id,
            'type' => $type,
            'content' => $this->types[$type]['content'],
        ];
    }

    public function reorder(string $id, int $position): void
    {
        $ids = array_map(strval(...), array_keys($this->blocks));

        $from = array_search($id, $ids, true);

        if ($from === false) {
            return;
        }

        array_splice($ids, $from, 1);
        array_splice($ids, $position, 0, [$id]);

        $this->blocks = collect($ids)
            ->mapWithKeys(fn (string $key): array => [$key => $this->blocks[$key]])
            ->all();
    }

    public function confirmRemove(string $id): void
    {
        $this->selected = $id;

        Flux::modal('remove-block')->show();
    }

    public function remove(): void
    {
        $this->blocks = collect($this->blocks)
            ->reject(fn (array $block): bool => (string) $block['id'] === $this->selected)
            ->all();

        $this->selected = null;

        Flux::modal('remove-block')->close();
    }

    public function render(): View
    {
        return $this->view();
    }
};
?>

<div>
    <div wire:sort="reorder" class="flex flex-col gap-3 mb-6">
        @foreach ($blocks as $index => $block)
            @php
                $blockLabel = __($types[$block['type']]['label'] ?? $block['type']);
                $initialTitle = match ($block['type']) {
                    'hero' => str(strip_tags($block['content']['heading'][$locale] ?? ''))->squish()->limit(50)->value() ?: $blockLabel,
                    'text-image' => str(strip_tags($block['content']['body'][$locale] ?? ''))->squish()->words(8, '…')->value() ?: $blockLabel,
                    default => $blockLabel,
                };
            @endphp
            <flux:card
                size="sm"
                class="p-0! overflow-hidden"
                wire:key="block-{{ $block['id'] }}"
                wire:sort:item="{{ $block['id'] }}"
                x-data="{ open: true }">
                <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                    <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                        <flux:icon name="bars-3" variant="mini" />
                    </div>

                    <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                        <flux:heading class="truncate" x-text="window.blockTitle($wire.blocks[@js((string) $index)], $wire.locale, @js($blockLabel))">{{ $initialTitle }}</flux:heading>
                    </button>

                    <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                        <flux:button size="xs" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                            <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                            <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                        </flux:button>
                        <flux:button size="xs" icon="trash" variant="subtle" square :tooltip="__('Remove')" wire:click="confirmRemove('{{ $block['id'] }}')" />
                    </div>
                </div>

                <div class="p-4" x-show="open" x-collapse>
                    @includeIf("components.admin.blocks.{$block['type']}", ['block' => $block, 'locale' => $locale, 'multiLocale' => $multiLocale, 'index' => $index])
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:dropdown position="bottom" align="start" class="mt-3">
        <flux:button icon="plus" variant="filled">{{ __('Add block') }}</flux:button>
        <flux:menu>
            @foreach ($types as $type => $definition)
                <flux:menu.item :icon="$definition['icon']" wire:click="add('{{ $type }}')">
                    {{ __($definition['label']) }}
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>

    <flux:modal name="remove-block" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove block') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Are you sure you want to remove this block? This cannot be undone.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="remove">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@script
<script>
    window.blockTitle = function (block, locale, fallback) {
        if (! block) {
            return fallback;
        }

        const content = block.content || {};
        let raw = '';

        if (block.type === 'hero') {
            raw = (content.heading || {})[locale] || '';
        } else if (block.type === 'text-image') {
            raw = (content.body || {})[locale] || '';
        } else {
            return fallback;
        }

        const div = document.createElement('div');
        div.innerHTML = raw;
        const text = (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();

        if (! text) {
            return fallback;
        }

        if (block.type === 'text-image') {
            const words = text.split(' ');

            return words.length > 8 ? words.slice(0, 8).join(' ') + '…' : text;
        }

        return text.length > 50 ? text.slice(0, 50) + '…' : text;
    };
</script>
@endscript
