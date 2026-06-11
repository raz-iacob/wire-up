<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Media;
use App\Models\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
    public Settings $settings;

    /**
     * @var array<string, string>
     */
    public array $title = [];

    /**
     * @var array<string, string>
     */
    public array $description = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $favicon = null;

    #[Url(except: 'en')]
    public string $locale;

    /**
     * @var array<string, mixed>
     */
    public array $activeLocales = [];

    public function mount(): void
    {
        $this->settings = Settings::current();
        $this->settings->load('translations', 'media');

        $this->title = $this->settings->translationsFor('title');
        $this->description = $this->settings->translationsFor('description');
        $this->favicon = $this->mediaForRole('favicon');

        $this->locale = app()->getLocale();
        $this->activeLocales = resolve('localization')->getActiveLocales();

        if (session()->pull('identity-saved', false)) {
            Flux::toast(__('Identity has been updated.'), variant: 'success');
        }
    }

    public function update(UpdateSettingsAction $action): void
    {
        /** @var array<string, array<int, mixed>> */
        $rules = [
            'favicon' => ['nullable', 'array'],
            'favicon.id' => ['nullable', 'integer', 'exists:media,id'],
        ];

        foreach (array_keys($this->activeLocales) as $locale) {
            $rules["title.$locale"] = ['required', 'string', 'min:3', 'max:120'];
            $rules["description.$locale"] = ['nullable', 'string', 'max:255'];
        }

        $validated = $this->validate($rules);

        $action->handle($this->settings, [
            ...$validated,
            'favicon' => $this->favicon,
        ]);

        session()->flash('identity-saved');

        $this->redirect(route('admin.settings-identity'), navigate: true);
    }

    #[On('change-locale')]
    public function changeLocale(): void
    {
        $codes = array_keys($this->activeLocales);
        $index = array_search($this->locale, $codes, true);

        $this->locale = $codes[($index + 1) % count($codes)] ?? $this->locale;
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Identity'))
            ->layout('layouts::admin');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mediaForRole(string $role): ?array
    {
        $media = $this->settings->media
            ->first(fn (Media $media): bool => $media->pivot->role === $role);

        return $media ? $this->mediaToItem($media) : null;
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
};
?>

<x-settings-layout :subheading="__('These details not only shape your site’s identity but also enhance its visibility in search engines.')">
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-8 md:col-span-3">
            <x-forms.input-translated name="title" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Title') }}" />
            <x-forms.textarea-translated name="description" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Tagline') }}" />
            
            <livewire:media-selector
                wire:model="favicon"
                name="favicon"
                type="image"
                :crops="['default' => ['label' => __('Favicon'), 'w' => 512, 'h' => 512]]"
                label="{{ __('Favicon') }}"
            />

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </div>

        <div class="md:sticky md:top-8 md:col-span-2" x-data="{
            faviconUrl() {
                const f = $wire.favicon
                if (! f) return null
                const c = f.crop && f.crop.default
                if (c && f.source) {
                    const opts = `w=${c.w || 512},h=${c.h || 512},crop=${c.crop_w || 0}-${c.crop_h || 0}-${c.crop_x || 0}-${c.crop_y || 0},q=${c.q || 80},fm=${c.fm || 'png'}`
                    return `/img/${opts}/${f.source}`
                }
                return f.preview || null
            }
        }">
            <flux:text class="mb-3">{{ __('This is how your site will appear in search results.') }}</flux:text>
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="flex items-center justify-center size-7 shrink-0 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <img x-cloak x-show="faviconUrl()" :src="faviconUrl()" alt="" class="size-full object-cover" />
                        <flux:icon icon="globe-alt" variant="mini" class="text-zinc-400" x-show="! faviconUrl()" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading class="truncate text-sm!" x-text="$wire.title[$wire.locale] || '{{ __('Website title') }}'"></flux:heading>
                        <flux:text class="text-xs">{{ str(config('app.url'))->after('://') }}</flux:text>
                    </div>
                </div>
                <flux:heading class="text-xl! text-blue-700 dark:text-blue-400" x-text="$wire.title[$wire.locale] || '{{ __('Website title') }}'"></flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" x-text="$wire.description[$wire.locale] || '{{ __('Your site tagline goes here.') }}'"></flux:text>
            </div>
        </div>
    </form>
</x-settings-layout>
