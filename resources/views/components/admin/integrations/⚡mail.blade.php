<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\Mail;
use App\Livewire\Forms\MailIntegrationForm;
use App\Services\IntegrationTester;
use App\Services\SettingsService;
use App\Traits\ConnectsIntegration;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public MailIntegrationForm $form;

    public function mount(): void
    {
        $this->form->mail_provider = array_key_exists((string) config('site.mail_provider'), config()->array('mail_providers')) ? config()->string('site.mail_provider') : 'gmail';
        $this->form->mail_host = $this->savedSetting('mail_host');
        $this->form->mail_port = is_numeric(config('site.mail_port')) ? (int) config('site.mail_port') : 587;
        $this->form->mail_username = $this->savedSetting('mail_username');
        $this->form->mail_encryption = in_array(config('site.mail_encryption'), ['tls', 'ssl'], true) ? config()->string('site.mail_encryption') : 'tls';
        $this->form->mail_from_address = $this->savedSetting('mail_from_address');
        $this->form->mail_from_name = $this->savedSetting('mail_from_name');
    }

    #[Computed]
    public function testEmailRecipient(): string
    {
        $contactEmail = resolve(SettingsService::class)->contactEmail();

        return $contactEmail !== '' ? $contactEmail : (string) auth()->user()?->email;
    }

    public function updatedFormMailProvider(string $value): void
    {
        $preset = config('mail_providers.'.$value);

        if (! is_array($preset) || $value === 'custom') {
            return;
        }

        $this->form->mail_host = (string) ($preset['host'] ?? '');
        $this->form->mail_port = (int) ($preset['port'] ?? 587);
        $this->form->mail_encryption = (string) ($preset['encryption'] ?? 'tls');
        $this->form->mail_username = (string) ($preset['username'] ?? '');
    }

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $this->form->mail_host = mb_trim($this->form->mail_host);
        $this->form->mail_from_address = mb_trim($this->form->mail_from_address);

        $validated = $this->form->validate();

        $action->handle([
            'mail_provider' => $validated['mail_provider'],
            'mail_host' => $validated['mail_host'],
            'mail_port' => $validated['mail_port'],
            'mail_username' => $validated['mail_username'],
            'mail_password' => $this->typedOrSaved($validated['mail_password'], 'mail_password'),
            'mail_encryption' => $validated['mail_encryption'],
            'mail_from_address' => $validated['mail_from_address'],
            'mail_from_name' => mb_trim((string) $validated['mail_from_name']),
        ]);

        $this->form->reset('mail_password');

        $this->finish(__('Email connected.'), notify: true);
    }

    public function test(IntegrationTester $tester): void
    {
        $this->authorize('settings.edit');

        $this->form->mail_host = mb_trim($this->form->mail_host);
        $this->form->mail_from_address = mb_trim($this->form->mail_from_address);

        $validated = $this->form->validate();

        if (! $this->allowsAnotherTest()) {
            return;
        }

        $this->reportTest($tester->mail([
            'host' => $validated['mail_host'],
            'port' => $validated['mail_port'],
            'encryption' => $validated['mail_encryption'],
            'username' => $validated['mail_username'],
            'password' => $this->typedOrSaved($validated['mail_password'], 'mail_password'),
            'from_address' => $validated['mail_from_address'],
            'from_name' => mb_trim((string) $validated['mail_from_name']),
        ], $this->testEmailRecipient()));
    }

    protected function integration(): Mail
    {
        return new Mail;
    }
};
?>

<div>
    <x-admin.integration-card
        :name="__('Email')"
        :description="__('Send form notifications and system email through your own SMTP provider.')"
        modal="integration-mail"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <flux:icon name="envelope" class="text-accent size-6" />
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-mail" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect email (SMTP)') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Pick a provider or choose Custom SMTP, then add your credentials. Form notifications and system email will send from your account.') }}</flux:text>
            </div>

            <flux:select wire:model.live="form.mail_provider" :label="__('Provider')">
                @foreach (config('mail_providers') as $key => $preset)
                    <flux:select.option value="{{ $key }}">{{ $preset['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <div x-show="$wire.form.mail_provider === 'custom'" x-cloak class="space-y-6">
                <flux:input wire:model="form.mail_host" :label="__('SMTP host')" placeholder="smtp.example.com" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="number" wire:model="form.mail_port" :label="__('Port')" />
                    <flux:select wire:model="form.mail_encryption" :label="__('Encryption')">
                        <flux:select.option value="tls">{{ __('TLS (STARTTLS)') }}</flux:select.option>
                        <flux:select.option value="ssl">SSL</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <flux:input
                wire:model="form.mail_username"
                :label="__('Username')"
                :placeholder="__('Usually your email address or API user')"
            />
            <flux:input
                wire:model="form.mail_password"
                type="password"
                viewable
                :label="__('Password or API key')"
                :placeholder="$this->connected ? $this->hint('mail_password') : __('Paste your SMTP password or API key…')"
                :description="$this->connected ? __('Leave blank to keep the saved password.') : null"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="form.mail_from_address"
                    type="email"
                    :label="__('From address')"
                    placeholder="hello@yoursite.com"
                />
                <flux:input wire:model="form.mail_from_name" :label="__('From name')" :placeholder="__('Your Site')" />
            </div>

            <x-admin.integration-actions
                :connected="$this->connected"
                :test="__('Sends a test message to :email', ['email' => $this->testEmailRecipient])"
            />
        </form>
    </flux:modal>
</div>
