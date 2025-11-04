<?php

declare(strict_types=1);

use App\Actions\UpdateUserPassword;
use App\Models\User;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::admin')]
class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function update(#[CurrentUser] User $user, UpdateUserPassword $action): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ]);

        $action->handle($user, $this->password);

        Flux::toast(__('Your password has been updated successfully.'));
    }
};
?>

<x-account-layout :heading="__('Change your password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
    <form method="POST" wire:submit="update" class="mt-6 space-y-6">
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
            :label="__('Confirm Password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:button variant="primary" type="submit">{{ __('Update') }}</flux:button>
    </form>
</x-admin.settings-layout>