<?php

declare(strict_types=1);

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public bool $twoFactorMissing = false;

    public bool $showsTokens = false;

    public function mount(#[CurrentUser] User $user): void
    {
        $this->twoFactorMissing = ! RequireTwoFactor::isSatisfiedFor($user);
        $this->showsTokens = SettingsService::current()->remoteMcpEnabled() && $user->can('mcp.use');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Security'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.account-layout>
    <div class="space-y-10">
        @if ($twoFactorMissing)
            <flux:callout icon="shield-exclamation" variant="warning">
                <flux:callout.text>
                    {{ __('Staff need two-factor authentication. Turn it on below, or sign in with a passkey.') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        <livewire:shared.two-factor />

        <flux:separator />

        <livewire:shared.passkeys />

        @if ($showsTokens)
            <flux:separator />

            <livewire:admin.access-tokens />
        @endif
    </div>
</x-admin.account-layout>
