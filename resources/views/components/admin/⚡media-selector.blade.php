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
    public bool $withCaption = false;
    public ?int $replaceIndex = null;

    public bool $showRemoveModal = false;
    public ?int $removeIndex = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $crops = [];

    public function mount(): void
    {
        if ($this->crops === []) {
            $this->crops = ['default' => ['label' => __('Default'), 'w' => 1200, 'h' => 800]];
        }

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

    /**
     * @param  array<int, array<string, mixed>>  $media
     */
    #[On('media-selected')]
    public function handleMediaSelected(string $target, array $media): void
    {
        if ($target !== $this->targetKey()) {
            return;
        }

        $selectedMedia = $this->preserveExistingPivotData(array_values(array_filter(array_map(
            $this->normalizeMediaItem(...),
            $media,
        ))));

        if ($this->multiple) {
            $items = $this->selectedItems();

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

    /**
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    private function preserveExistingPivotData(array $incoming): array
    {
        $existing = [];

        foreach ($this->selectedItems() as $item) {
            if (isset($item['id'])) {
                $existing[$item['id']] = [
                    'crop' => $item['crop'] ?? [],
                    'metadata' => $item['metadata'] ?? [],
                ];
            }
        }

        return array_map(function (array $item) use ($existing): array {
            $previous = isset($item['id']) ? ($existing[$item['id']] ?? null) : null;

            if ($previous === null) {
                return $item;
            }

            if (empty($item['crop']) && ! empty($previous['crop'])) {
                $item['crop'] = $previous['crop'];
            }

            if (empty($item['metadata']) && ! empty($previous['metadata'])) {
                $item['metadata'] = $previous['metadata'];
            }

            return $item;
        }, $incoming);
    }

    public function confirmRemove(?int $index = null): void
    {
        $this->removeIndex = $index;
        $this->showRemoveModal = true;
    }

    public function removeConfirmed(): void
    {
        $this->removeMedia($this->removeIndex);

        $this->showRemoveModal = false;
        $this->removeIndex = null;
    }

    public function setCaption(int $index, string $caption): void
    {
        $items = $this->selectedItems();

        if (! isset($items[$index])) {
            return;
        }

        $metadata = is_array($items[$index]['metadata'] ?? null) ? $items[$index]['metadata'] : [];
        $caption = mb_trim($caption);

        if ($caption === '') {
            unset($metadata['caption']);
        } else {
            $metadata['caption'] = $caption;
        }

        $items[$index]['metadata'] = $metadata;

        $this->media = $this->multiple ? array_values($items) : $items[$index];
    }

    public function removeMedia(?int $index = null): void
    {
        if (! $this->multiple) {
            $this->media = null;

            return;
        }

        $items = $this->selectedItems();

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

        $items = $this->selectedItems();
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

    /**
     * @param  array<string, mixed>  $crop
     */
    public function setCrop(int $index, string $variant, array $crop): void
    {
        $items = $this->selectedItems();

        if (! isset($items[$index]) || ! array_key_exists($variant, $this->crops)) {
            return;
        }

        $existing = is_array($items[$index]['crop'] ?? null) ? $items[$index]['crop'] : [];
        $existing[$variant] = $crop;
        $items[$index]['crop'] = $existing;

        $this->media = $this->multiple ? array_values($items) : $items[$index];
    }

    /**
     * @param  array<string, mixed>  $crops
     */
    public function setCrops(int $index, array $crops): void
    {
        $items = $this->selectedItems();

        if (! isset($items[$index])) {
            return;
        }

        $existing = is_array($items[$index]['crop'] ?? null) ? $items[$index]['crop'] : [];

        foreach ($crops as $variant => $crop) {
            if (array_key_exists($variant, $this->crops) && is_array($crop)) {
                $existing[$variant] = $crop;
            }
        }

        $items[$index]['crop'] = $existing;

        $this->media = $this->multiple ? array_values($items) : $items[$index];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function selectedItems(): array
    {
        if ($this->multiple) {
            return is_array($this->media) ? $this->media : [];
        }

        if (is_array($this->media) && $this->isMediaItem($this->media)) {
            return [$this->media];
        }

        if (is_array($this->media) && array_is_list($this->media) && $this->isMediaItem($this->media[0] ?? null)) {
            return [$this->media[0]];
        }

        return [];
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
        if ($replaceIndex !== null && isset($this->selectedItems()[$replaceIndex])) {
            return [$this->selectedItems()[$replaceIndex]];
        }

        return $this->selectedItems();
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
            'source' => $item['source'] ?? null,
            'preview' => $item['preview'] ?? null,
            'crop_src' => $item['crop_src'] ?? null,
            'filename' => $item['filename'] ?? null,
            'alt_text' => $item['alt_text'] ?? null,
            'mime_type' => $item['mime_type'] ?? null,
            'thumbnail' => $item['thumbnail'] ?? null,
            'icon' => $item['icon'] ?? null,
            'size' => $item['size'] ?? null,
            'duration' => $item['duration'] ?? null,
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'dimensions' => $item['dimensions'] ?? null,
            'created_at' => $item['created_at'] ?? null,
            'crop' => $item['crop'] ?? [],
            'metadata' => $item['metadata'] ?? [],
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

<div wire:key="{{ $this->targetKey() }}" x-data="mediaCropper($wire)">
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

    <flux:card class="block appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-4 text-base shadow-xs disabled:border-b-zinc-200 disabled:shadow-none dark:border-white/10 dark:bg-white/10 dark:disabled:border-white/5 dark:shadow-none md:text-sm">
        @if($this->selectedItems() === [])
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
                <div class="grid grid-cols-1 gap-3" @if($multiple) wire:sort="reorderMedia" @endif>
                    @foreach($this->selectedItems() as $index => $item)
                        @php
                            $primaryKey = array_key_first($crops);
                            $primaryDef = $primaryKey !== null ? ($crops[$primaryKey] ?? []) : [];
                            $storedCrop = $primaryKey !== null ? ($item['crop'][$primaryKey] ?? null) : null;
                            $isImage = ($item['icon'] ?? null) === 'photo';
                            $isSvg = ($item['mime_type'] ?? null) === 'image/svg+xml';
                            $isCroppable = $isImage && ! $isSvg;
                            $hasPreview = $isImage || ! empty($item['thumbnail']);
                            $extension = strtoupper(pathinfo((string) ($item['filename'] ?? ''), PATHINFO_EXTENSION));

                            $filename = $item['filename'] ?? __('Untitled media');
                            $tailLength = 7;
                            $nameHead = mb_strlen($filename) > 16 ? mb_substr($filename, 0, -$tailLength) : $filename;
                            $nameTail = mb_strlen($filename) > 16 ? mb_substr($filename, -$tailLength) : '';

                            $previewSrc = $item['preview'] ?? null;
                            if ($isCroppable && $storedCrop && ! empty($item['source'])) {
                                $previewSrc = route('image.show', [
                                    'options' => sprintf(
                                        'w=%d,h=%d,crop=%d-%d-%d-%d,q=%d,fm=%s',
                                        $storedCrop['w'] ?? ($primaryDef['w'] ?? 1200),
                                        $storedCrop['h'] ?? ($primaryDef['h'] ?? 630),
                                        $storedCrop['crop_w'] ?? 0,
                                        $storedCrop['crop_h'] ?? 0,
                                        $storedCrop['crop_x'] ?? 0,
                                        $storedCrop['crop_y'] ?? 0,
                                        $storedCrop['q'] ?? 80,
                                        $storedCrop['fm'] ?? 'jpg',
                                    ),
                                    'path' => $item['source'],
                                ]);
                            }
                        @endphp
                        <div
                            wire:key="{{ $this->targetKey() }}-{{ $item['id'] }}-{{ $index }}"
                            @if($multiple) wire:sort:item="{{ $item['id'] }}" @endif
                            class="flex flex-col gap-4 rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 dark:border-white/10 dark:bg-white/5 md:flex-row md:items-start md:gap-4"
                        >
                            <div class="flex flex-col md:flex-row items-center gap-3 md:shrink-0">
                                @if($multiple)
                                    <button
                                        type="button"
                                        wire:sort:handle
                                        aria-label="{{ __('Drag to reorder') }}"
                                        class="shrink-0 cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                    >
                                        <flux:icon name="chevron-up-down" class="size-6" />
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    wire:click="openLibrary({{ $index }})"
                                    title="{{ __('Change') }}"
                                    style="aspect-ratio: {{ $isImage ? (($primaryDef['w'] ?? 16).' / '.($primaryDef['h'] ?? 9)) : '1 / 1' }}"
                                    class="group relative h-32 w-full md:w-40 shrink-0 overflow-hidden rounded-lg border border-black/80 bg-black/80 dark:border-white/10 dark:bg-white/80"
                                >
                                    @if($hasPreview && $previewSrc)
                                        <img src="{{ $previewSrc }}" alt="{{ $item['alt_text'] ?? $item['filename'] ?? $name }}" class="size-full object-contain" />
                                    @else
                                        <div class="flex size-full flex-col items-center justify-center gap-1 text-zinc-500 dark:text-zinc-400">
                                            <flux:icon name="{{ $item['icon'] ?? 'document' }}" class="size-8" />
                                            @if($extension !== '')
                                                <span class="text-xs font-semibold uppercase">{{ $extension }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                        <flux:icon name="arrows-right-left" class="size-6 text-white" />
                                    </span>
                                </button>
                            </div>

                            <div class="min-w-0 space-y-0.5 md:flex-1 md:pt-3">
                                <flux:heading size="sm" class="flex max-w-11/12 overflow-hidden" title="{{ $filename }}">
                                    <span class="min-w-0 truncate">{{ $nameHead }}</span>
                                    @if($nameTail !== '')
                                        <span class="shrink-0 whitespace-nowrap">{{ $nameTail }}</span>
                                    @endif
                                </flux:heading>

                                @if(! empty($item['width']) && ! empty($item['height']))
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Original') }}: {{ $item['width'] }} × {{ $item['height'] }}</flux:text>
                                @endif

                                @if(! empty($item['duration']))
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Duration') }}: {{ gmdate('H:i:s', (int) $item['duration']) }}</flux:text>
                                @endif

                                @if($isCroppable)
                                    @foreach($crops as $variantKey => $cropDef)
                                        @php
                                            $variantCrop = $item['crop'][$variantKey] ?? null;
                                            $variantW = $variantCrop['crop_w'] ?? null;
                                            $variantH = $variantCrop['crop_h'] ?? null;
                                        @endphp
                                        @if($variantW && $variantH)
                                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $cropDef['label'] ?? ucfirst($variantKey) }}: {{ $variantW }} × {{ $variantH }}</flux:text>
                                        @endif
                                    @endforeach
                                @endif

                                @if($withCaption)
                                    <div class="pt-2">
                                        <flux:input
                                            size="sm"
                                            wire:key="{{ $this->targetKey() }}-caption-{{ $item['id'] }}"
                                            value="{{ data_get($item, 'metadata.caption', '') }}"
                                            x-on:blur="$wire.setCaption({{ $index }}, $event.target.value)"
                                            placeholder="{{ __('Add a caption') }}" />
                                    </div>
                                @endif
                            </div>

                            <flux:button.group class="md:shrink-0">
                                <flux:button variant="filled" icon="arrows-right-left" square tooltip="{{ __('Change') }}" wire:click="openLibrary({{ $index }})" />
                                @if($isCroppable)
                                    <flux:button variant="filled" icon="scissors" square tooltip="{{ __('Crop') }}" x-on:click="start({{ $index }}, @js($item), @js($crops))" />
                                @endif
                                <flux:button variant="filled" icon="x-mark" square tooltip="{{ __('Remove') }}" wire:click="confirmRemove({{ $index }})" />
                            </flux:button.group>
                        </div>
                    @endforeach
                </div>
            </div>
            @if($multiple && count($this->selectedItems()) < $max)
                <flux:button variant="filled" wire:click="openLibrary" class="mt-4">{{ __('Add more media') }}</flux:button>
            @endif
        @endif
    </flux:card>

    @php
        $removeItem = $removeIndex !== null ? ($this->selectedItems()[$removeIndex] ?? null) : null;
    @endphp
    <flux:modal wire:model.self="showRemoveModal" class="min-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove media?') }}</flux:heading>
                <flux:text class="mt-2">
                    @if($removeItem)
                        {{ __('":name" will be removed from this field. This does not delete it from the media library.', ['name' => $removeItem['filename'] ?? __('this item')]) }}
                    @else
                        {{ __('This item will be removed from this field. This does not delete it from the media library.') }}
                    @endif
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="removeConfirmed">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <div
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="close()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
    >
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col gap-4 rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800" x-on:click.outside="close()">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Edit image crop') }}</flux:heading>
                <flux:button icon="x-mark" variant="ghost" size="sm" square x-on:click="close()" />
            </div>

            <div class="flex items-center gap-1 border-b border-zinc-200 dark:border-white/10" x-show="variants.length > 1">
                <template x-for="v in variants" :key="v.key">
                    <button
                        type="button"
                        x-on:click="switchTo(v.key)"
                        x-text="v.label"
                        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                        :class="v.key === activeVariant
                            ? 'border-zinc-900 text-zinc-900 dark:border-white dark:text-white'
                            : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'"
                    ></button>
                </template>
            </div>

            <div class="w-full overflow-hidden rounded-lg bg-black/10 dark:bg-black/30">
                <template x-if="open">
                    <img x-ref="cropImage" :src="item?.crop_src" alt="" class="block w-full" />
                </template>
            </div>

            <div class="flex items-center justify-between gap-2">
                <flux:button variant="primary" type="button" x-on:click="apply()">{{ __('Update') }}</flux:button>
                <div class="text-right leading-tight">
                    <flux:text class="text-zinc-500 dark:text-zinc-400"><span x-text="dims.w"></span> × <span x-text="dims.h"></span></flux:text>
                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Ratio') }}: <span x-text="ratioLabel"></span></flux:text>
                </div>
            </div>
        </div>
    </div>
</div>