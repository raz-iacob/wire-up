<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Services\SettingsService;
use App\Services\UiStrings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int, string>
     */
    public array $locales = [];

    public string $locale = '';

    /**
     * @var array<string, array<string, string>>
     */
    public array $translations = [];

    public bool $hideTranslated = false;

    public function mount(): void
    {
        abort_unless(SettingsService::current()->showsInterfaceTranslations(), 404);

        $this->locales = SettingsService::current()->interfaceTranslationLocales();
        $this->locale = $this->locales[0];

        $stored = config('site.ui_translations');
        $stored = is_array($stored) ? $stored : [];

        foreach ($this->locales as $locale) {
            foreach ($this->allStrings() as $string) {
                $localeStore = is_array($stored[$locale] ?? null) ? $stored[$locale] : [];
                $this->translations[$locale][md5($string)] = (string) ($localeStore[$string] ?? '');
            }
        }
    }

    public function saveString(string $hash, string $value): void
    {
        $this->authorize('settings.edit');

        if (! array_key_exists($hash, $this->translations[$this->locale] ?? [])) {
            return;
        }

        $this->translations[$this->locale][$hash] = mb_trim($value);

        $clean = [];

        foreach ($this->locales as $locale) {
            foreach ($this->allStrings() as $string) {
                $translation = mb_trim($this->translations[$locale][md5($string)] ?? '');

                if ($translation !== '') {
                    $clean[$locale][$string] = $translation;
                }
            }
        }

        new UpdateSettingsAction()->handle(['ui_translations' => $clean]);

        Flux::toast(__('Translation saved.'), variant: 'success');
    }

    /**
     * @return array<int, array{group: string, strings: array<int, string>}>
     */
    #[Computed]
    public function catalog(): array
    {
        return UiStrings::catalog();
    }

    /**
     * @return array<int, array{group: string, strings: array<int, string>}>
     */
    #[Computed]
    public function rows(): array
    {
        $rows = [];

        foreach (UiStrings::catalog() as $group) {
            $strings = array_values(array_filter(
                $group['strings'],
                fn (string $string): bool => ! $this->hideTranslated || $this->isUntranslated($string),
            ));

            if ($strings !== []) {
                $rows[] = ['group' => $group['group'], 'strings' => $strings];
            }
        }

        return $rows;
    }

    #[Computed]
    public function untranslatedCount(): int
    {
        return count(array_filter($this->allStrings(), $this->isUntranslated(...)));
    }

    #[Computed]
    public function totalCount(): int
    {
        return count($this->allStrings());
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function localeNames(): array
    {
        $active = resolve('localization')->getActiveLocales();

        return collect($this->locales)
            ->mapWithKeys(fn (string $code): array => [$code => is_string($active[$code]['name'] ?? null) ? $active[$code]['name'] : $code])
            ->all();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Translations'))
            ->layout('layouts::admin');
    }

    private function isUntranslated(string $string): bool
    {
        return mb_trim((string) ($this->translations[$this->locale][md5($string)] ?? '')) === '';
    }

    /**
     * @return array<int, string>
     */
    private function allStrings(): array
    {
        return UiStrings::strings();
    }
};
?>

<div class="grid grid-cols-1 gap-10 md:grid-cols-5">
    <div class="min-w-0 space-y-10 md:col-span-3">
        @forelse ($this->rows as $group)
            <div wire:key="group-{{ md5($group['group']) }}">
                <flux:heading size="lg" class="mb-4">{{ __($group['group']) }}</flux:heading>

                <div class="space-y-6">
                    @foreach ($group['strings'] as $string)
                        @php($hash = md5($string))
                        <div wire:key="tr-{{ $locale }}-{{ $hash }}" class="space-y-1.5">
                            <label for="tr-{{ $locale }}-{{ $hash }}" class="block text-sm font-medium text-zinc-800 dark:text-white">{{ $string }}</label>
                            <textarea
                                id="tr-{{ $locale }}-{{ $hash }}"
                                x-data="{ original: '' }"
                                x-init="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                x-on:focus="original = $el.value"
                                x-on:blur="if ($el.value.trim() !== original.trim()) $wire.saveString(@js($hash), $el.value.trim())"
                                rows="1"
                                placeholder="{{ __('Write the :language translation here', ['language' => $this->localeNames[$locale]]) }}"
                                class="block w-full resize-none overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 shadow-xs placeholder:text-zinc-400 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent dark:border-white/10 dark:bg-white/10 dark:text-white dark:placeholder:text-zinc-500"
                            >{{ $translations[$locale][$hash] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:callout icon="check-circle" variant="success">
                <flux:callout.heading>{{ __('All done') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Everything is translated for this language.') }}</flux:callout.text>
            </flux:callout>
        @endforelse
    </div>

    <div class="order-first md:order-none md:col-span-2">
        <flux:card class="flex flex-col gap-6 md:sticky md:top-24">
            @if (count($locales) > 1)
                <flux:select wire:model.live="locale" variant="listbox" :label="__('Translating into')">
                    @foreach ($locales as $code)
                        <flux:select.option value="{{ $code }}">{{ $this->localeNames[$code] }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:switch wire:model.live="hideTranslated" :label="__('Hide translated')" align="left" />

            <flux:separator />

            <div>
                <flux:heading size="xl">{{ $this->untranslatedCount }}</flux:heading>
                <flux:text>{{ __('left to translate') }}</flux:text>
                <flux:text variant="subtle" class="mt-1">{{ __(':done of :total done', ['done' => $this->totalCount - $this->untranslatedCount, 'total' => $this->totalCount]) }}</flux:text>
            </div>
        </flux:card>
    </div>
</div>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ __('Translations') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Translations') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
