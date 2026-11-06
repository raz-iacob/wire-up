<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\Stripe as StripeIntegration;
use App\Livewire\Forms\StripeIntegrationForm;
use App\Services\PublicUrlGuard;
use App\Services\StripeService;
use App\Traits\ConnectsIntegration;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

return new class extends Component
{
    use ConnectsIntegration;

    public StripeIntegrationForm $form;

    public function mount(): void
    {
        $this->form->stripe_publishable_key = $this->savedSetting('stripe_publishable_key');
    }

    #[Computed]
    public function stripeWebhookIsAutomatic(): bool
    {
        return $this->savedSetting('stripe_webhook_endpoint_id') !== '';
    }

    #[Computed]
    public function stripeWebhookUrl(): string
    {
        return route('cashier.webhook');
    }

    public function connect(UpdateSettingsAction $action, StripeService $stripe, PublicUrlGuard $guard): void
    {
        $this->authorize('settings.edit');

        $this->form->stripe_publishable_key = mb_trim($this->form->stripe_publishable_key);
        $this->form->stripe_secret_key = mb_trim($this->form->stripe_secret_key);
        $this->form->stripe_webhook_secret = mb_trim($this->form->stripe_webhook_secret);

        $validated = $this->form->validate();

        $publishableKey = (string) $validated['stripe_publishable_key'];
        $secretKey = (string) ($validated['stripe_secret_key'] ?: $this->savedSetting('stripe_secret_key'));

        if ($secretKey === '') {
            $this->addError('form.stripe_secret_key', __('Enter your Stripe secret key.'));

            return;
        }

        if ($stripe->isLiveKey($publishableKey) !== $stripe->isLiveKey($secretKey)) {
            $this->addError('form.stripe_secret_key', __('Both keys must be test keys, or both live keys.'));

            return;
        }

        try {
            $accountName = $stripe->accountName($secretKey);
            $webhook = $this->stripeWebhook($stripe, $guard, $secretKey, (string) $validated['stripe_webhook_secret']);
        } catch (ApiErrorException $exception) {
            $this->addError('form.stripe_secret_key', __('Stripe said: :reason', ['reason' => Str::limit($exception->getMessage(), 200)]));

            return;
        }

        $action->handle([
            'stripe_publishable_key' => $publishableKey,
            'stripe_secret_key' => $secretKey,
            'stripe_webhook_secret' => $webhook['secret'],
            'stripe_webhook_endpoint_id' => $webhook['id'],
        ]);

        $this->form->reset('stripe_secret_key', 'stripe_webhook_secret');

        $this->finish(__('Stripe connected to :account.', ['account' => $accountName]));
    }

    public function disconnect(UpdateSettingsAction $action, StripeService $stripe): void
    {
        $this->authorize('settings.edit');

        $secretKey = $this->savedSetting('stripe_secret_key');
        $endpointId = $this->savedSetting('stripe_webhook_endpoint_id');

        if ($secretKey !== '' && $endpointId !== '') {
            try {
                $stripe->deleteWebhookEndpoint($secretKey, $endpointId);
            } catch (ApiErrorException) {
                Flux::toast(__('Disconnected, but the webhook could not be removed from Stripe. Delete it in your Stripe dashboard.'), variant: 'warning');
            }
        }

        $action->handle(array_fill_keys($this->integration()->disconnects(), ''));

        $this->form->reset();

        $this->finish(__('Disconnected.'));
    }

    protected function integration(): StripeIntegration
    {
        return new StripeIntegration;
    }

    /**
     * @return array{id: string, secret: string}
     */
    private function stripeWebhook(StripeService $stripe, PublicUrlGuard $guard, string $secretKey, string $typedSecret): array
    {
        try {
            $guard->assertPublic($this->stripeWebhookUrl());
        } catch (InvalidArgumentException) {
            return ['id' => '', 'secret' => $typedSecret !== '' ? $typedSecret : $this->savedSetting('stripe_webhook_secret')];
        }

        $previousSecretKey = $this->savedSetting('stripe_secret_key');
        $previousEndpointId = $this->savedSetting('stripe_webhook_endpoint_id');

        if ($previousSecretKey !== '' && $previousEndpointId !== '') {
            $stripe->deleteWebhookEndpoint($previousSecretKey, $previousEndpointId);
        }

        return $stripe->createWebhookEndpoint($secretKey, $this->stripeWebhookUrl());
    }
};
?>

<div>
    <x-admin.integration-card
        name="Stripe"
        :description="__('Take payments for products and subscriptions.')"
        modal="integration-stripe"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <svg viewBox="0 0 32 32" class="size-7" xmlns="http://www.w3.org/2000/svg">
                <rect width="32" height="32" rx="6" fill="#635BFF" />
                <path fill="#fff" d="M14.9 12.6c0-.9.8-1.3 2-1.3 1.8 0 4 .5 5.8 1.5V7.3a15.4 15.4 0 0 0-5.8-1.1c-4.7 0-7.9 2.5-7.9 6.6 0 6.4 8.8 5.4 8.8 8.2 0 1.1-.9 1.4-2.2 1.4-1.9 0-4.4-.8-6.3-1.9v5.6c2.1.9 4.3 1.3 6.3 1.3 4.8 0 8.2-2.4 8.2-6.6-.1-6.9-8.9-5.7-8.9-8.2Z" />
            </svg>
        </x-slot:logo>
        <x-slot:badge>
            @if ($this->connected)
                <flux:badge
                    size="sm"
                    :color="str_contains($this->form->stripe_publishable_key, '_live_') ? 'green' : 'amber'"
                >
                    {{ str_contains($this->form->stripe_publishable_key, '_live_') ? __('Live') : __('Test mode') }}
                </flux:badge>
            @endif
        </x-slot:badge>
    </x-admin.integration-card>

    <flux:modal name="integration-stripe" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect Stripe') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Find both keys in your Stripe dashboard under Developers → API keys.') }}</flux:text>
            </div>

            <flux:input
                wire:model="form.stripe_publishable_key"
                :label="__('Publishable key')"
                placeholder="pk_test_…"
            />

            <flux:input
                wire:model="form.stripe_secret_key"
                type="password"
                viewable
                :label="__('Secret key')"
                :placeholder="$this->hint('stripe_secret_key') !== '' ? $this->hint('stripe_secret_key') : 'sk_test_…'"
                :description="$this->hint('stripe_secret_key') !== '' ? __('Leave blank to keep the saved key.') : null"
            />

            <flux:separator variant="subtle" />

            @if ($this->stripeWebhookIsAutomatic())
                <flux:text>{{ __('Stripe sends payment updates to this site automatically.') }}</flux:text>
            @else
                <div class="space-y-4">
                    <flux:input
                        :value="$this->stripeWebhookUrl()"
                        readonly
                        copyable
                        :label="__('Webhook URL')"
                        :description="__('Set up automatically when this site is publicly reachable. Otherwise add this URL as a webhook in Stripe and paste its signing secret below.')"
                    />

                    <flux:input
                        wire:model="form.stripe_webhook_secret"
                        type="password"
                        viewable
                        :label="__('Webhook signing secret')"
                        placeholder="whsec_…"
                    />
                </div>
            @endif

            <x-admin.integration-actions :connected="$this->connected" />
        </form>
    </flux:modal>
</div>
