<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\GoogleMaps;
use App\Livewire\Forms\GoogleMapsIntegrationForm;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public GoogleMapsIntegrationForm $form;

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->form->validate();

        $action->handle(['google_maps_api_key' => $this->typedOrSaved($validated['google_maps_api_key'], 'google_maps_api_key')]);

        $this->form->reset('google_maps_api_key');

        $this->finish(__('Google Maps connected.'));
    }

    protected function integration(): GoogleMaps
    {
        return new GoogleMaps;
    }
};
?>

<div>
    <x-admin.integration-card
        :name="__('Google Maps')"
        :description="__('Use the official Maps Embed API for location blocks.')"
        modal="integration-google-maps"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <svg viewBox="0 0 24 24" class="size-7" xmlns="http://www.w3.org/2000/svg">
                <path fill="#EA4335" d="M12 2c-3.9 0-7 3.1-7 7 0 4.9 7 13 7 13s7-8.1 7-13c0-3.9-3.1-7-7-7z" />
                <circle cx="12" cy="9" r="2.6" fill="#fff" />
            </svg>
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-google-maps" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect Google Maps') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Uses the Google Maps Embed API for location blocks. Enable the Maps Embed API and create a key in the Google Cloud console.') }}</flux:text>
            </div>

            <flux:input
                wire:model="form.google_maps_api_key"
                type="password"
                viewable
                :label="__('Google Maps API key')"
                :placeholder="$this->connected ? $this->hint('google_maps_api_key') : __('Paste your Google Maps API key…')"
                :description="$this->connected ? __('Leave blank to keep the saved key.') : null"
            />

            <x-admin.integration-actions :connected="$this->connected" />
        </form>
    </flux:modal>
</div>
