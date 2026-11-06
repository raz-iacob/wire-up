<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\Newsletter;
use App\Livewire\Forms\NewsletterIntegrationForm;
use App\Services\IntegrationTester;
use App\Services\NewsletterService;
use App\Traits\ConnectsIntegration;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public NewsletterIntegrationForm $form;

    public function mount(): void
    {
        $this->form->newsletter_provider = array_key_exists($this->savedSetting('newsletter_provider'), NewsletterService::PROVIDERS) ? $this->savedSetting('newsletter_provider') : 'mailchimp';
        $this->form->newsletter_list_id = $this->savedSetting('newsletter_list_id');
        $this->form->newsletter_double_opt_in = (bool) config('site.newsletter_double_opt_in', true);
    }

    #[Computed]
    public function providerName(): ?string
    {
        return resolve(NewsletterService::class)->providerName();
    }

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validatedForm();

        $action->handle([
            'newsletter_provider' => $validated['newsletter_provider'],
            'newsletter_api_key' => $this->apiKey(),
            'newsletter_list_id' => $this->form->usesList() ? (string) $validated['newsletter_list_id'] : '',
            'newsletter_double_opt_in' => (bool) $validated['newsletter_double_opt_in'],
        ]);

        $this->form->reset('newsletter_api_key');

        $this->finish(__(':service connected.', ['service' => NewsletterService::PROVIDERS[$validated['newsletter_provider']]]));
    }

    public function test(IntegrationTester $tester): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validatedForm();

        if (! $this->allowsAnotherTest()) {
            return;
        }

        $this->reportTest($tester->newsletter($validated['newsletter_provider'], $this->apiKey(), (string) $validated['newsletter_list_id']));
    }

    protected function integration(): Newsletter
    {
        return new Newsletter;
    }

    /**
     * @return array{newsletter_provider: string, newsletter_api_key: ?string, newsletter_list_id: ?string, newsletter_double_opt_in: bool}
     */
    private function validatedForm(): array
    {
        $this->form->newsletter_api_key = mb_trim($this->form->newsletter_api_key);
        $this->form->newsletter_list_id = mb_trim($this->form->newsletter_list_id);

        /** @var array{newsletter_provider: string, newsletter_api_key: ?string, newsletter_list_id: ?string, newsletter_double_opt_in: bool} $validated */
        $validated = $this->form->validate();

        return $validated;
    }

    private function apiKey(): string
    {
        $typed = $this->form->newsletter_api_key;

        return $typed !== '' || ! $this->form->keepsSavedKey() ? $typed : $this->savedSetting('newsletter_api_key');
    }
};
?>

<div>
    <x-admin.integration-card
        :name="__('Newsletter')"
        :description="__('Add newsletter block sign-ups to Mailchimp, Kit or Buttondown.')"
        modal="integration-newsletter"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <flux:icon name="newspaper" class="text-accent size-6" />
        </x-slot:logo>
        <x-slot:badge>
            @if ($this->connected)
                <flux:badge size="sm" color="zinc">{{ $this->providerName }}</flux:badge>
            @endif
        </x-slot:badge>
    </x-admin.integration-card>

    <flux:modal name="integration-newsletter" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect a newsletter') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Sign-ups stay in the Inbox and are added to your list too.') }}</flux:text>
            </div>

            <flux:select wire:model.live="form.newsletter_provider" :label="__('Service')">
                @foreach (\App\Services\NewsletterService::PROVIDERS as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @php($newsletterKeySaved = $this->form->keepsSavedKey())
            <flux:input
                wire:model="form.newsletter_api_key"
                type="password"
                viewable
                :label="__('API key')"
                :placeholder="$newsletterKeySaved ? $this->hint('newsletter_api_key') : __('Paste your API key…')"
                :description="$newsletterKeySaved ? __('Leave blank to keep the saved key.') : $this->form->apiKeyHelp()"
            />

            @if ($this->form->usesList())
                <flux:input
                    wire:model="form.newsletter_list_id"
                    :label="$this->form->newsletter_provider === 'kit' ? __('Form ID') : __('Audience ID')"
                    :description="$this->form->listHelp()"
                />
            @endif

            @if ($this->form->offersDoubleOptIn())
                <flux:switch
                    wire:model="form.newsletter_double_opt_in"
                    :label="__('Ask people to confirm their email')"
                    align="left"
                />
            @endif

            <x-admin.integration-actions
                :connected="$this->connected"
                :test="__('Checks the key and list with the service')"
            />
        </form>
    </flux:modal>
</div>
