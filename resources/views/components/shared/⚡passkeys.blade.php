<?php

declare(strict_types=1);

use App\Actions\AddPasskeyAction;
use App\Actions\RemovePasskeyAction;
use App\Actions\StartPasskeyRegistrationAction;
use App\Models\User;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Passkeys\Passkey;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public User $user;

    public string $name = '';

    public string $password = '';

    public ?int $removingId = null;

    public string $remove_password = '';

    public function mount(#[CurrentUser] User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function registrationOptions(StartPasskeyRegistrationAction $action): array
    {
        $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'current_password'],
        ], attributes: [
            'name' => __('name'),
            'password' => __('password'),
        ]);

        return $action->handle($this->user);
    }

    /**
     * @param  array<array-key, mixed>  $credential
     */
    public function add(array $credential, AddPasskeyAction $action): void
    {
        $action->handle($this->user, mb_trim($this->name), $credential);
        unset($this->passkeys);

        $this->reset('name', 'password');

        Flux::modal('add-passkey')->close();
        Flux::toast(__('Passkey added.'), variant: 'success');
    }

    public function confirmRemoval(int $id): void
    {
        $this->removingId = $id;
        $this->reset('remove_password');
        $this->resetErrorBag();

        Flux::modal('remove-passkey')->show();
    }

    public function remove(RemovePasskeyAction $action): void
    {
        $this->validate([
            'remove_password' => ['required', 'current_password'],
        ], attributes: [
            'remove_password' => __('password'),
        ]);

        $action->handle($this->user, $this->user->passkeys()->findOrFail($this->removingId));
        unset($this->passkeys);

        $this->reset('removingId', 'remove_password');

        Flux::modal('remove-passkey')->close();
        Flux::toast(__('Passkey removed.'));
    }

    /**
     * @return Collection<int, Passkey>
     */
    #[Computed]
    public function passkeys(): Collection
    {
        return $this->user->passkeys()->latest('id')->get();
    }
};
?>

<div
    class="space-y-6"
    x-data="passkeyRegistration({
        duplicate: @js(__('This device already has a passkey for your account.')),
        failed: @js(__("That passkey didn't work. Please try again.")),
    })"
>
    <div>
        <flux:heading size="lg">{{ __('Passkeys') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Sign in with your fingerprint, face or device PIN instead of a password.') }}</flux:text>
    </div>

    @if ($this->passkeys->isNotEmpty())
        <div class="divide-y divide-zinc-800/10 dark:divide-white/10">
            @foreach ($this->passkeys as $passkey)
                <div wire:key="passkey-{{ $passkey->id }}" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <flux:heading class="truncate">{{ $passkey->name }}</flux:heading>
                        <flux:text size="sm">
                            {{ __('Added :date', ['date' => $passkey->created_at?->isoFormat('LL')]) }} · {{ $passkey->last_used_at ? __('Last used :time', ['time' => $passkey->last_used_at->diffForHumans()]) : __('Never used') }}
                        </flux:text>
                    </div>

                    <flux:button
                        wire:click="confirmRemoval({{ $passkey->id }})"
                        variant="subtle"
                        size="sm"
                        icon="trash"
                    >{{ __('Remove') }}</flux:button>
                </div>
            @endforeach
        </div>
    @endif

    <div x-show="supported" x-cloak>
        <flux:modal.trigger name="add-passkey">
            <flux:button variant="primary" icon="finger-print">{{ __('Add a passkey') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:text x-show="! supported" x-cloak>{{ __('This browser can\'t create passkeys.') }}</flux:text>

    <flux:modal name="add-passkey" class="min-w-[22rem]">
        <form x-on:submit.prevent="add" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add a passkey') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Enter your password, then follow your browser\'s prompt.') }}</flux:text>
            </div>

            <flux:input
                wire:model="name"
                :label="__('Name')"
                :badge="__('Optional')"
                :placeholder="__('MacBook, iPhone…')"
                maxlength="255"
            />

            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            <flux:error name="credential" />

            <div
                x-show="error"
                x-text="error"
                role="alert"
                class="text-sm font-medium text-red-500 dark:text-red-400"
                x-cloak
            ></div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" x-bind:disabled="busy">{{ __('Continue') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="remove-passkey" class="min-w-[22rem]">
        <form wire:submit="remove" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove this passkey?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('You won\'t be able to sign in with it any more. Enter your password to confirm.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="remove_password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Remove') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
