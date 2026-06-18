<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<string, string>
     */
    public array $links = [];

    public string $variant = '';

    public function mount(): void
    {
        $saved = config('site.social');
        $saved = is_array($saved) ? $saved : [];

        foreach (array_keys(config()->array('social.platforms')) as $platform) {
            $this->links[$platform] = is_string($saved[$platform] ?? null) ? $saved[$platform] : '';
        }

        $this->variant = SettingsService::current()->socialIconVariant();
    }

    public function update(UpdateSettingsAction $action): void
    {
        $rules = [
            'variant' => ['required', Rule::in(array_keys(config()->array('social.icon_variants')))],
        ];

        foreach (array_keys(config()->array('social.platforms')) as $platform) {
            $rules["links.$platform"] = ['nullable', 'url', 'max:255'];
        }

        $validated = $this->validate($rules);

        $links = array_filter(
            $validated['links'] ?? [],
            fn (mixed $url): bool => is_string($url) && $url !== '',
        );

        $action->handle([
            'social' => $links,
            'social_icon_variant' => $validated['variant'],
        ]);

        Flux::toast(__('Social links have been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Social'))
            ->layout('layouts::admin');
    }
};
?>

@php
    $platforms = config('social.platforms');
    $iconVariants = config('social.icon_variants');
@endphp

<x-admin.settings-layout>
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-10 md:col-span-3">
            <div class="grid sm:grid-cols-2 gap-6">
                @foreach ($platforms as $key => $platform)
                    <flux:input
                        wire:model="links.{{ $key }}"
                        type="url"
                        label="{{ __($platform['label']) }}"
                        placeholder="{{ $platform['placeholder'] }}"
                    />
                @endforeach
            </div>

            <flux:radio.group wire:model="variant" variant="segmented" label="{{ __('Icon style') }}" description="{{ __('How the social icons render in your footer.') }}">
                @foreach ($iconVariants as $value => $label)
                    <flux:radio value="{{ $value }}" label="{{ __($label) }}" />
                @endforeach
            </flux:radio.group>

            <div>
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
            {{ __('Social') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Social') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
