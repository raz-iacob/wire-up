<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
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
        $this->title = $this->localeMap('title');
        $this->description = $this->localeMap('description');
        $this->favicon = is_array(config('site.favicon')) ? config('site.favicon') : null;

        $this->activeLocales = resolve('localization')->getActiveLocales();

        if (! isset($this->locale) || ! array_key_exists($this->locale, $this->activeLocales)) {
            $this->locale = app()->getLocale();
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

        $messages = [
            'title.*.required' => __('Enter a title for the selected language.'),
            'title.*.min' => __('The title must be at least :min characters.'),
            'title.*.max' => __('The title may not exceed :max characters.'),
            'description.*.max' => __('The tagline may not exceed :max characters.'),
            'favicon.id.exists' => __('The selected favicon is no longer available.'),
        ];

        $attributes = [
            'title.*' => __('title'),
            'description.*' => __('tagline'),
            'favicon' => __('favicon'),
            'favicon.id' => __('favicon'),
        ];

        try {
            $validated = $this->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $this->revealErrors($e);

            throw $e;
        }

        $action->handle([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? [],
            'favicon' => $this->favicon,
        ]);

        Flux::toast(__('Identity has been updated.'), variant: 'success');
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

    /**
     * @return array<string, string>
     */
    private function localeMap(string $key): array
    {
        $value = config('site.'.$key);
        if (! is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $code => $text) {
            if (is_string($code) && is_string($text)) {
                $map[$code] = $text;
            }
        }

        return $map;
    }
};
?>

<x-admin.settings-layout>
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-8 md:col-span-3">
            <x-forms.input-translated name="title" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Title') }}" />
            <x-forms.textarea-translated name="description" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Tagline') }}" />
            
            <livewire:admin.media-selector
                wire:model="favicon"
                name="favicon"
                type="image"
                :crops="['default' => ['label' => __('Favicon'), 'w' => 512, 'h' => 512]]"
                label="{{ __('Favicon') }}"
            />

            <div>
                <flux:button type="submit" variant="primary" icon="check">
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
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ __('Identity') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Identity') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
