<?php

declare(strict_types=1);

use App\Actions\DeleteUserAction;
use App\Actions\UpdateUserAction;
use App\Actions\UpdateUserPasswordAction;
use App\Models\User;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;

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

    public function mount(#[CurrentUser] User $user): void
    {
        $this->user = $user;
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

        Auth::logout();

        $action->handle($this->user);

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
};
?>

<div class="mx-auto w-full max-w-2xl px-(--wire-gutter) py-16 space-y-12">
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

        <flux:input wire:model="current_password" :label="__('Current password')" type="password" required autocomplete="current-password" viewable />
        <flux:input wire:model="password" :label="__('New password')" type="password" required autocomplete="new-password" viewable />
        <flux:input wire:model="password_confirmation" :label="__('Confirm new password')" type="password" required autocomplete="new-password" viewable />

        <flux:button variant="primary" type="submit">{{ __('Update password') }}</flux:button>
    </form>

    <flux:separator />

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
