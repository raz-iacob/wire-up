<?php

use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;

new class extends Component
{
    public $layout = 'simple';

    public string $email = '';

    public function mount(): void
    {
        //
    }

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }

    public function render(): View
    {
        return $this->view()
            ->layout('layouts::auth.'.$this->layout);
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col text-center">
        <flux:heading size="xl">{{ __('Forgot password') }}</flux:heading>
        <flux:subheading>{{ __('Enter your email to receive a password reset link') }}</flux:subheading>
    </div>

    <form method="POST" wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        {{-- Email Address --}}
        <flux:input
            wire:model="email"
            :label="__('Email Address')"
            type="email"
            required
            autofocus
            placeholder="email@example.com"
        />

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Email password reset link') }}</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400 rtl:space-x-reverse">
        <span>{{ __('Or, return to') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
    </div>
</div>