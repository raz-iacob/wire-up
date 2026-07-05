<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Actions\UpdateRecordAction;
use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Services\SettingsService;
use App\Models\Record;
use App\Models\RecordType;
use App\Traits\HasBlockBuilder;
use App\Traits\HasContentEditor;
use Flux\Flux;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    use HasBlockBuilder, HasContentEditor;

    public RecordType $recordType;

    public Record $record;

    public string $defaultLocale;

    /**
     * @var array<string, string>
     */
    public array $title = [];

    /**
     * @var array<string, string>
     */
    public array $description = [];

    /**
     * @var array<string, string>
     */
    public array $slugs = [];

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $media = [];

    /**
     * @var array<int, string>
     */
    public array $categories = [];

    public string $categorySearch = '';

    public bool $noindex = false;

    public bool $showPreview = false;

    public ?string $previewToken = null;

    public function mount(RecordType $recordType, Record $record): void
    {
        $this->authorize('records.'.$recordType->key.'.edit');

        abort_unless($record->record_type_id === $recordType->id, 404);

        $record->load('translations', 'media', 'blocks', 'slugs', 'categories');

        $this->recordType = $recordType;
        $this->record = $record;
        $this->categories = $record->categories->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $this->defaultLocale = resolve('localization')->getDefaultLocale();
        $this->blocks = $this->withBlockDefaults($record->getBlocksArray());
        $this->status = $record->computed_status;
        $this->published_at = $record->published_at;
        $this->title = $record->translationsFor('title');
        $this->description = $record->translationsFor('description');
        $this->slugs = $record->getSlugsArray();
        $this->locale = app()->getLocale();
        $this->activeLocales = resolve('localization')->getActiveLocales();
        $this->publishedLocales = array_values(array_intersect($record->published_locales, array_keys($this->activeLocales)));
        $this->noindex = (bool) ($record->metadata['noindex'] ?? false);
        $this->data = is_array($record->data) ? $record->data : [];

        $this->media['og_image'] = $this->mediaForRole('og_image');

        foreach ($this->recordType->fields as $field) {
            if (FieldType::tryFrom($field['type'])?->isMedia()) {
                $this->media[$field['key']] = $this->mediaForRole($field['key']);
            }
        }

        foreach (array_keys($this->activeLocales) as $locale) {
            $this->media['og_image'][$locale] ??= [];
        }
    }

    /**
     * @return Collection<int, Media>
     */
    protected function editorMedia(): Collection
    {
        return $this->record->media;
    }

    public function createCategory(CreateCategoryAction $action): void
    {
        $name = mb_trim($this->categorySearch);

        if ($name === '') {
            return;
        }

        $category = $action->handle(['name' => [$this->locale => $name]]);

        $this->categories[] = (string) $category->id;
        $this->categorySearch = '';

        unset($this->categoryOptions);
    }

    /**
     * @return EloquentCollection<int, Category>
     */
    #[Computed]
    public function categoryOptions(): EloquentCollection
    {
        return Category::query()
            ->with('translations')
            ->get()
            ->sortBy(fn (Category $category): string => $category->name)
            ->values();
    }

    public function update(UpdateRecordAction $action): void
    {
        foreach ($this->media as $role => $localized) {
            $this->media[$role] = $this->normalizeMediaInput($localized);
        }

        $this->normalizeMoneyInput();
        $this->normalizeBlockAnchors();
        $this->applyPrefills();

        [$rules, $messages, $attributes] = $this->validationRules();

        try {
            $validated = $this->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $this->revealErrors($e);

            Flux::toast(__('Please review the highlighted fields before saving.'), variant: 'danger');

            throw $e;
        }

        $action->handle($this->record, [
            'title' => $this->title,
            'description' => $this->description,
            'data' => $this->cleanData(),
            'status' => $this->status,
            'published_at' => $this->published_at,
            'slugs' => $this->slugs,
            'blocks' => $this->blocks,
            'media' => $this->media,
            'categories' => array_map(intval(...), $this->categories),
            'metadata' => [
                ...($this->record->metadata ?? []),
                'published_locales' => array_values($validated['publishedLocales'] ?? []),
                'noindex' => $this->noindex,
            ],
        ]);

        $this->record->refresh()->load('translations', 'media', 'blocks', 'slugs');

        Flux::toast(__('Changes saved.'), variant: 'success');
    }

    public function preview(): void
    {
        $originalData = $this->data;
        $this->normalizeMoneyInput();
        $data = $this->cleanData();
        $this->data = $originalData;

        $media = [];
        foreach ($this->media as $role => $localized) {
            $media[$role] = $this->normalizeMediaInput($localized);
        }

        $this->previewToken = (string) Str::uuid();

        Cache::put($this->previewCacheKey($this->previewToken), [
            'record_id' => $this->record->id,
            'locale' => $this->locale,
            'title' => $this->title[$this->locale] ?? '',
            'description' => $this->description[$this->locale] ?? '',
            'data' => $data,
            'blocks' => array_values($this->blocks),
            'media' => $media,
        ], now()->addMinutes(30));

        $this->showPreview = true;
    }

    private function previewCacheKey(string $token): string
    {
        return "record-preview:{$this->record->id}:".auth()->id().":{$token}";
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, string>}
     */
    private function validationRules(): array
    {
        $rules = [
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => $this->status === ContentStatus::SCHEDULED
                ? ['required', 'date', 'after:now']
                : ['nullable', 'date'],
            'publishedLocales' => ['array'],
            'publishedLocales.*' => ['string', Rule::in(array_keys($this->activeLocales))],
            'blocks' => ['array'],
            'blocks.*.type' => ['required', 'string', Rule::in(BlockType::values())],
            'blocks.*.content' => ['array'],
            'categories' => ['array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')],
            'noindex' => ['boolean'],
            'media' => ['array'],
            'media.og_image' => ['array'],
            'media.og_image.*' => ['array'],
            'media.og_image.*.*' => ['array'],
            'media.og_image.*.*.id' => ['required', 'integer', 'exists:media,id'],
            'media.og_image.*.*.metadata' => ['nullable', 'array'],
            'media.og_image.*.*.metadata.caption' => ['nullable', 'string', 'max:500'],
            'media.og_image.*.*.metadata.alt' => ['nullable', 'string', 'max:255'],
        ];

        $attributes = [
            'publishedLocales.*' => (string) __('language'),
            'published_at' => (string) __('scheduled date'),
        ];

        foreach (array_keys($this->activeLocales) as $locale) {
            $isLive = in_array($locale, $this->publishedLocales, true);

            $slugUnique = Rule::unique('slugs', 'slug')->where('locale', $locale)
                ->where('base_path', $this->recordType->slug_prefix)
                ->where(function (Builder $query): void {
                    $query->whereNot(function (Builder $q): void {
                        $q->where('sluggable_id', $this->record->id)
                            ->where('sluggable_type', 'record');
                    });
                });

            $rules["title.$locale"] = $isLive ? ['required', 'string', 'min:3'] : ['nullable', 'string'];
            $rules["description.$locale"] = ['nullable', 'string', 'max:160'];
            $rules["slugs.$locale"] = $isLive
                ? ['required', 'string', 'min:3', 'regex:/^[a-z0-9-]+$/', $slugUnique]
                : ['nullable', 'string', 'regex:/^[a-z0-9-]+$/', $slugUnique];

            $attributes["title.$locale"] = (string) __('title');
            $attributes["slugs.$locale"] = (string) __('web address');
            $attributes["description.$locale"] = (string) __('description');
        }

        foreach ($this->recordType->fields as $field) {
            $this->appendFieldRules($field, $rules, $attributes);
        }

        $messages = [
            'publishedLocales.*.in' => __('Choose a language that is enabled in your site settings.'),
            'published_at.required' => __('Choose a date to schedule this record.'),
            'published_at.after' => __('The scheduled date must be in the future.'),
            'media.*.*.required' => __('Add at least one item.'),
            'media.*.*.min' => __('Add at least one item.'),
            'slugs.*.regex' => __('Web addresses can only use lowercase letters, numbers and hyphens.'),
        ];

        return [$rules, $messages, $attributes];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $attributes
     */
    private function appendFieldRules(array $field, array &$rules, array &$attributes): void
    {
        $type = FieldType::tryFrom($field['type']);

        if ($type === null) {
            return;
        }

        $key = $field['key'];
        $required = (bool) ($field['required'] ?? false);
        $translatable = (bool) ($field['translatable'] ?? false);
        $labelMap = is_array($field['label'] ?? null) ? $field['label'] : [];
        $label = (string) ($labelMap[$this->defaultLocale] ?? (array_first($labelMap) ?? $key));

        if ($type->isMedia()) {
            $rules["media.$key"] = ['array'];
            $rules["media.$key.*"] = ['array'];
            $rules["media.$key.*.*"] = ['array'];
            $rules["media.$key.*.*.id"] = ['required', 'integer', 'exists:media,id'];

            if ($required) {
                $locales = $translatable ? ($this->publishedLocales !== [] ? $this->publishedLocales : [$this->defaultLocale]) : [$this->defaultLocale];

                foreach ($locales as $locale) {
                    $rules["media.$key.$locale"] = ['required', 'array', 'min:1'];
                    $attributes["media.$key.$locale"] = $label;
                }
            }

            return;
        }

        $typeRules = match ($type) {
            FieldType::NUMBER, FieldType::MONEY => ['numeric'],
            FieldType::URL => ['url'],
            FieldType::BOOLEAN => ['boolean'],
            FieldType::DATE, FieldType::DATETIME => ['date'],
            FieldType::SELECT => [Rule::in(is_array($field['options'] ?? null) ? $field['options'] : [])],
            default => ['string'],
        };

        if ($translatable) {
            foreach (array_keys($this->activeLocales) as $locale) {
                $isLive = in_array($locale, $this->publishedLocales, true);
                $rules["data.$key.$locale"] = [...($required && $isLive ? ['required'] : ['nullable']), ...$typeRules];
                $attributes["data.$key.$locale"] = $label;
            }

            return;
        }

        $rules["data.$key"] = [...($required ? ['required'] : ['nullable']), ...$typeRules];
        $attributes["data.$key"] = $label;
    }

    private function applyPrefills(): void
    {
        foreach ($this->recordType->fields as $field) {
            $target = $field['prefills'] ?? null;

            if ($target !== 'title' && $target !== 'description') {
                continue;
            }

            $key = $field['key'];
            $translatable = (bool) ($field['translatable'] ?? false);

            foreach (array_keys($this->activeLocales) as $locale) {
                $source = $translatable ? ($this->data[$key][$locale] ?? '') : ($this->data[$key] ?? '');
                $source = is_string($source) ? mb_trim($source) : '';

                if ($source === '') {
                    continue;
                }

                if ($target === 'title' && mb_trim((string) ($this->title[$locale] ?? '')) === '') {
                    $this->title[$locale] = $source;
                }

                if ($target === 'description' && mb_trim((string) ($this->description[$locale] ?? '')) === '') {
                    $this->description[$locale] = \Illuminate\Support\Str::limit(strip_tags($source), 160, '');
                }
            }
        }
    }

    private function normalizeMoneyInput(): void
    {
        foreach ($this->recordType->fields as $field) {
            if (FieldType::tryFrom($field['type']) !== FieldType::MONEY) {
                continue;
            }

            $key = $field['key'];

            if ((bool) ($field['translatable'] ?? false)) {
                foreach (array_keys($this->activeLocales) as $locale) {
                    $this->data[$key][$locale] = $this->stripMoney($this->data[$key][$locale] ?? null);
                }

                continue;
            }

            $this->data[$key] = $this->stripMoney($this->data[$key] ?? null);
        }
    }

    private function stripMoney(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $value);

        return $clean === '' || $clean === null ? null : $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function cleanData(): array
    {
        $clean = [];

        foreach ($this->recordType->fields as $field) {
            $type = FieldType::tryFrom($field['type']);

            if ($type === null) {
                continue;
            }

            if ($type->isMedia()) {
                continue;
            }

            $key = $field['key'];

            if ((bool) ($field['translatable'] ?? false)) {
                $clean[$key] = is_array($this->data[$key] ?? null) ? $this->data[$key] : [];

                continue;
            }

            $value = $this->data[$key] ?? null;

            $clean[$key] = match ($type) {
                FieldType::BOOLEAN => (bool) $value,
                FieldType::NUMBER, FieldType::MONEY => is_numeric($value) ? $value + 0 : null,
                default => $value === '' ? null : $value,
            };
        }

        return $clean;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function pageLinkOptions(): array
    {
        $pages = Page::query()
            ->with('translations')
            ->get()
            ->sortBy(fn (Page $page): string => $page->title)
            ->mapWithKeys(fn (Page $page): array => [$page->id => $page->title])
            ->all();

        return $pages + SettingsService::current()->authPageOptions();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Edit').' '.($this->record->title !== '' ? $this->record->title : $this->recordType->name))
            ->layout('layouts::admin');
    }
};
?>

<div>
@push('head')
    @vite('resources/js/editor.js')
@endpush
<form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid grid-cols-1 md:grid-cols-5 gap-10 items-stretch">
    <div class="md:col-span-3 min-w-0">
        <div class="space-y-6 mb-10">

            @if ($recordType->fields !== [])
                <flux:fieldset>
                    <flux:legend>{{ __('Fields') }}</flux:legend>
                    <flux:description>{{ __('Custom fields for this content type.') }}</flux:description>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        @foreach ($recordType->fields as $field)
                            @php($fieldType = \App\Enums\FieldType::tryFrom($field['type']))
                            @if ($fieldType)
                                <div wire:key="field-wrapper-{{ $field['key'] }}" @class(['md:col-span-2' => ! $fieldType->isCompact()])>
                                    @includeIf($fieldType->adminView(), ['field' => $field, 'locale' => $locale, 'multiLocale' => count($activeLocales) > 1, 'defaultLocale' => $defaultLocale])
                                </div>
                            @endif
                        @endforeach
                    </div>
                </flux:fieldset>

                <flux:separator />
            @endif

            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Content') }}</flux:legend>
                <flux:description>{{ __('Build and customize this record using flexible content blocks.') }}</flux:description>

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
                        @php($pickerSearches = collect(\App\Enums\BlockType::cases())->map(fn (\App\Enums\BlockType $type): string => \Illuminate\Support\Str::lower($type->label().' '.$type->description()))->values())
                        <div class="space-y-6" x-data="{ q: '' }" x-on:block-picker-opened.window="q = ''; $nextTick(() => { const i = $refs.blockSearch; (i?.tagName === 'INPUT' ? i : i?.querySelector('input'))?.focus() })">
                            <div class="space-y-4">
                                <flux:heading size="lg">{{ __('Add a block') }}</flux:heading>
                                <flux:input x-ref="blockSearch" x-model="q" icon="magnifying-glass" clearable placeholder="{{ __('Search blocks…') }}" />
                            </div>
                            <div class="grid content-start min-h-100 overflow-y-auto overscroll-contain p-1 md:h-[calc(100vh-20rem)] grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                @foreach (\App\Enums\BlockType::cases() as $pickerType)
                                    <button type="button" wire:click="addBlock('{{ $pickerType->value }}')" x-show="q.trim() === '' || @js($pickerSearches[$loop->index]).includes(q.toLowerCase().trim())" class="flex h-fit flex-col gap-2 rounded-lg border border-zinc-200 dark:border-white/10 p-4 text-left transition hover:border-zinc-300 hover:bg-zinc-50 dark:hover:border-white/20 dark:hover:bg-white/5">
                                        <flux:icon name="{{ $pickerType->icon() }}" class="size-6 text-zinc-400" />
                                        <flux:heading size="sm">{{ $pickerType->label() }}</flux:heading>
                                        <flux:text size="sm">{{ $pickerType->description() }}</flux:text>
                                    </button>
                                @endforeach

                                <div class="col-span-full py-10 text-center" x-show="q.trim() !== '' && @js($pickerSearches->all()).every(h => !h.includes(q.toLowerCase().trim()))" x-cloak>
                                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No blocks match your search.') }}</flux:text>
                                </div>
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

            <flux:fieldset>
                <flux:legend>{{ __('SEO Settings') }}</flux:legend>
                <flux:description>{{ __('The main information for this record and how it appears in search results.') }}</flux:description>

                <div class="flex flex-col gap-6 mt-6">
                    <x-forms.input-translated name="title" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Title') }}" />
                    <x-forms.url-translated name="slugs" :$locale :multi-locale="count($activeLocales) > 1" :base-path="$recordType->slug_prefix" label="{{ __('Web Address') }}" />
                    <x-forms.textarea-translated name="description" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Description') }}" />
                    <livewire:admin.media-selector wire:model="media.og_image.{{ $locale }}" wire:key="record-og-image-{{ $locale }}" type="image" name="og_image" :locale="$locale" :multi-locale="count($activeLocales) > 1" :crops="['desktop' => ['label' => __('Desktop'), 'w' => 1200, 'h' => 700], 'mobile' => ['label' => __('Mobile'), 'w' => 800, 'h' => 800]]" label="{{ __('Open Graph Image') }}" />
                    <flux:switch wire:model="noindex" label="{{ __('Discourage search engines from indexing this record') }}" description="{{ __('Adds a noindex tag to this record only and leaves it out of the sitemap.') }}" align="left" />
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
                            @foreach (ContentStatus::cases() as $statusOption)
                                <flux:select.option value="{{ $statusOption->value }}">
                                    {{ $statusOption->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <div x-cloak x-show="$wire.status === '{{ ContentStatus::SCHEDULED->value }}'">
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

                <flux:accordion.item>
                    <flux:accordion.heading>
                        <div class="flex items-center justify-between">
                            {{ __('Categories') }}
                            <flux:text>
                                <span x-cloak x-show="$wire.categories.length === 0">{{ __('Not set') }}</span>
                                <span x-cloak x-show="$wire.categories.length > 0"><span x-text="$wire.categories.length"></span> {{ __('selected') }}</span>
                            </flux:text>
                        </div>
                    </flux:accordion.heading>

                    <flux:accordion.content class="mt-3">
                        <flux:pillbox wire:model="categories" variant="combobox" multiple :placeholder="__('Add a category…')">
                            <x-slot name="input">
                                <flux:pillbox.input wire:model="categorySearch" :placeholder="__('Add a category…')" />
                            </x-slot>
                            @foreach ($this->categoryOptions as $categoryOption)
                                <flux:pillbox.option :value="$categoryOption->id" wire:key="cat-opt-{{ $categoryOption->id }}">{{ $categoryOption->name }}</flux:pillbox.option>
                            @endforeach
                            <flux:pillbox.option.create wire:click="createCategory" min-length="2">
                                {{ __('Create') }} "<span wire:text="categorySearch"></span>"
                            </flux:pillbox.option.create>
                        </flux:pillbox>
                    </flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>

            <flux:button wire:click="preview" icon="eye" variant="filled" class="w-full">
                {{ __('Preview') }}
            </flux:button>

            <div class="grid grid-cols-2 gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
                <flux:button wire:navigate href="{{ route('admin.records-index', $recordType) }}" icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
            </div>

            <flux:text size="sm">
                {{ __('Last edited') }} {{ $record->updated_at?->diffForHumans() ?? __('Never') }}
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
                    src="{{ route('admin.records-preview', ['recordType' => $recordType, 'record' => $record, 'token' => $previewToken]) }}"
                    class="w-full h-full rounded-lg border border-zinc-200 dark:border-white/10 bg-white shadow-sm"
                    title="{{ __('Record preview') }}"></iframe>
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
        const raw = ((content.heading || {})[locale] || '')
            .replace(/<\/(?:p|div|li|h[1-6])>|<br\s*\/?>/gi, ' ');

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
        <flux:breadcrumbs.item href="{{ route('admin.records-index', $recordType) }}" wire:navigate>
            {{ $recordType->name }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ $record->title !== '' ? $record->title : __('Untitled') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ \Illuminate\Support\Str::limit($record->title !== '' ? $record->title : $recordType->name, 22) }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.records-index', $recordType) }}" wire:navigate>{{ $recordType->name }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
