<?php

declare(strict_types=1);

use App\Actions\ConfirmTwoFactorAction;
use App\Actions\DisableTwoFactorAction;
use App\Actions\EnableTwoFactorAction;
use App\Actions\RegenerateRecoveryCodesAction;
use App\Models\User;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public User $user;

    public string $code = '';

    public string $disable_password = '';

    public bool $showingRecoveryCodes = false;

    public function mount(#[CurrentUser] User $user): void
    {
        $this->user = $user;
    }

    public function enable(EnableTwoFactorAction $action): void
    {
        $action->handle($this->user);
        $this->forgetState();

        $this->code = '';
        $this->showingRecoveryCodes = false;
    }

    public function confirm(ConfirmTwoFactorAction $action): void
    {
        $this->validate([
            'code' => ['required', 'string'],
        ], attributes: [
            'code' => __('code'),
        ]);

        $action->handle($this->user, $this->code);
        $this->forgetState();

        $this->code = '';
        $this->showingRecoveryCodes = true;

        Flux::toast(__('Two-factor authentication is now on.'), variant: 'success');
    }

    public function disable(DisableTwoFactorAction $action): void
    {
        $this->validate([
            'disable_password' => ['required', 'current_password'],
        ], attributes: [
            'disable_password' => __('password'),
        ]);

        $action->handle($this->user);
        $this->forgetState();

        $this->reset('code', 'disable_password', 'showingRecoveryCodes');

        Flux::modal('disable-two-factor')->close();
        Flux::toast(__('Two-factor authentication is now off.'));
    }

    public function cancelSetup(DisableTwoFactorAction $action): void
    {
        $action->handle($this->user);
        $this->forgetState();

        $this->reset('code', 'showingRecoveryCodes');
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesAction $action): void
    {
        $action->handle($this->user);
        $this->forgetState();

        $this->showingRecoveryCodes = true;

        Flux::toast(__('New recovery codes generated.'), variant: 'success');
    }

    public function showRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = true;
    }

    #[Computed]
    public function confirmed(): bool
    {
        return $this->user->hasEnabledTwoFactorAuthentication();
    }

    #[Computed]
    public function awaitingConfirmation(): bool
    {
        return $this->user->two_factor_secret !== null && ! $this->confirmed();
    }

    #[Computed]
    public function qrCode(): string
    {
        return $this->user->twoFactorQrCodeSvg();
    }

    #[Computed]
    public function setupKey(): string
    {
        return (string) Fortify::currentEncrypter()->decrypt((string) $this->user->two_factor_secret);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function recoveryCodes(): array
    {
        /** @var array<int, string> $codes */
        $codes = $this->user->recoveryCodes();

        return $codes;
    }

    private function forgetState(): void
    {
        unset($this->confirmed, $this->awaitingConfirmation, $this->qrCode, $this->setupKey, $this->recoveryCodes);
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('Two-factor authentication') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Require a code from your authenticator app when you sign in.') }}</flux:text>
    </div>

    @if ($this->confirmed)
        <flux:badge color="green" icon="lock-closed">{{ __('On') }}</flux:badge>

        <div class="flex flex-wrap gap-3">
            @unless ($this->showingRecoveryCodes)
                <flux:button wire:click="showRecoveryCodes" variant="subtle" icon="key">
                    {{ __('Show recovery codes') }}
                </flux:button>
            @endunless

            <flux:modal.trigger name="disable-two-factor">
                <flux:button variant="danger" icon="lock-open">{{ __('Turn off') }}</flux:button>
            </flux:modal.trigger>
        </div>
    @elseif ($this->awaitingConfirmation)
        <div class="space-y-4">
            <flux:text>{{ __('Scan this with your authenticator app, then enter the code it shows.') }}</flux:text>

            <div data-two-factor-qr class="inline-flex rounded-(--wire-radius) bg-white p-3">{!! $this->qrCode !!}</div>

            <flux:input
                :label="__('Setup key')"
                :value="$this->setupKey"
                readonly
                :description="__('Enter this by hand if you cannot scan the code.')"
            />

            <form wire:submit="confirm" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:input
                    wire:model="code"
                    :label="__('Code from your app')"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    class="sm:max-w-48"
                />

                <div class="flex gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Confirm') }}</flux:button>
                    <flux:button wire:click="cancelSetup" variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </div>
    @else
        <flux:button wire:click="enable" variant="primary" icon="lock-closed">
            {{ __('Turn on two-factor authentication') }}
        </flux:button>
    @endif

    @if ($this->showingRecoveryCodes && ($this->confirmed || $this->awaitingConfirmation))
        <flux:callout icon="key">
            <flux:callout.heading>{{ __('Recovery codes') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Save these somewhere safe. Each one works once if you lose your authenticator app.') }}
            </flux:callout.text>

            <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                @foreach ($this->recoveryCodes as $recoveryCode)
                    <div wire:key="recovery-{{ $loop->index }}">{{ $recoveryCode }}</div>
                @endforeach
            </div>

            <flux:button
                wire:click="regenerateRecoveryCodes"
                variant="subtle"
                size="sm"
                icon="arrow-path"
                class="mt-4"
            >{{ __('Generate new codes') }}</flux:button>
        </flux:callout>
    @endif

    <flux:modal name="disable-two-factor" class="min-w-[22rem]">
        <form wire:submit="disable" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Turn off two-factor authentication?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('You will only need your password to sign in. Enter your password to confirm.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="disable_password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Turn off') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
