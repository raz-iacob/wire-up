<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Locale;
use App\Models\Page;
use App\Services\SettingsService;
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

    public ?int $home_page_id = null;

    public function mount(): void
    {
        $this->languages = Locale::query()->active()->orderBy('name')->pluck('code')->all();
        $this->home_page_id = SettingsService::current()->homePageId();
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

    /**
     * @return Collection<int, Page>
     */
    #[Computed]
    public function publishedPages(): Collection
    {
        return Page::query()
            ->published()
            ->with('translations')
            ->get();
    }

    public function update(UpdateSettingsAction $action): void
    {
        $validated = $this->validate([
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', Rule::exists('locales', 'code')],
            'home_page_id' => ['required', 'integer', Rule::exists('pages', 'id')],
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

        $action->handle(['home_page_id' => $validated['home_page_id']]);

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

<x-admin.settings-layout :subheading="__('General settings for your site.')">
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-8 md:col-span-3">
            <flux:select
                variant="listbox"
                searchable
                wire:model="home_page_id"
                :label="__('Homepage')"
                :placeholder="__('Select a page…')"
                :description="__('The page shown at your site’s root. Visiting its own URL redirects there.')"
            >
                @foreach ($this->publishedPages as $page)
                    <flux:select.option :value="$page->id">{{ $page->title }}</flux:select.option>
                @endforeach
            </flux:select>

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

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </div>
    </form>
</x-admin.settings-layout>
