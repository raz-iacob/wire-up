<?php

declare(strict_types=1);

use Flux\Flux;
use App\Models\User;
use Livewire\Component;
use App\Actions\DeleteUser;
use App\Actions\UpdateUser;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Container\Attributes\CurrentUser;

new
#[Layout('layouts::admin')]
class extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public bool $emailVerified = false;

    public string $password = '';

    public function mount(#[CurrentUser] User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->emailVerified = $user instanceof MustVerifyEmail && $user->hasVerifiedEmail();
    }

    public function update(UpdateUser $action): void
    {
        $credentials = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user->id),
            ],
        ]);

        $action->handle($this->user, $credentials);

        Flux::toast(__('Your changes have been saved.'));
    }

    public function resendVerificationLink(): void
    {
        if ($this->user->hasVerifiedEmail()) {
            Flux::toast(__('Your email address is already verified.'));
            return;
        }

        $this->user->sendEmailVerificationNotification();

        Flux::toast(__('A new verification link has been sent to your email address.'));
    }

    public function delete(DeleteUser $action): void
    {
        $this->validate([
            'password' => ['required', 'current_password'],
        ]);

        Auth::logout();

        $action->handle($this->user);

        Session::invalidate();
        Session::regenerateToken();

        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Account') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your profile and account settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <x-account-layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="update" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if (! $emailVerified)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationLink">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>
                    </div>
                @endif
            </div>

            <flux:button variant="primary" type="submit">{{ __('Update') }}</flux:button>
        </form>

        <section class="mt-32 space-y-6">
            <div class="relative mb-5">
                <flux:heading>{{ __('Delete account') }}</flux:heading>
                <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
            </div>

            <flux:modal.trigger name="confirm-user-deletion">
                <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
                    {{ __('Delete account') }}
                </flux:button>
            </flux:modal.trigger>

            <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
                <form wire:submit="delete" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                        <flux:subheading>
                            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                        </flux:subheading>
                    </div>

                    <flux:input wire:model="password" :label="__('Password')" type="password" />

                    <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>

                        <flux:button variant="danger" type="submit">{{ __('Delete account') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </section>
    </x-admin.settings-layout>
</div>