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

    public string $contact_email = '';

    public string $currency = '';

    public function mount(): void
    {
        $this->languages = Locale::query()->active()->orderBy('name')->pluck('code')->all();
        $this->home_page_id = SettingsService::current()->homePageId();
        $this->contact_email = is_string(config('site.contact_email')) ? config()->string('site.contact_email') : '';
        $this->currency = SettingsService::current()->currency();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function currencies(): array
    {
        return config()->array('currencies');
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
            'contact_email' => ['nullable', 'email', 'max:255'],
            'currency' => ['required', 'string', Rule::in(array_keys($this->currencies()))],
        ], [
            'contact_email.email' => __('Enter a valid email address for form submissions.'),
        ], [
            'contact_email' => __('form submissions email'),
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

        $action->handle([
            'home_page_id' => $validated['home_page_id'],
            'contact_email' => $validated['contact_email'] ?? '',
            'currency' => $validated['currency'],
        ]);

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

<x-admin.settings-layout>
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-10 md:col-span-3">
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

            <flux:input
                wire:model="contact_email"
                type="email"
                :label="__('Form submissions email')"
                :placeholder="__('you@example.com')"
                :description="__('Where form submissions are emailed when a form has no recipient of its own.')"
            />

            <flux:select
                variant="listbox"
                searchable
                wire:model="currency"
                :label="__('Currency')"
                :placeholder="__('Select a currency…')"
                :description="__('Used to format money fields across your site.')"
            >
                @foreach ($this->currencies as $code => $meta)
                    <flux:select.option :value="$code">{{ $code }} — {{ $meta['name'] }} ({{ $meta['symbol'] }})</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
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
            {{ __('General') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('General') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
