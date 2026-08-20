<?php

declare(strict_types=1);

use App\Actions\VerifyTwoFactorCodeAction;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

return new class extends Component
{
    public string $code = '';

    public string $recovery_code = '';

    public bool $usingRecoveryCode = false;

    public function mount(): void
    {
        if (! $this->challengedUser() instanceof User) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function useRecoveryCode(): void
    {
        $this->usingRecoveryCode = true;
        $this->reset('code', 'recovery_code');
        $this->resetValidation();
    }

    public function useAuthenticatorCode(): void
    {
        $this->usingRecoveryCode = false;
        $this->reset('code', 'recovery_code');
        $this->resetValidation();
    }

    public function verify(VerifyTwoFactorCodeAction $action): void
    {
        $user = $this->challengedUser();

        if (! $user instanceof User) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->ensureIsNotRateLimited($user);

        if (! $action->handle($user, $this->code, $this->recovery_code)) {
            RateLimiter::hit($this->throttleKey($user));

            $usedRecoveryCode = $this->usingRecoveryCode;

            $this->reset('code', 'recovery_code');

            throw ValidationException::withMessages([
                $usedRecoveryCode ? 'recovery_code' : 'code' => $usedRecoveryCode
                    ? __('That recovery code is not valid.')
                    : __('That code is not correct. Check your authenticator app and try again.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($user));

        $remember = (bool) Session::pull('login.remember', false);
        Session::forget('login.id');

        Auth::login($user, $remember);
        Session::regenerate();

        if ($user->canAccessAdmin()) {
            $this->redirectIntended(default: route('admin.dashboard', absolute: false), navigate: true);

            return;
        }

        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Two-factor authentication'))
            ->layout('layouts::auth.'.resolve(SettingsService::class)->authLayout());
    }

    private function challengedUser(): ?User
    {
        $id = Session::get('login.id');

        if (! is_int($id) && ! is_string($id)) {
            return null;
        }

        $user = User::query()->find($id);

        return $user?->hasEnabledTwoFactorAuthentication() === true ? $user : null;
    }

    private function ensureIsNotRateLimited(User $user): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($user), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey($user));

        throw ValidationException::withMessages([
            'code' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(User $user): string
    {
        return 'two-factor|'.$user->id.'|'.request()->ip();
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2 text-center">
        @if ($usingRecoveryCode)
            <flux:heading size="xl">{{ __('Enter a recovery code') }}</flux:heading>
            <flux:text>{{ __('Use one of the recovery codes you saved when you turned on two-factor authentication.') }}</flux:text>
        @else
            <flux:heading size="xl">{{ __('Enter your authentication code') }}</flux:heading>
            <flux:text>{{ __('Open your authenticator app and enter the code it shows for this site.') }}</flux:text>
        @endif
    </div>

    <form method="POST" wire:submit="verify" class="flex flex-col gap-6">
        @if ($usingRecoveryCode)
            <flux:input
                wire:model="recovery_code"
                :label="__('Recovery code')"
                autocomplete="one-time-code"
                autofocus
                required
            />
        @else
            <flux:field class="flex flex-col items-center gap-3">
                <flux:label class="sr-only">{{ __('Authentication code') }}</flux:label>
                <flux:otp wire:model="code" length="6" autofocus />
                <flux:error name="code" />
            </flux:field>
        @endif

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Continue') }}</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 rtl:space-x-reverse dark:text-zinc-400">
        @if ($usingRecoveryCode)
            <flux:link wire:click="useAuthenticatorCode" class="cursor-pointer">
                {{ __('Use an authentication code instead') }}
            </flux:link>
        @else
            <flux:link wire:click="useRecoveryCode" class="cursor-pointer">
                {{ __('Use a recovery code instead') }}
            </flux:link>
        @endif
    </div>
</div>
