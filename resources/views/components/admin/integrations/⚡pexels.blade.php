<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\Pexels;
use App\Livewire\Forms\PexelsIntegrationForm;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public PexelsIntegrationForm $form;

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->form->validate();

        $action->handle(['pexels_api_key' => $this->typedOrSaved($validated['pexels_api_key'], 'pexels_api_key')]);

        $this->form->reset('pexels_api_key');

        $this->finish(__('Pexels connected.'));
    }

    protected function integration(): Pexels
    {
        return new Pexels;
    }
};
?>

<div>
    <x-admin.integration-card
        name="Pexels"
        :description="__('Search and import free photos and videos in the media library.')"
        modal="integration-pexels"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <svg viewBox="0 0 32 32" class="size-7" xmlns="http://www.w3.org/2000/svg">
                <rect width="32" height="32" rx="6" fill="#05A081" />
                <path fill="#fff" d="M12.6 8.8h4.6c3 0 5.1 2 5.1 4.9s-2.1 4.9-5.1 4.9h-2.1v4.6h-2.5V8.8Zm2.5 2.3v5.2h1.9c1.6 0 2.7-1 2.7-2.6s-1.1-2.6-2.7-2.6h-1.9Z" />
            </svg>
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-pexels" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect Pexels') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Enables searching and importing photos and videos from Pexels in the media library. Create a free key at pexels.com/api.') }}</flux:text>
            </div>

            <flux:input
                wire:model="form.pexels_api_key"
                type="password"
                viewable
                :label="__('Pexels API key')"
                :placeholder="$this->connected ? $this->hint('pexels_api_key') : __('Paste your Pexels API key…')"
                :description="$this->connected ? __('Leave blank to keep the saved key.') : null"
            />

            <x-admin.integration-actions :connected="$this->connected" />
        </form>
    </flux:modal>
</div>
