<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\OffsiteBackups;
use App\Livewire\Forms\OffsiteBackupsIntegrationForm;
use App\Services\IntegrationTester;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public OffsiteBackupsIntegrationForm $form;

    public function mount(): void
    {
        $this->form->offsite_endpoint = $this->savedSetting('offsite_endpoint');
        $this->form->offsite_region = $this->savedSetting('offsite_region') ?: 'auto';
        $this->form->offsite_bucket = $this->savedSetting('offsite_bucket');
        $this->form->offsite_folder = $this->savedSetting('offsite_folder') ?: (string) parse_url(config()->string('app.url'), PHP_URL_HOST);
        $this->form->offsite_access_key = $this->savedSetting('offsite_access_key');
    }

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $action->handle($this->validatedSettings());

        $this->form->reset('offsite_secret_key');

        $this->finish(__('Off-site backups connected.'));
    }

    public function test(IntegrationTester $tester): void
    {
        $this->authorize('settings.edit');

        $settings = $this->validatedSettings();

        if (! $this->allowsAnotherTest()) {
            return;
        }

        $this->reportTest($tester->offsiteBackups($settings));
    }

    protected function integration(): OffsiteBackups
    {
        return new OffsiteBackups;
    }

    /**
     * @return array<string, string>
     */
    private function validatedSettings(): array
    {
        foreach (array_keys($this->form->all()) as $field) {
            $this->form->{$field} = mb_trim((string) $this->form->{$field});
        }

        $this->form->offsite_folder = mb_trim($this->form->offsite_folder, '/');

        $validated = $this->form->validate();

        return [
            'offsite_endpoint' => mb_rtrim((string) $validated['offsite_endpoint'], '/'),
            'offsite_region' => (string) $validated['offsite_region'] ?: 'auto',
            'offsite_bucket' => (string) $validated['offsite_bucket'],
            'offsite_folder' => (string) $validated['offsite_folder'],
            'offsite_access_key' => (string) $validated['offsite_access_key'],
            'offsite_secret_key' => $this->typedOrSaved($validated['offsite_secret_key'], 'offsite_secret_key'),
        ];
    }
};
?>

<div>
    <x-admin.integration-card
        :name="__('Off-site backups')"
        :description="__('Copy every backup to Cloudflare R2, Backblaze B2, Amazon S3 or other S3-compatible storage.')"
        modal="integration-offsite-backups"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <flux:icon name="cloud-arrow-up" class="text-accent size-6" />
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-offsite-backups" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect off-site backups') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Each backup is copied to this bucket once it is made, so it survives losing the server. Use a key that can only reach this bucket.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="form.offsite_endpoint"
                :label="__('Endpoint')"
                placeholder="https://…r2.cloudflarestorage.com"
                :description="__('Leave blank for Amazon S3.')"
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.offsite_bucket" :label="__('Bucket')" />
                <flux:input wire:model="form.offsite_region" :label="__('Region')" />
            </div>

            <flux:input
                wire:model="form.offsite_folder"
                :label="__('Folder')"
                :badge="__('Optional')"
                :description="__('Lets several sites share one bucket.')"
            />

            <flux:input wire:model="form.offsite_access_key" :label="__('Access key ID')" autocomplete="off" />

            <flux:input
                wire:model="form.offsite_secret_key"
                type="password"
                viewable
                autocomplete="new-password"
                :label="__('Secret access key')"
                :placeholder="$this->connected ? $this->hint('offsite_secret_key') : null"
                :description="$this->connected ? __('Leave blank to keep the saved key.') : null"
            />

            <x-admin.integration-actions
                :connected="$this->connected"
                :test="__('Writes, lists and deletes a small test file')"
            />
        </form>
    </flux:modal>
</div>
