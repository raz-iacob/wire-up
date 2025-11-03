<?php

declare(strict_types=1);

use Flux\Flux;
use App\Models\User;
use Livewire\Component;
use App\Actions\UpdateUser;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
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

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </form>

        {{-- <livewire:admin.settings.delete-user-form /> --}}
    </x-admin.settings-layout>
</div>