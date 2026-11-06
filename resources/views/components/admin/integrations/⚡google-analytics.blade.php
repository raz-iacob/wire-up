<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\GoogleAnalytics;
use App\Livewire\Forms\GoogleAnalyticsIntegrationForm;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public GoogleAnalyticsIntegrationForm $form;

    public function mount(): void
    {
        $this->form->google_analytics_id = $this->savedSetting('google_analytics_id');
        $this->form->google_analytics_property_id = $this->savedSetting('google_analytics_property_id');
    }

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->form->validate();
        $propertyId = mb_trim((string) ($validated['google_analytics_property_id'] ?? ''));

        $action->handle([
            'google_analytics_id' => $validated['google_analytics_id'],
            'google_analytics_property_id' => $propertyId,
            'google_analytics_credentials' => $propertyId === '' ? '' : $this->typedOrSaved($validated['google_analytics_credentials'] ?? '', 'google_analytics_credentials'),
        ]);

        $this->form->reset('google_analytics_credentials');

        $this->finish(__('Google Analytics connected.'), notify: true);
    }

    protected function integration(): GoogleAnalytics
    {
        return new GoogleAnalytics;
    }
};
?>

<div>
    <x-admin.integration-card
        :name="__('Google Analytics')"
        :description="__('Add Google Analytics tracking to your public site.')"
        modal="integration-google-analytics"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <svg viewBox="0 0 32 32" class="size-7" xmlns="http://www.w3.org/2000/svg">
                <rect x="20" y="4" width="8" height="24" rx="4" fill="#F9AB00" />
                <rect x="11" y="12" width="8" height="16" rx="4" fill="#E37400" />
                <circle cx="7" cy="24.5" r="3.5" fill="#E37400" />
            </svg>
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-google-analytics" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect Google Analytics') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Adds the Google Analytics tracking snippet to your public site. Find this ID in your GA4 property’s data stream settings.') }}</flux:text>
            </div>

            <flux:input
                wire:model="form.google_analytics_id"
                :label="__('Google Analytics measurement ID')"
                placeholder="G-XXXXXXXXXX"
            />

            <flux:separator variant="subtle" />

            <div class="space-y-4">
                <div>
                    <flux:heading size="sm">{{ __('Analytics reports') }}</flux:heading>
                    @php($serviceAccountsLink = '<a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener noreferrer" class="underline">'.__('Google Cloud console').'</a>')
                    @php($dataApiLink = '<a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" rel="noopener noreferrer" class="underline">'.__('Analytics Data API').'</a>')
                    <flux:text class="mt-1">{!! __('Optional. Shows visitor stats in the admin. Create a service account key in the :console, enable the :api, and add the account as a viewer of your GA4 property.', ['console' => $serviceAccountsLink, 'api' => $dataApiLink]) !!}</flux:text>
                </div>

                <flux:input
                    wire:model="form.google_analytics_property_id"
                    :label="__('GA4 property ID')"
                    placeholder="123456789"
                    :description="__('In Google Analytics under Admin → Property details.')"
                />

                <flux:textarea
                    wire:model="form.google_analytics_credentials"
                    rows="5"
                    class="font-mono text-sm"
                    :label="__('Service account JSON')"
                    :placeholder="$this->hint('google_analytics_credentials') !== '' ? $this->hint('google_analytics_credentials') : __('Paste the JSON key file…')"
                    :description="$this->hint('google_analytics_credentials') !== '' ? __('Leave blank to keep the saved key file.') : null"
                />
            </div>

            <x-admin.integration-actions :connected="$this->connected" />
        </form>
    </flux:modal>
</div>
