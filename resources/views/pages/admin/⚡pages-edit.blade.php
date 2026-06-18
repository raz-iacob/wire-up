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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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
        $this->blocks = $page->getBlocksArray();
        $this->status = $page->computed_status;
        $this->publishedLocales = $page->published_locales;
        $this->published_at = $page->published_at;
        $this->title = $page->translationsFor('title');
        $this->description = $page->translationsFor('description');
        $this->slugs = $page->getSlugsArray();
        $this->locale = app()->getLocale();
        $this->activeLocales = resolve('localization')->getActiveLocales();
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

    public function addBlock(string $type): void
    {
        $blockType = BlockType::tryFrom($type);

        if ($blockType === null) {
            return;
        }

        $id = 'new-'.Str::uuid()->toString();

        $this->blocks[$id] = [
            'id' => $id,
            'type' => $blockType->value,
            'position' => count($this->blocks),
            'content' => $blockType->defaultContent(),
        ];
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

    #[Computed]
    public function isHomePage(): bool
    {
        return $this->page->id === SettingsService::current()->homePageId();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Edit').' '.$this->page->title)
            ->layout('layouts::admin');
    }
};
?>

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
                                x-data="{ open: {{ str_starts_with((string) $block['id'], 'new-') ? 'true' : 'false' }} }">
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
                                        <flux:button size="sm" icon="x-mark" variant="subtle" square :tooltip="__('Remove')" wire:click="confirmRemoveBlock('{{ $block['id'] }}')" />
                                    </div>
                                </div>

                                <div class="p-4" x-show="open" x-collapse x-cloak>
                                    @includeIf($blockType->adminView(), ['block' => $block, 'locale' => $locale, 'multiLocale' => count($activeLocales) > 1, 'index' => $index])
                                </div>
                            </flux:card>
                        @endforeach
                    </div>

                    <flux:dropdown position="bottom" align="start">
                        <flux:button icon="plus" variant="filled">{{ __('Add block') }}</flux:button>
                        <flux:menu>
                            @foreach (\App\Enums\BlockType::cases() as $blockType)
                                <flux:menu.item :icon="$blockType->icon()" wire:click="addBlock('{{ $blockType->value }}')">
                                    {{ $blockType->label() }}
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
                    <livewire:admin.media-selector wire:model="og_image.{{ $locale }}" type="image" name="og_image" :$locale :multi-locale="count($activeLocales) > 1" :multiple="false" :with-caption="true" :crops="['desktop' => ['label' => __('Desktop'), 'w' => 1200, 'h' => 700], 'mobile' => ['label' => __('Mobile'), 'w' => 800, 'h' => 800]]" label="{{ __('Open Graph Image') }}" />
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