<?php

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    #[Modelable]
    public mixed $media = null;

    public string $name = 'cover';
    public ?string $type = null;
    public string $locale = 'en';
    public bool $multiLocale = false;
    public ?string $label = null;
    public ?string $note = null;
    public bool $multiple = false;
    public int $max = 30;
    public ?int $replaceIndex = null;

    public function mount(): void
    {
        $this->normalizeMediaState();
    }

    public function updatedMedia(): void
    {
        $this->normalizeMediaState();
    }

    public function targetKey(): string
    {
        return $this->name.'.'.$this->locale;
    }

    public function openLibrary(?int $replaceIndex = null): void
    {
        $this->replaceIndex = $replaceIndex;

        $this->dispatch(
            'select-media',
            target: $this->targetKey(),
            type: $this->normalizedType(),
            max: $this->selectionLimit($replaceIndex),
            media: $this->selectionForLibrary($replaceIndex),
        );
    }

    #[On('media-selected')]
    public function handleMediaSelected(string $target, array $media): void
    {
        if ($target !== $this->targetKey()) {
            return;
        }

        $selectedMedia = array_values(array_filter(array_map(
            $this->normalizeMediaItem(...),
            $media,
        )));

        if ($this->multiple) {
            $items = $this->selectedItems;

            if ($this->replaceIndex !== null && isset($items[$this->replaceIndex])) {
                if (isset($selectedMedia[0])) {
                    $items[$this->replaceIndex] = $selectedMedia[0];
                }

                $this->media = array_values($items);
                $this->replaceIndex = null;

                return;
            }

            $this->media = array_slice($selectedMedia, 0, $this->max);
            $this->replaceIndex = null;

            return;
        }

        $this->media = $selectedMedia[0] ?? null;
        $this->replaceIndex = null;
    }

    public function removeMedia(?int $index = null): void
    {
        if (! $this->multiple) {
            $this->media = null;

            return;
        }

        $items = $this->selectedItems;

        if ($index === null || ! isset($items[$index])) {
            return;
        }

        unset($items[$index]);

        $this->media = array_values($items);
    }

    public function reorderMedia(int $id, int $position): void
    {
        if (! $this->multiple) {
            return;
        }

        $items = $this->selectedItems;
        $currentPosition = array_search($id, array_column($items, 'id'), true);

        if ($currentPosition === false) {
            return;
        }

        $item = $items[$currentPosition];

        unset($items[$currentPosition]);

        $items = array_values($items);

        array_splice($items, max(0, min($position, count($items))), 0, [$item]);

        $this->media = $items;
    }

    #[Computed]
    public function selectedItems(): array
    {
        if ($this->multiple) {
            return is_array($this->media) ? $this->media : [];
        }

        return is_array($this->media) && $this->isMediaItem($this->media)
            ? [$this->media]
            : [];
    }

    #[Computed]
    public function buttonText(): string
    {
        return match ($this->type) {
            'image' => $this->multiple ? __('Attach Images') : __('Attach Image'),
            'video' => $this->multiple ? __('Attach Videos') : __('Attach Video'),
            'audio' => $this->multiple ? __('Attach Audios') : __('Attach Audio'),
            'document' => $this->multiple ? __('Attach Files') : __('Attach File'),
            default => __('Attach Media'),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function selectionForLibrary(?int $replaceIndex = null): array
    {
        if ($replaceIndex !== null && isset($this->selectedItems[$replaceIndex])) {
            return [$this->selectedItems[$replaceIndex]];
        }

        return $this->selectedItems;
    }

    private function selectionLimit(?int $replaceIndex = null): int
    {
        if ($replaceIndex !== null || ! $this->multiple) {
            return 1;
        }

        return $this->max;
    }

    private function normalizeMediaState(): void
    {
        if ($this->multiple) {
            if ($this->media === null || $this->media === '') {
                $this->media = [];

                return;
            }

            if ($this->isMediaItem($this->media)) {
                $this->media = [$this->normalizeMediaItem($this->media)];

                return;
            }

            if (! is_array($this->media)) {
                $this->media = [];

                return;
            }

            $this->media = array_slice(array_values(array_filter(array_map(
                $this->normalizeMediaItem(...),
                $this->media,
            ))), 0, $this->max);

            return;
        }

        if ($this->media === [] || $this->media === '') {
            $this->media = null;

            return;
        }

        if (is_array($this->media) && array_is_list($this->media)) {
            $this->media = $this->normalizeMediaItem($this->media[0] ?? null);

            return;
        }

        $this->media = $this->normalizeMediaItem($this->media);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMediaItem(mixed $item): ?array
    {
        if (! is_array($item) || ! $this->isMediaItem($item)) {
            return null;
        }

        return [
            'id' => $item['id'],
            'preview' => $item['preview'] ?? null,
            'filename' => $item['filename'] ?? null,
            'alt_text' => $item['alt_text'] ?? null,
            'mime_type' => $item['mime_type'] ?? null,
            'thumbnail' => $item['thumbnail'] ?? null,
            'icon' => $item['icon'] ?? null,
            'size' => $item['size'] ?? null,
            'duration' => $item['duration'] ?? null,
            'dimensions' => $item['dimensions'] ?? null,
            'created_at' => $item['created_at'] ?? null,
        ];
    }

    private function isMediaItem(mixed $item): bool
    {
        return is_array($item) && array_key_exists('id', $item);
    }

    private function normalizedType(): ?string
    {
        if (in_array($this->type, [null, '', 'any'], true)) {
            return null;
        }

        return $this->type;
    }
};
?>

<div wire:key="{{ $this->targetKey() }}">
    <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-3">
            @if($label)
                <flux:label>{{ $label }}</flux:label>
            @endif

            @if($multiLocale)
                <flux:tooltip content="{{ __('Change language') }}">
                    <flux:badge size="sm" class="text-xs py-0.5!" as="button" wire:click="$dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
                </flux:tooltip>
            @endif
        </div>

        @if($note)
            <flux:subheading>{{ $note }}</flux:subheading>
        @endif
    </div>

    <flux:card class="block appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-4 text-base shadow-xs disabled:border-b-zinc-200 disabled:shadow-none dark:border-white/10 dark:bg-white/10 dark:disabled:border-white/5 dark:shadow-none sm:text-sm">
        @if($this->selectedItems === [])
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <flux:button variant="filled" wire:click="openLibrary">{{ $this->buttonText }}</flux:button>
                <div class="space-y-1 grow">
                    <flux:heading size="sm">{{ __('No media selected') }}</flux:heading>
                    <flux:subheading>
                        {{ $multiple
                            ? __('Choose up to :max items from the media library.', ['max' => $max])
                            : __('Choose one item from the media library.') }}
                    </flux:subheading>
                </div>
            </div>
        @else
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <flux:badge size="sm">
                            {{ trans_choice(':count item selected|:count items selected', count($this->selectedItems), ['count' => count($this->selectedItems)]) }}
                        </flux:badge>

                        @if($multiple)
                            <flux:subheading>{{ __('Drag items to reorder them.') }}</flux:subheading>
                        @endif
                    </div>

                    @if($multiple && count($this->selectedItems) < $max)
                        <flux:button variant="ghost" wire:click="openLibrary">{{ __('Add media') }}</flux:button>
                    @endif
                </div>

                <div class="grid gap-3" @if($multiple) wire:sort="reorderMedia" @endif>
                    @foreach($this->selectedItems as $index => $item)
                        <div
                            wire:key="{{ $this->targetKey() }}-{{ $item['id'] }}-{{ $index }}"
                            @if($multiple) wire:sort:item="{{ $item['id'] }}" @endif
                            class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 dark:border-white/10 dark:bg-white/5"
                        >
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="flex min-w-0 gap-4">
                                    @if($multiple)
                                        <button
                                            type="button"
                                            wire:sort:handle
                                            class="mt-1 inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-zinc-200 px-3 text-xs font-medium text-zinc-500 hover:bg-zinc-100 dark:border-white/10 dark:text-zinc-400 dark:hover:bg-white/10"
                                        >
                                            {{ __('Move') }}
                                        </button>
                                    @endif

                                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-white/10 dark:bg-black/10">
                                        @if(! empty($item['preview']))
                                            <img src="{{ $item['preview'] }}" alt="{{ $item['alt_text'] ?? $item['filename'] ?? $name }}" class="h-full w-full object-cover" />
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ __('No preview') }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="min-w-0 space-y-1">
                                        <flux:heading size="sm" class="truncate">{{ $item['filename'] ?? __('Untitled media') }}</flux:heading>

                                        @if(! empty($item['alt_text']))
                                            <flux:subheading class="truncate">{{ $item['alt_text'] }}</flux:subheading>
                                        @endif

                                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            @if(! empty($item['mime_type']))
                                                <span>{{ $item['mime_type'] }}</span>
                                            @endif

                                            @if(! empty($item['dimensions']))
                                                <span>{{ __('Size') }}: {{ $item['dimensions'] }}</span>
                                            @endif

                                            @if(! empty($item['duration']))
                                                <span>{{ __('Duration') }}: {{ $item['duration'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 md:shrink-0">
                                    <flux:button variant="ghost" wire:click="openLibrary({{ $index }})">{{ __('Change') }}</flux:button>
                                    <flux:button variant="ghost" wire:click="removeMedia({{ $index }})">{{ __('Remove') }}</flux:button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:card>
</div>