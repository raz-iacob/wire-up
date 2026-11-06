<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\Turnstile;
use App\Livewire\Forms\TurnstileIntegrationForm;
use App\Services\IntegrationTester;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public TurnstileIntegrationForm $form;

    public function mount(): void
    {
        $this->form->turnstile_site_key = $this->savedSetting('turnstile_site_key');
    }

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $this->form->turnstile_site_key = mb_trim($this->form->turnstile_site_key);
        $this->form->turnstile_secret_key = mb_trim($this->form->turnstile_secret_key);

        $validated = $this->form->validate();

        $action->handle([
            'turnstile_site_key' => $validated['turnstile_site_key'],
            'turnstile_secret_key' => $this->typedOrSaved($validated['turnstile_secret_key'], 'turnstile_secret_key'),
        ]);

        $this->form->reset('turnstile_secret_key');

        $this->finish(__('Cloudflare Turnstile connected.'));
    }

    public function test(IntegrationTester $tester): void
    {
        $this->authorize('settings.edit');

        $this->form->turnstile_secret_key = mb_trim($this->form->turnstile_secret_key);

        $validated = $this->form->validateOnly('turnstile_secret_key');

        if (! $this->allowsAnotherTest()) {
            return;
        }

        $this->reportTest($tester->turnstile($this->typedOrSaved($validated['turnstile_secret_key'] ?? '', 'turnstile_secret_key')));
    }

    protected function integration(): Turnstile
    {
        return new Turnstile;
    }
};
?>

<div>
    <x-admin.integration-card
        name="Cloudflare Turnstile"
        :description="__('Keep bots out of your forms and sign-in pages without a puzzle.')"
        modal="integration-turnstile"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <flux:icon name="shield-check" class="text-accent size-6" />
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-turnstile" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect Cloudflare Turnstile') }}</flux:heading>
                @php($turnstileLink = '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer" class="underline">'.__('Turnstile dashboard').'</a>')
                <flux:text class="mt-2">{!! __('Checks that a person, not a bot, is sending the contact, newsletter, sign-in, sign-up and password reset forms. Add this site in the :dashboard and paste its keys.', ['dashboard' => $turnstileLink]) !!}</flux:text>
            </div>

            <flux:input wire:model="form.turnstile_site_key" :label="__('Site key')" placeholder="0x4AAAAAAA…" />

            <flux:input
                wire:model="form.turnstile_secret_key"
                type="password"
                viewable
                :label="__('Secret key')"
                :placeholder="$this->connected ? $this->hint('turnstile_secret_key') : '0x4AAAAAAA…'"
                :description="$this->connected ? __('Leave blank to keep the saved key.') : null"
            />

            <x-admin.integration-actions
                :connected="$this->connected"
                :test="__('Checks the secret key with Cloudflare')"
            />
        </form>
    </flux:modal>
</div>
