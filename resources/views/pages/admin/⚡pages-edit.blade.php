<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Actions\UpdatePageAction;
use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Services\SettingsService;
use App\Traits\HasBlockBuilder;
use App\Traits\HasContentEditor;
use Flux\Flux;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
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

    public bool $noindex = false;

    /**
     * @var array<string, mixed>
     */
    public array $layout = [];

    /**
     * @var array<int, array{key: string, label: string}>
     */
    public array $menuOptions = [];

    /**
     * @var array<string, string>
     */
    public array $slugs = [];

    /**
     * @var array<int, string>
     */
    public array $categories = [];

    public string $categorySearch = '';

    public bool $showPreview = false;

    public ?string $previewToken = null;

    public function mount(Page $page): void
    {
        $this->authorize('pages.edit');

        $page->load('translations', 'media', 'blocks', 'categories');
        $this->page = $page;
        $this->categories = $page->categories->pluck('id')->map(fn (int $id): string => (string) $id)->all();
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
        $this->noindex = (bool) ($page->metadata['noindex'] ?? false);
        $this->layout = [
            'hideHeader' => false,
            'hideFooter' => false,
            'backgroundColor' => null,
            'backgroundImage' => null,
            'backgroundFixed' => false,
            'customCss' => '',
            ...(is_array($page->metadata['layout'] ?? null) ? $page->metadata['layout'] : []),
            'sidebar' => Page::normalizeSidebar(($page->metadata['layout'] ?? [])['sidebar'] ?? null),
        ];

        $this->menuOptions = collect(SettingsService::current()->allMenus())
            ->reject(fn (array $menu): bool => $menu['builtin'])
            ->map(fn (array $menu): array => ['key' => $menu['key'], 'label' => $menu['name']])
            ->values()
            ->all();

        $this->layout['sidebar']['menus'] = array_values(array_intersect(
            $this->layout['sidebar']['menus'],
            array_column($this->menuOptions, 'key'),
        ));

        foreach (array_keys($this->activeLocales) as $locale) {
            $this->og_image[$locale] ??= [];
        }
    }

    /**
     * @return Collection<int, Media>
     */
    protected function editorMedia(): Collection
    {
        return $this->page->media;
    }

    public function createCategory(CreateCategoryAction $action): void
    {
        $this->authorize('categories.create');

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

    public function update(UpdatePageAction $action): void
    {
        $this->authorize('pages.edit');

        $this->og_image = $this->normalizeMediaInput($this->og_image);
        $this->normalizeBlockAnchors();

        /** @var array<string, array<int, mixed>> */
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
            'og_image' => ['array'],
            'og_image.*' => ['array'],
            'og_image.*.*' => ['array'],
            'og_image.*.*.id' => ['required', 'integer', 'exists:media,id'],
            'og_image.*.*.metadata' => ['nullable', 'array'],
            'og_image.*.*.metadata.caption' => ['nullable', 'string', 'max:500'],
            'og_image.*.*.metadata.alt' => ['nullable', 'string', 'max:255'],
            'noindex' => ['boolean'],
            'layout' => ['array'],
            'layout.hideHeader' => ['boolean'],
            'layout.hideFooter' => ['boolean'],
            'layout.backgroundFixed' => ['boolean'],
            'layout.backgroundColor' => ['nullable', 'string', 'max:30'],
            'layout.backgroundImage' => ['nullable', 'array'],
            'layout.customCss' => ['nullable', 'string', 'max:50000'],
            'layout.sidebar' => ['array'],
            'layout.sidebar.menus' => ['array'],
            'layout.sidebar.menus.*' => ['string'],
            'categories' => ['array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')],
        ];

        foreach (array_keys($this->activeLocales) as $locale) {
            $isLive = in_array($locale, $this->publishedLocales, true);

            $slugUnique = Rule::unique('slugs', 'slug')->where('locale', $locale)
                ->where('base_path', '')
                ->where(function (Builder $query): void {
                    $query->whereNot(function (Builder $q): void {
                        $q->where('sluggable_id', $this->page->id)
                            ->where('sluggable_type', 'page');
                    });
                });

            $rules["title.$locale"] = $isLive ? ['required', 'string', 'min:3'] : ['nullable', 'string'];
            $rules["description.$locale"] = ['nullable', 'string', 'max:160'];
            $rules["slugs.$locale"] = $isLive
                ? ['required', 'string', 'min:3', 'regex:/^[a-z0-9-]+$/', $slugUnique]
                : ['nullable', 'string', 'regex:/^[a-z0-9-]+$/', $slugUnique];
        }

        $messages = [
            'publishedLocales.*.in' => __('Choose a language that is enabled in your site settings.'),
            'published_at.required' => __('Choose a date to schedule this page.'),
            'published_at.after' => __('The scheduled date must be in the future.'),
            'slugs.*.regex' => __('Web addresses can only use lowercase letters, numbers and hyphens.'),
        ];

        $attributes = [
            'publishedLocales.*' => __('language'),
            'published_at' => __('scheduled date'),
            'layout.customCss' => __('custom CSS'),
        ];

        try {
            $validated = $this->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $this->revealErrors($e);

            Flux::toast(__('Please review the highlighted fields before saving.'), variant: 'danger');

            throw $e;
        }

        $action->handle($this->page, [
            ...Arr::except($validated, ['publishedLocales', 'blocks', 'layout', 'noindex', 'categories']),
            'blocks' => $this->blocks,
            'og_image' => $this->og_image,
            'categories' => array_map(intval(...), $this->categories),
            'metadata' => [
                ...($this->page->metadata ?? []),
                'published_locales' => array_values($validated['publishedLocales'] ?? []),
                'noindex' => (bool) ($validated['noindex'] ?? false),
                'layout' => $this->layoutMetadata(),
            ],
        ]);

        Flux::toast(__('Page content has been updated.'), variant: 'success');
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutMetadata(): array
    {
        $color = mb_trim((string) ($this->layout['backgroundColor'] ?? ''));

        return [
            'hideHeader' => (bool) ($this->layout['hideHeader'] ?? false),
            'hideFooter' => (bool) ($this->layout['hideFooter'] ?? false),
            'backgroundColor' => $color !== '' ? $color : null,
            'backgroundImage' => $this->layout['backgroundImage'] ?? null,
            'backgroundFixed' => (bool) ($this->layout['backgroundFixed'] ?? false),
            'customCss' => mb_trim((string) ($this->layout['customCss'] ?? '')),
            'sidebar' => Page::normalizeSidebar($this->layout['sidebar'] ?? null),
        ];
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
            'layout' => $this->layoutMetadata(),
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
@push('head')
    @vite('resources/js/editor.js')
@endpush
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
                <flux:description>{{ __('Manage how this page appears in search results.') }}</flux:description>

                <div class="flex flex-col gap-6 mt-6">
                    <x-forms.input-translated name="title" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Title') }}" />
                    <x-forms.url-translated name="slugs" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Web Address') }}" :readonly="$this->isHomePage" :note="$this->isHomePage ? __('Served at /. Its URL redirects here.') : ''" />
                    <x-forms.textarea-translated name="description" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Description') }}" />
                    <livewire:admin.media-selector wire:model="og_image.{{ $locale }}" type="image" name="og_image" :crops="['desktop' => ['label' => __('Desktop'), 'w' => 1200, 'h' => 700], 'mobile' => ['label' => __('Mobile'), 'w' => 800, 'h' => 800]]" label="{{ __('Open Graph Image') }}" />

                    <flux:switch wire:model="noindex" label="{{ __('Discourage search engines from indexing this page') }}" description="{{ __('Adds a noindex tag to this page only and leaves it out of the sitemap.') }}" align="left" />
                </div>
            </flux:fieldset>

            <flux:separator />

            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Layout') }}</flux:legend>
                <flux:description>{{ __('Optional design overrides for this page.') }}</flux:description>

                <div class="flex flex-col gap-6 mt-6">
                    <div class="grid md:grid-cols-2 gap-4">
                        <flux:switch wire:model.live="layout.hideHeader" label="{{ __('Hide site header') }}" align="left" />
                        <flux:switch wire:model.live="layout.hideFooter" label="{{ __('Hide site footer') }}" align="left" />
                    </div>

                    <livewire:admin.media-selector
                        wire:model="layout.backgroundImage"
                        wire:key="page-background-image"
                        name="page-background-image"
                        type="image"
                        :locale="$locale"
                        :multiple="false"
                        label="{{ __('Background image') }}" />

                    <div class="grid md:grid-cols-2 gap-4">
                        <flux:color-picker wire:model="layout.backgroundColor" clearable label="{{ __('Background color') }}" placeholder="{{ __('Theme') }}" />
                        <div class="flex md:h-full md:items-center md:pt-5">
                            <flux:switch wire:model.live="layout.backgroundFixed" label="{{ __('Fixed background') }}" align="left" />
                        </div>
                    </div>

                    <flux:separator variant="subtle" />

                    <flux:field>
                        <flux:label>{{ __('Menus to show') }}</flux:label>

                        <flux:pillbox variant="combobox" multiple wire:model="layout.sidebar.menus" placeholder="{{ __('Select menus…') }}">
                            @forelse ($menuOptions as $option)
                                <flux:pillbox.option value="{{ $option['key'] }}" wire:key="sidebar-menu-{{ $option['key'] }}">{{ $option['label'] }}</flux:pillbox.option>
                            @empty
                                <flux:pillbox.option.empty>{{ __('No menus yet.') }}</flux:pillbox.option.empty>
                            @endforelse
                        </flux:pillbox>

                        <flux:description>
                            {{ __('Create and edit menus in') }}
                            <flux:link href="{{ route('admin.settings-menus') }}" wire:navigate>{{ __('Settings → Menus') }}</flux:link>.
                        </flux:description>
                    </flux:field>

                    <div>
                        <flux:modal.trigger name="page-custom-css">
                            <flux:button icon="code-bracket" variant="filled">{{ ($layout['customCss'] ?? '') !== '' ? __('Edit custom CSS') : __('Add custom CSS') }}</flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:modal name="page-custom-css" class="w-full md:max-w-2xl">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Custom CSS') }}</flux:heading>
                                <flux:text class="mt-2">{{ __('Add custom CSS rules that apply only to this page.') }}</flux:text>
                            </div>
                            <flux:textarea wire:model.lazy="layout.customCss" rows="12" class="font-mono text-sm" placeholder=".my-class &#123; color: red; &#125;" />
                            <div class="flex justify-end">
                                <flux:modal.close>
                                    <flux:button variant="primary">{{ __('Done') }}</flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>
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