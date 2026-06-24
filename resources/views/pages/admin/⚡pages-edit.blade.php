<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Media;
use App\Models\Page;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
    public Page $page;

    /**
     * @var array<string, string>
     */
    public array $title = [];

    /**
     * @var array<string, string>
     */
    public array $description = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    public array $og_image = [];

    /**
     * @var array<string, string>
     */
    public array $slugs = [];

    /**
     * @var array<int|string, array{id: string, type: string, position: int, content: array<string, mixed>}>
     */
    public array $blocks = [];

    public ?string $selectedBlock = null;

    public ?int $insertPosition = null;

    public bool $showPreview = false;

    public ?string $previewToken = null;

    public PageStatus $status;

    /**
     * @var array<int, string>
     */
    public array $publishedLocales = [];

    public ?CarbonImmutable $published_at = null;

    #[Url(except: 'en')]
    public string $locale;

    /**
     * @var array<string, mixed>
     */
    public array $activeLocales = [];

    public function mount(Page $page): void
    {
        $page->load('translations', 'media', 'blocks');
        $this->page = $page;
        $this->blocks = $this->withBlockDefaults($page->getBlocksArray());
        $this->status = $page->computed_status;
        $this->published_at = $page->published_at;
        $this->title = $page->translationsFor('title');
        $this->description = $page->translationsFor('description');
        $this->slugs = $page->getSlugsArray();
        $this->locale = app()->getLocale();
        $this->activeLocales = resolve('localization')->getActiveLocales();
        $this->publishedLocales = array_values(array_intersect($page->published_locales, array_keys($this->activeLocales)));
        $this->og_image = $this->mediaForRole('og_image');

        foreach (array_keys($this->activeLocales) as $locale) {
            $this->og_image[$locale] ??= [];
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function mediaForRole(string $role): array
    {
        return $this->page->media
            ->filter(fn (Media $media): bool => $media->pivot->role === $role)
            ->groupBy(fn (Media $media): string => $media->pivot->locale)
            ->map(fn (Collection $items): array => $items
                ->sortBy(fn (Media $media): int => $media->pivot->position)
                ->map(fn (Media $media): array => $this->mediaToItem($media))
                ->values()
                ->all()
            )
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaToItem(Media $media): array
    {
        return [
            'id' => $media->id,
            'source' => $media->source,
            'preview' => $media->preview,
            'crop_src' => $media->cropSrc,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'thumbnail' => $media->thumbnail,
            'icon' => $media->type->icon(),
            'size' => $media->size,
            'duration' => $media->duration,
            'width' => $media->width,
            'height' => $media->height,
            'dimensions' => $media->dimensions,
            'created_at' => $media->created_at->toDateTimeString(),
            'crop' => $media->pivot->crop ?? [],
            'metadata' => $media->pivot->metadata ?? [],
        ];
    }

    public function update(UpdatePageAction $action): void
    {
        $this->og_image = $this->normalizeMediaInput($this->og_image);
        $this->normalizeBlockAnchors();

        /** @var array<string, array<int, mixed>> */
        $rules = [
            'status' => ['required', Rule::enum(PageStatus::class)],
            'published_at' => $this->status === PageStatus::SCHEDULED
                ? ['required', 'date', 'after:now']
                : ['nullable', 'date'],
            'publishedLocales' => ['array'],
            'publishedLocales.*' => ['string', Rule::in(array_keys($this->activeLocales))],
            'blocks' => ['array'],
            'blocks.*.type' => ['required', 'string', Rule::in(BlockType::values())],
            'blocks.*.content' => ['array'],
            'og_image' => ['array'],
            'og_image.*' => ['array'],
            'og_image.*.*' => ['array'],
            'og_image.*.*.id' => ['required', 'integer', 'exists:media,id'],
            'og_image.*.*.metadata' => ['nullable', 'array'],
            'og_image.*.*.metadata.caption' => ['nullable', 'string', 'max:500'],
            'og_image.*.*.metadata.alt' => ['nullable', 'string', 'max:255'],
        ];

        foreach (array_keys($this->activeLocales) as $locale) {
            $isLive = in_array($locale, $this->publishedLocales, true);

            $slugUnique = Rule::unique('slugs', 'slug')->where('locale', $locale)
                ->where(function (Builder $query): void {
                    $query->whereNot(function (Builder $q): void {
                        $q->where('sluggable_id', $this->page->id)
                            ->where('sluggable_type', 'page');
                    });
                });

            $rules["title.$locale"] = $isLive ? ['required', 'string', 'min:3'] : ['nullable', 'string'];
            $rules["description.$locale"] = ['nullable', 'string', 'max:160'];
            $rules["slugs.$locale"] = $isLive ? ['required', 'string', 'min:3', $slugUnique] : ['nullable', 'string', $slugUnique];
        }

        $messages = [
            'publishedLocales.*.in' => __('Choose a language that is enabled in your site settings.'),
            'published_at.required' => __('Choose a date to schedule this page.'),
            'published_at.after' => __('The scheduled date must be in the future.'),
        ];

        $attributes = [
            'publishedLocales.*' => __('language'),
            'published_at' => __('scheduled date'),
        ];

        try {
            $validated = $this->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $this->revealErrors($e);

            Flux::toast(__('Please review the highlighted fields before saving.'), variant: 'danger');

            throw $e;
        }

        $action->handle($this->page, [
            ...Arr::except($validated, ['publishedLocales', 'blocks']),
            'blocks' => $this->blocks,
            'og_image' => $this->og_image,
            'metadata' => [
                ...($this->page->metadata ?? []),
                'published_locales' => array_values($validated['publishedLocales'] ?? []),
            ],
        ]);

        Flux::toast(__('Page content has been updated.'), variant: 'success');
    }

    /**
     * @param  array<string, mixed>  $media
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normalizeMediaInput(array $media): array
    {
        $result = [];

        foreach ($media as $locale => $value) {
            if (is_array($value) && array_is_list($value)) {
                $result[$locale] = $value;
            } elseif (is_array($value) && $value !== []) {
                $result[$locale] = [$value];
            } else {
                $result[$locale] = [];
            }
        }

        return $result;
    }

    public function updated(string $name): void
    {
        if (preg_match('/^blocks\.[^.]+\.content\.(ctaPrimary|ctaSecondary)\.link\.type$/', $name) === 1) {
            $path = Str::after($name, 'blocks.');
            data_set($this->blocks, Str::beforeLast($path, '.type').'.value', '');
        }

    }

    /**
     * @param  array<int|string, array{id: string, type: string, position: int, content: array<string, mixed>}>  $blocks
     * @return array<int|string, array{id: string, type: string, position: int, content: array<string, mixed>}>
     */
    private function withBlockDefaults(array $blocks): array
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

    private function normalizeBlockAnchors(): void
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

    #[On('change-locale')]
    public function changeLocale(): void
    {
        $codes = array_keys($this->activeLocales);
        $index = array_search($this->locale, $codes, true);

        $this->locale = $codes[($index + 1) % count($codes)] ?? $this->locale;
    }

    private function revealErrors(ValidationException $e): void
    {
        $codes = array_keys($this->activeLocales);

        foreach (array_keys($e->errors()) as $key) {
            $locale = str($key)->afterLast('.')->value();

            if (in_array($locale, $codes, true)) {
                $this->locale = $locale;

                return;
            }
        }
    }

    public function openBlockPicker(?int $position = null): void
    {
        $this->insertPosition = $position;

        Flux::modal('block-picker')->show();
    }

    public function addBlock(string $type): void
    {
        $blockType = BlockType::tryFrom($type);

        if ($blockType === null) {
            return;
        }

        $id = 'new-'.Str::uuid()->toString();

        $blocks = array_values($this->blocks);
        $position = $this->insertPosition ?? count($blocks);

        array_splice($blocks, $position, 0, [[
            'id' => $id,
            'type' => $blockType->value,
            'position' => $position,
            'content' => $blockType->defaultContent(),
        ]]);

        $this->blocks = collect($blocks)
            ->mapWithKeys(fn (array $block, int $index): array => [
                $block['id'] => [...$block, 'position' => $index],
            ])
            ->all();

        $this->insertPosition = null;

        Flux::modal('block-picker')->close();
    }

    public function addAccordionItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $this->blocks[$id]['content']['items'][] = ['id' => (string) Str::uuid(), 'title' => [], 'body' => []];
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

    public function addTestimonialItem(string $id): void
    {
        if (! isset($this->blocks[$id])) {
            return;
        }

        $this->blocks[$id]['content']['items'][] = ['id' => (string) Str::uuid(), 'quote' => [], 'author' => [], 'role' => [], 'avatar' => null, 'rating' => 0];
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

    public function preview(): void
    {
        $this->previewToken = (string) Str::uuid();

        Cache::put($this->previewCacheKey($this->previewToken), [
            'page_id' => $this->page->id,
            'locale' => $this->locale,
            'title' => $this->title[$this->locale] ?? '',
            'description' => $this->description[$this->locale] ?? '',
            'blocks' => array_values($this->blocks),
        ], now()->addMinutes(30));

        $this->showPreview = true;
    }

    private function previewCacheKey(string $token): string
    {
        return "page-preview:{$this->page->id}:".auth()->id().":{$token}";
    }

    #[Computed]
    public function isHomePage(): bool
    {
        return $this->page->id === SettingsService::current()->homePageId();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function pageLinkOptions(): array
    {
        return Page::query()
            ->with('translations')
            ->get()
            ->sortBy(fn (Page $page): string => $page->title)
            ->mapWithKeys(fn (Page $page): array => [$page->id => $page->title])
            ->all();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Edit').' '.$this->page->title)
            ->layout('layouts::admin');
    }
};
?>

<div>
<form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid grid-cols-1 md:grid-cols-5 gap-10 items-stretch">
    <div class="md:col-span-3 min-w-0">
        <div class="space-y-6 mb-10 md:col-span-2">
            
            <flux:fieldset class="pb-6">
                <div class="flex items-center justify-between">
                    <flux:legend>{{ __('Content') }}</flux:legend>
                    @if ($this->isHomePage)
                    <div>
                        <flux:badge color="sky" size="sm" icon="home">{{ __('Homepage') }}</flux:badge>
                    </div>
                    @endif
                </div>
                <flux:description>{{ __('Build and customize this page using flexible content blocks.') }}</flux:description>

                <div class="mt-6">
                    <div wire:sort="reorderBlocks" class="flex flex-col gap-3 mb-6">
                        @foreach ($blocks as $index => $block)
                            @php($blockType = \App\Enums\BlockType::from($block['type']))
                            <flux:card
                                size="sm"
                                class="p-0! overflow-hidden"
                                wire:key="block-{{ $block['id'] }}"
                                wire:sort:item="{{ $block['id'] }}"
                                x-data="{ open: {{ str_starts_with((string) $block['id'], 'new-') ? 'true' : 'false' }} }"
                                x-on:blocks-toggle-all.window="open = $event.detail">
                                <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                                    <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                                        <flux:icon name="bars-3" variant="mini" />
                                    </div>

                                    <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                                        <flux:heading class="truncate" x-text="window.blockTitle($wire.blocks[@js((string) $index)], $wire.locale, @js($blockType->label()))">{{ $blockType->editorTitle($block['content'], $locale) }}</flux:heading>
                                    </button>

                                    <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                                        <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                            <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                            <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                                        </flux:button>

                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button size="sm" icon="ellipsis-horizontal" variant="subtle" square :tooltip="__('Options')" />
                                            <flux:menu>
                                                <flux:menu.item icon="arrows-pointing-out" x-show="!open" x-on:click="$dispatch('blocks-toggle-all', true)">{{ __('Expand all') }}</flux:menu.item>
                                                <flux:menu.item icon="arrows-pointing-in" x-show="open" x-on:click="$dispatch('blocks-toggle-all', false)">{{ __('Collapse all') }}</flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="arrow-up" wire:click="openBlockPicker({{ $loop->index }})">{{ __('Add above') }}</flux:menu.item>
                                                <flux:menu.item icon="arrow-down" wire:click="openBlockPicker({{ $loop->index + 1 }})">{{ __('Add below') }}</flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmRemoveBlock('{{ $block['id'] }}')">{{ __('Delete') }}</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>

                                <div class="p-4" x-show="open" x-collapse x-cloak>
                                    @includeIf($blockType->adminView(), ['block' => $block, 'locale' => $locale, 'multiLocale' => count($activeLocales) > 1, 'index' => $index, 'pageOptions' => $this->pageLinkOptions])

                                    @if ($blockType->hasAnchor())
                                        <div class="mt-6">
                                            <flux:input
                                                wire:model.lazy="blocks.{{ $index }}.content.anchor"
                                                label="{{ __('Anchor') }}"
                                                description="{{ __('Link directly to this block with #your-anchor.') }}"
                                                icon="hashtag"
                                                placeholder="contact" />
                                        </div>
                                    @endif
                                </div>
                            </flux:card>
                        @endforeach
                    </div>

                    <flux:button icon="plus" variant="filled" wire:click="openBlockPicker">{{ __('Add block') }}</flux:button>

                    <flux:modal name="block-picker" class="w-full md:max-w-5xl">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Add a block') }}</flux:heading>
                                <flux:text class="mt-2">{{ __('Choose a block to add to your page.') }}</flux:text>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                @foreach (\App\Enums\BlockType::cases() as $pickerType)
                                    <button type="button" wire:click="addBlock('{{ $pickerType->value }}')" class="flex flex-col gap-2 rounded-lg border border-zinc-200 dark:border-white/10 p-4 text-left transition hover:border-zinc-300 hover:bg-zinc-50 dark:hover:border-white/20 dark:hover:bg-white/5">
                                        <flux:icon name="{{ $pickerType->icon() }}" class="size-6 text-zinc-400" />
                                        <flux:heading size="sm">{{ $pickerType->label() }}</flux:heading>
                                        <flux:text size="sm">{{ $pickerType->description() }}</flux:text>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </flux:modal>

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
                                <flux:button variant="danger" wire:click="removeBlock">{{ __('Remove') }}</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                </div>
            </flux:fieldset>

            <flux:separator />

            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('SEO Settings') }}</flux:legend>
                <flux:description>{{ __('Manage how this page appears in search results.') }}</flux:description>

                <div class="flex flex-col gap-6 mt-6">
                    <x-forms.input-translated name="title" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Title') }}" />
                    <x-forms.url-translated name="slugs" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Web Address') }}" :readonly="$this->isHomePage" :note="$this->isHomePage ? __('Served at /. Its URL redirects here.') : ''" />
                    <x-forms.textarea-translated name="description" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Description') }}" />
                    <livewire:admin.media-selector wire:model="og_image.{{ $locale }}" type="image" name="og_image" :crops="['desktop' => ['label' => __('Desktop'), 'w' => 1200, 'h' => 700], 'mobile' => ['label' => __('Mobile'), 'w' => 800, 'h' => 800]]" label="{{ __('Open Graph Image') }}" />
                </div>
            </flux:fieldset>
        </div>
    </div>
    <div class="mb-10 md:mb-0 md:col-span-2">
        <flux:card class="flex flex-col gap-6 md:sticky md:top-24">

            <flux:accordion>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <div class="flex items-center justify-between">
                            {{ __('Status') }}
                            <flux:text class="{{ $status->textColor() }}">{{ $status->label() }}</flux:text>
                        </div>
                    </flux:accordion.heading>

                    <flux:accordion.content class="mt-3 flex flex-col gap-6">
                        <flux:select variant="listbox" placeholder="{{ __('Choose status') }}" wire:model.live="status">
                            @foreach (PageStatus::cases() as $statusOption)
                                <flux:select.option value="{{ $statusOption->value }}">
                                    {{ $statusOption->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <div x-cloak x-show="$wire.status === '{{ PageStatus::SCHEDULED->value }}'">
                            <flux:date-picker wire:model="published_at" label="{{ __('Publish on') }}" selectable-header />
                        </div>
                    </flux:accordion.content>
                </flux:accordion.item>

                @if (count($activeLocales) > 1)
                    <flux:accordion.item>
                        <flux:accordion.heading>
                            <div class="flex items-center justify-between">
                                {{ __('Languages') }}
                                <flux:text>{{ count($publishedLocales) }} {{ __('Live') }}</flux:text>
                            </div>
                        </flux:accordion.heading>

                        <flux:accordion.content class="mt-3 max-h-40 overflow-y-auto">
                            <flux:checkbox.group wire:model.live="publishedLocales">
                                @foreach ($activeLocales as $code => $meta)
                                    <flux:checkbox
                                        label="{{ $meta['name'] ?? $code }}"
                                        value="{{ $code }}"
                                        :disabled="count($publishedLocales) === 1 && in_array($code, $publishedLocales, true)"
                                        wire:key="locale-{{ $code }}" />
                                @endforeach
                            </flux:checkbox.group>
                        </flux:accordion.content>
                    </flux:accordion.item>
                @endif
            </flux:accordion>

            <flux:button wire:click="preview" icon="eye" variant="filled" class="w-full">
                {{ __('Preview') }}
            </flux:button>

            <div class="grid grid-cols-2 gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
                <flux:button wire:navigate href="{{ route('admin.pages-index') }}" icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
            </div>

            <flux:text size="sm">
                {{ __('Last edited') }} {{ $page->updated_at?->diffForHumans() ?? __('Never') }}
            </flux:text>
        </flux:card>
    </div>
</form>

@if ($showPreview)
    <div
        class="fixed inset-0 z-50 flex flex-col bg-zinc-100 dark:bg-zinc-900"
        x-data="{ device: 'desktop', widths: { desktop: '100%', tablet: '768px', mobile: '375px' } }">
        <div class="flex items-center justify-between gap-4 border-b border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-800 px-4 py-2">
            <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="$set('showPreview', false)">
                {{ __('Close') }}
            </flux:button>

            <div class="flex items-center gap-1">
                <flux:button size="sm" variant="subtle" icon="computer-desktop" x-on:click="device = 'desktop'" x-bind:data-active="device === 'desktop'" class="data-[active=true]:text-accent" :tooltip="__('Desktop')" />
                <flux:button size="sm" variant="subtle" icon="device-tablet" x-on:click="device = 'tablet'" x-bind:data-active="device === 'tablet'" class="data-[active=true]:text-accent" :tooltip="__('Tablet')" />
                <flux:button size="sm" variant="subtle" icon="device-phone-mobile" x-on:click="device = 'mobile'" x-bind:data-active="device === 'mobile'" class="data-[active=true]:text-accent" :tooltip="__('Mobile')" />
            </div>

            <flux:text size="sm" class="hidden sm:block">{{ __('Preview') }}</flux:text>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="mx-auto h-full transition-[width] duration-200" x-bind:style="'width: ' + widths[device]">
                <iframe
                    src="{{ route('admin.pages-preview', ['page' => $page, 'token' => $previewToken]) }}"
                    class="w-full h-full rounded-lg border border-zinc-200 dark:border-white/10 bg-white shadow-sm"
                    title="{{ __('Page preview') }}"></iframe>
            </div>
        </div>
    </div>
@endif
</div>

@script
<script>
    window.blockTitle = function (block, locale, fallback) {
        if (! block) {
            return fallback;
        }

        const content = block.content || {};
        const raw = (content.heading || {})[locale] || '';

        const div = document.createElement('div');
        div.innerHTML = raw;
        const text = (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();

        if (! text) {
            return fallback;
        }

        return text.length > 50 ? text.slice(0, 50) + '…' : text;
    };
</script>
@endscript

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.pages-index') }}" wire:navigate>
            {{ __('Pages') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ $page->title }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ Str::limit($page->title, 22) }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.pages-index') }}" wire:navigate>{{ __('Pages') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection