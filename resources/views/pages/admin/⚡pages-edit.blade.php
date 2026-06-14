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

        /** @var array<string, array<int, string>> */
        $rules = [
            'status' => ['required', Rule::enum(PageStatus::class)],
            'published_at' => ['nullable', 'date'],
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

        $validated = $this->validate($rules);

        $action->handle($this->page, [
            ...$validated,
            'og_image' => $this->og_image,
        ]);

        Flux::toast(__('Page content has been updated.'), variant: 'success');
    }

    /**
     * Normalizes media-field input to a list of items per locale, so single-mode
     * selectors (one item or null) and multiple-mode selectors (a list) persist
     * through the same code path.
     *
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

<form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
    <div class="md:col-span-2 min-w-0">
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
        <div class="max-w-5xl mt-8 space-y-6 mb-10">
            
            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Content') }}</flux:legend>
                <flux:description>{{ __('Build and customize this page using flexible content blocks.') }}</flux:description>

                
            </flux:fieldset>
            
            <flux:separator />

            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Visibility') }}</flux:legend>
                <flux:description>{{ __('Control when and how this page becomes visible to visitors.') }}</flux:description>

                <div class="grid md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <flux:select variant="listbox" placeholder="Choose status" label="{{ __('Status') }}" wire:model="status">
                            @foreach(PageStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}">
                                    {{ $status->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div x-cloak x-show="$wire.status === '{{ PageStatus::SCHEDULED->value }}'">
                        <flux:date-picker wire:model="published_at" label="{{ __('Publish on') }}" />
                    </div>
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
    <div class="mb-10 md:mb-0">
        <div class="flex flex-col-reverse md:flex-col items-center md:items-end md:justify-end gap-4 md:sticky md:top-8 pt-2">
            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Save') }}
                </flux:button>
                <flux:button wire:navigate href="{{ route('admin.pages-index') }}" icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
            </div>
        </div>
    </div>
</form>