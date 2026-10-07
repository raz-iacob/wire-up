<?php

declare(strict_types=1);

use App\Actions\DeleteUserAction;
use App\Actions\SyncSubscriptionFromCheckoutAction;
use App\Actions\UpdateUserAction;
use App\Actions\UpdateUserPasswordAction;
use App\Models\Record;
use App\Models\User;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Cashier\Exceptions\InvalidCustomer;
use Laravel\Cashier\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

return new class extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public bool $emailVerified = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $delete_password = '';

    public bool $justSubscribed = false;

    public function mount(#[CurrentUser] User $user, SyncSubscriptionFromCheckoutAction $sync): void
    {
        $this->user = $user;

        $subscriptionSession = request()->string('subscription_session')->value();

        if ($subscriptionSession !== '') {
            $this->justSubscribed = true;

            try {
                $sync->handle($user, $subscriptionSession);
            } catch (ApiErrorException $exception) {
                report($exception);
            }
        }

        $this->name = $user->name;
        $this->email = $user->email;
        $this->emailVerified = $user->hasVerifiedEmail();
    }

    public function updateProfile(UpdateUserAction $action): void
    {
        $credentials = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user->id)],
        ], attributes: [
            'name' => __('name'),
            'email' => __('email address'),
        ]);

        $action->handle($this->user, $credentials);

        $this->emailVerified = $this->user->refresh()->hasVerifiedEmail();

        Flux::toast(__('Your details have been saved.'), variant: 'success');
    }

    public function updatePassword(UpdateUserPasswordAction $action): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ], attributes: [
            'current_password' => __('current password'),
            'password' => __('new password'),
        ]);

        $action->handle($this->user, $this->password);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        Flux::toast(__('Your password has been updated.'), variant: 'success');
    }

    public function resendVerification(): void
    {
        if ($this->user->hasVerifiedEmail()) {
            $this->emailVerified = true;

            return;
        }

        $this->user->sendEmailVerificationNotification();

        Flux::toast(__('A new verification link has been sent to your email address.'), variant: 'success');
    }

    /**
     * @return array<int, array{name: string, status: string}>
     */
    #[Computed]
    public function subscriptions(): array
    {
        return Subscription::query()->where('user_id', $this->user->id)->latest()->get()
            ->map(fn (Subscription $subscription): array => [
                'name' => $this->subscriptionName($subscription),
                'status' => $this->subscriptionStatus($subscription),
            ])
            ->all();
    }

    public function manageBilling(): void
    {
        try {
            $url = $this->user->billingPortalUrl(route('account'));
        } catch (ApiErrorException|InvalidCustomer $exception) {
            report($exception);

            $this->addError('billing', __('Billing cannot be managed right now. Please try again later.'));

            return;
        }

        $this->redirect($url);
    }

    public function logout(): void
    {
        Auth::logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirect(route('home'));
    }

    public function delete(DeleteUserAction $action): void
    {
        $this->validate([
            'delete_password' => ['required', 'current_password'],
        ], attributes: [
            'delete_password' => __('password'),
        ]);

        $action->handle($this->user, 'delete_password');

        Auth::logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirect(route('home'));
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('My account'))
            ->layoutData(['description' => __('Manage your account details and password.')]);
    }

    private function subscriptionName(Subscription $subscription): string
    {
        $record = Record::query()->find((int) Str::after($subscription->type, 'record-'));

        return $record instanceof Record ? $record->displayHeading() : __('Subscription');
    }

    private function subscriptionStatus(Subscription $subscription): string
    {
        return match (true) {
            $subscription->onGracePeriod() => __('Ends :date', ['date' => $subscription->ends_at?->isoFormat('LL')]),
            $subscription->ended() => __('Ended'),
            $subscription->pastDue() => __('Payment failed. Update your card under Manage billing.'),
            $subscription->hasIncompletePayment() => __('Waiting for payment'),
            default => __('Renews automatically'),
        };
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-12 px-(--wire-gutter) py-16">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My account') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Signed in as :email', ['email' => $user->email]) }}</flux:text>
        </div>

        <flux:button wire:click="logout" variant="ghost" icon="arrow-right-start-on-rectangle">
            {{ __('Log out') }}
        </flux:button>
    </div>

    <flux:separator />

    <form wire:submit="updateProfile" class="space-y-6">
        <flux:heading size="lg">{{ __('Details') }}</flux:heading>

        <flux:input wire:model="name" :label="__('Full name')" type="text" required autocomplete="name" />

        <div>
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            @if (! $emailVerified)
                <flux:text class="mt-3">
                    {{ __('Your email address is unverified.') }}
                    <flux:link class="cursor-pointer" wire:click.prevent="resendVerification">
                        {{ __('Re-send the verification email.') }}
                    </flux:link>
                </flux:text>
            @endif
        </div>

        <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
    </form>

    <flux:separator />

    <form wire:submit="updatePassword" class="space-y-6">
        <flux:heading size="lg">{{ __('Password') }}</flux:heading>

        <flux:input
            wire:model="current_password"
            :label="__('Current password')"
            type="password"
            required
            autocomplete="current-password"
            viewable
        />
        <flux:input
            wire:model="password"
            :label="__('New password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />
        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm new password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:button variant="primary" type="submit">{{ __('Update password') }}</flux:button>
    </form>

    <flux:separator />

    <livewire:shared.two-factor />

    <flux:separator />

    @if ($justSubscribed || $this->subscriptions !== [])
        <section class="space-y-4">
            <flux:heading size="lg">{{ __('Subscriptions') }}</flux:heading>

            @if ($justSubscribed && $this->subscriptions === [])
                <flux:text>{{ __('Thank you for subscribing. Your subscription will appear here in a moment.') }}</flux:text>
            @endif

            @foreach ($this->subscriptions as $index => $subscription)
                <div wire:key="subscription-{{ $index }}" class="flex items-center justify-between gap-4">
                    <flux:text variant="strong">{{ $subscription['name'] }}</flux:text>
                    <flux:text>{{ $subscription['status'] }}</flux:text>
                </div>
            @endforeach

            @if ($this->subscriptions !== [])
                <flux:button wire:click="manageBilling" icon="credit-card">{{ __('Manage billing') }}</flux:button>
            @endif

            <flux:error name="billing" />
        </section>

        <flux:separator />
    @endif

    <section class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Delete account') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Permanently delete your account and all of its data. This cannot be undone.') }}</flux:text>
        </div>

        <flux:modal.trigger name="confirm-account-deletion">
            <flux:button variant="danger">{{ __('Delete account') }}</flux:button>
        </flux:modal.trigger>

        <flux:modal name="confirm-account-deletion" :show="$errors->has('delete_password')" class="max-w-lg">
            <form wire:submit="delete" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Are you sure?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Once deleted, your account and all of its data are gone for good. Enter your password to confirm.') }}</flux:text>
                </div>

                <flux:input wire:model="delete_password" :label="__('Password')" type="password" viewable />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" type="submit">{{ __('Delete account') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </section>
</div>
