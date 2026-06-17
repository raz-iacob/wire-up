<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
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
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
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
        $page->load('translations', 'media');
        $this->page = $page;
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
            'published_at' => ['nullable', 'date'],
            'publishedLocales' => ['array'],
            'publishedLocales.*' => ['string', Rule::in(array_keys($this->activeLocales))],
            'og_image' => ['array'],
            'og_image.*' => ['array'],
            'og_image.*.*' => ['array'],
            'og_image.*.*.id' => ['required', 'integer', 'exists:media,id'],
            'og_image.*.*.metadata' => ['nullable', 'array'],
            'og_image.*.*.metadata.caption' => ['nullable', 'string', 'max:500'],
            'og_image.*.*.metadata.alt' => ['nullable', 'string', 'max:255'],
        ];

        foreach (array_keys($this->activeLocales) as $locale) {
            $rules["title.$locale"] = ['required', 'string', 'min:3'];
            $rules["description.$locale"] = ['nullable', 'string', 'max:160'];
            $rules["slugs.$locale"] = [
                'required', 'string', 'min:3',
                Rule::unique('slugs', 'slug')->where('locale', $locale)
                    ->where(function (Builder $query): void {
                        $query->whereNot(function (Builder $q): void {
                            $q->where('sluggable_id', $this->page->id)
                                ->where('sluggable_type', 'page');
                        });
                    }),
            ];
        }

        $messages = [
            'publishedLocales.*.in' => __('Choose a language that is enabled in your site settings.'),
        ];

        $attributes = [
            'publishedLocales.*' => __('language'),
        ];

        $validated = $this->validate($rules, $messages, $attributes);

        $action->handle($this->page, [
            ...Arr::except($validated, ['publishedLocales']),
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
        <div class="gap-4 mb-6 md:mb-0">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" class="cursor-pointer hover:underline">
                    {{ __('Edit') }} {{ $page->title }}
                </flux:heading>
                @if ($this->isHomePage)
                    <flux:badge color="lime" size="sm" icon="home">{{ __('Homepage') }}</flux:badge>
                @endif
            </div>
            <flux:subheading size="sm">
                {{ __('Created on') }} {{ $page->created_at?->format('M d, Y H:i') }}
            </flux:subheading>
        </div>
        <div class="mt-8 space-y-6 mb-10 md:col-span-2">
            
            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Content') }}</flux:legend>
                <flux:description>{{ __('Build and customize this page using flexible content blocks.') }}</flux:description>

                
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

            <div class="flex flex-col gap-6">
                <flux:select variant="listbox" placeholder="{{ __('Choose status') }}" label="{{ __('Status') }}" wire:model="status">
                    @foreach(PageStatus::cases() as $status)
                        <flux:select.option value="{{ $status->value }}">
                            {{ $status->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div x-cloak x-show="$wire.status === '{{ PageStatus::SCHEDULED->value }}'">
                    <flux:date-picker wire:model="published_at" label="{{ __('Publish on') }}" />
                </div>
            </div>

            @if (count($activeLocales) > 1)
                <flux:separator />

                <flux:accordion>
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
                            <flux:description class="mt-3">{{ __('This page is only visible in the languages selected here.') }}</flux:description>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>
            @endif

            <flux:separator />

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