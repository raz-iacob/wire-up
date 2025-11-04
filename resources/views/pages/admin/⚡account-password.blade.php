<?php

declare(strict_types=1);

use Flux\Flux;
use App\Models\User;
use Livewire\Component;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use App\Actions\UpdateUserPassword;
use Illuminate\Container\Attributes\CurrentUser;

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

<div class="space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Account') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your profile and account settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <x-account-layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
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
</div>