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

<x-admin.settings-layout :subheading="__('Set the links to your social profiles — they’ll appear in your site’s footer so visitors can connect with you.')">
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="max-w-3xl">
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

        <flux:separator variant="subtle" class="my-8" />

        <flux:radio.group wire:model="variant" variant="segmented" label="{{ __('Icon style') }}" description="{{ __('How the social icons render in your footer.') }}">
            @foreach ($iconVariants as $value => $label)
                <flux:radio value="{{ $value }}" label="{{ __($label) }}" />
            @endforeach
        </flux:radio.group>

        <div class="mt-10">
            <flux:button type="submit" variant="primary">
                {{ __('Update') }}
            </flux:button>
        </div>
    </form>
</x-admin.settings-layout>
