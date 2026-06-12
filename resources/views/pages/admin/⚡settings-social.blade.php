<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public Settings $settings;

    /**
     * @var array<string, string>
     */
    public array $links = [];

    public function mount(): void
    {
        $this->settings = Settings::current();

        $meta = $this->settings->metadata ?? [];
        $saved = is_array($meta['social'] ?? null) ? $meta['social'] : [];

        foreach (array_keys(config()->array('social.platforms')) as $platform) {
            $this->links[$platform] = is_string($saved[$platform] ?? null) ? $saved[$platform] : '';
        }
    }

    public function update(UpdateSettingsAction $action): void
    {
        $rules = [];

        foreach (array_keys(config()->array('social.platforms')) as $platform) {
            $rules["links.$platform"] = ['nullable', 'url', 'max:255'];
        }

        $validated = $this->validate($rules);

        $links = array_filter(
            $validated['links'] ?? [],
            fn (mixed $url): bool => is_string($url) && $url !== '',
        );

        $action->handle($this->settings, ['metadata' => ['social' => $links]]);

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
@endphp

<x-settings-layout :subheading="__('Set the links to your social profiles — they’ll appear in your site’s footer so visitors can connect with you.')">
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

        <div class="mt-10">
            <flux:button type="submit" variant="primary">
                {{ __('Update') }}
            </flux:button>
        </div>
    </form>
</x-settings-layout>
