<?php

declare(strict_types=1);

use App\Models\Locale;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int, string>
     */
    public array $languages = [];

    public function mount(): void
    {
        $this->languages = Locale::query()->active()->orderBy('name')->pluck('code')->all();
    }

    /**
     * @return Collection<int, Locale>
     */
    #[Computed]
    public function allLocales(): Collection
    {
        return Locale::query()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get(['code', 'name', 'endonym']);
    }

    public function update(): void
    {
        $validated = $this->validate([
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', Rule::exists('locales', 'code')],
        ]);

        $codes = array_values(array_unique($validated['languages']));

        $default = resolve('localization')->getDefaultLocale();
        if (! in_array($default, $codes, true)) {
            $codes[] = $default;
        }

        DB::transaction(function () use ($codes): void {
            Locale::query()->whereIn('code', $codes)->update(['active' => true]);
            Locale::query()->whereNotIn('code', $codes)->update(['active' => false]);
        });

        cache()->forget('site-locales');

        $this->languages = $codes;

        Flux::toast(__('Settings have been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('General'))
            ->layout('layouts::admin');
    }
};
?>

<x-settings-layout :subheading="__('General settings for your site.')">
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="max-w-2xl">
        <flux:pillbox
            wire:model="languages"
            multiple
            searchable
            :label="__('Languages')"
            :placeholder="__('Select languages…')"
            :description="__('Pick the languages your site supports. The default language is always kept active.')"
        >
            @foreach ($this->allLocales as $localeOption)
                <flux:pillbox.option :value="$localeOption->code" :label="$localeOption->endonym ? $localeOption->name.' ('.$localeOption->endonym.')' : $localeOption->name" />
            @endforeach
        </flux:pillbox>

        <div class="mt-10">
            <flux:button type="submit" variant="primary">
                {{ __('Update') }}
            </flux:button>
        </div>
    </form>
</x-settings-layout>
