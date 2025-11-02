<?php

declare(strict_types=1);

use App\Actions\CreateUserPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as RulesPassword;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public $layout = 'simple';

    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email')->value();
    }

    public function resetPassword(CreateUserPassword $action): void
    {
        $credentials = $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', RulesPassword::defaults()],
        ]);

        $status = $action->handle($credentials, $this->password);

        throw_if($status !== Password::PASSWORD_RESET, ValidationException::withMessages([
            'email' => [__(is_string($status) ? $status : '')],
        ]));

        $this->redirectRoute('login', navigate: true);
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
        <flux:heading size="xl">{{ __('Reset password') }}</flux:heading>
        <flux:subheading>{{ __('Please enter your new password below') }}</flux:subheading>
    </div>
    
    <form method="POST" wire:submit="resetPassword" class="flex flex-col gap-6">
        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('Password')"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm password')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('Confirm password')"
            viewable
        />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Reset password') }}
            </flux:button>
        </div>
    </form>
</div>