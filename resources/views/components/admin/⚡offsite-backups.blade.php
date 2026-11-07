<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\OffsiteBackups;
use App\Livewire\Forms\OffsiteBackupsIntegrationForm;
use App\Services\IntegrationTester;
use App\Services\OffsiteBackupService;
use App\Services\SettingsService;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public OffsiteBackupsIntegrationForm $form;

    public bool $enabled = false;

    public int $keep = 14;

    public function mount(): void
    {
        $this->enabled = $this->connected();
        $this->keep = SettingsService::current()->offsiteBackupsToKeep();

        $this->fillForm();
    }

    public function save(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        if (! $this->enabled) {
            $action->handle(array_fill_keys($this->integration()->disconnects(), ''));

            $this->form->reset();
            $this->fillForm();
            unset($this->connected);

            $this->finish(__('Off-site copies are off.'), notify: true);

            return;
        }

        $settings = $this->validatedSettings();

        $this->validate(
            ['keep' => ['required', 'integer', 'min:1', 'max:100']],
            attributes: ['keep' => __('copies to keep')],
        );

        $action->handle([...$settings, 'offsite_backups_to_keep' => $this->keep]);

        $this->form->reset('offsite_secret_key');
        unset($this->connected);

        $this->finish(__('Off-site copies saved.'), notify: true);
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

    public function location(): string
    {
        return resolve(OffsiteBackupService::class)->location();
    }

    protected function integration(): OffsiteBackups
    {
        return new OffsiteBackups;
    }

    private function fillForm(): void
    {
        $this->form->offsite_endpoint = $this->savedSetting('offsite_endpoint');
        $this->form->offsite_region = $this->savedSetting('offsite_region') ?: 'auto';
        $this->form->offsite_bucket = $this->savedSetting('offsite_bucket');
        $this->form->offsite_folder = $this->savedSetting('offsite_folder') ?: (string) parse_url(config()->string('app.url'), PHP_URL_HOST);
        $this->form->offsite_access_key = $this->savedSetting('offsite_access_key');
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

<form wire:submit="save" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="space-y-6">
    <flux:switch
        wire:model.live="enabled"
        align="left"
        :label="__('Off-site copies')"
        :description="__('Copy every backup to Cloudflare R2, Backblaze B2, Amazon S3 or other S3-compatible storage, so losing the server doesn’t lose the backups.')"
    />

    @if ($enabled)
        @if ($this->connected)
            <flux:text>{{ __('Every backup is also copied to :location.', ['location' => $this->location()]) }}</flux:text>
        @endif

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

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="form.offsite_access_key" :label="__('Access key ID')" autocomplete="off" />

            <flux:input
                wire:model="form.offsite_secret_key"
                type="password"
                viewable
                autocomplete="new-password"
                :label="__('Secret access key')"
                :placeholder="$this->connected ? $this->hint('offsite_secret_key') : null"
            />
        </div>

        <flux:input
            type="number"
            min="1"
            max="100"
            wire:model="keep"
            :label="__('Copies to keep')"
            :description="__('Counted separately from the backups kept on this server.')"
            class="sm:max-w-xs"
        />
    @elseif ($this->connected)
        <flux:text>
            {{ __('Saving turns off off-site copies and forgets the storage keys. Copies already in the bucket stay there.') }}
        </flux:text>
    @endif

    @can('settings.edit')
        @if ($enabled || $this->connected)
            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>

                @if ($enabled)
                    <flux:button wire:click="test" :tooltip="__('Writes, lists and deletes a small test file')">
                        {{ __('Test') }}</flux:button>
                @endif
            </div>
        @endif
    @endcan
</form>
