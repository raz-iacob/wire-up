<?php

declare(strict_types=1);

use Flux\Flux;
use App\Models\User;
use Livewire\Component;
use App\Actions\UpdateUser;
use Illuminate\Validation\Rule;
use App\Actions\UpdateUserPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password as PasswordRule;

return new class extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public ?string $photo = null;

    public bool $active = false;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->active = $user->active;
        $this->photo = $user->photo_url;
    }

    public function update(UpdateUser $action, UpdateUserPassword $updatePassword): void
    {
        $credentials = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user->id),
            ],

            'active' => ['boolean'],
        ]);

        if (is_null($this->photo) && $this->user->photo) {
            Storage::disk('public')->delete($this->user->photo);
            $credentials['photo'] = null;
        }

        $action->handle($this->user, $credentials);

        if ($this->password) {
            $this->validate([
                'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
            ]);

            $updatePassword->handle($this->user, $this->password);
        }

        Flux::toast(__('User details have been updated.'));
    }

    public function removePhoto(): void
    {
        $this->photo = null;
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->user->name)
            ->layout('layouts::admin');
    }
};
?>

<form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-3 gap-6 items-stretch">
    <div class="md:col-span-2">
        <div class="gap-4 mb-6 md:mb-0">
            <flux:heading size="xl" class="cursor-pointer hover:underline">
                {{ __('Edit') }} {{ $user->name }}
            </flux:heading>
            <flux:subheading size="sm">
                {{ __('Created on') }} {{ $user->created_at?->format('M d, Y H:i') }} | 
                {{ __('Last login') }} {{ $user->last_seen_at?->format('M d, Y H:i') ?? __('Never') }}
            </flux:subheading>
        </div>
        <div class="max-w-3xl mt-8 space-y-6 mb-10">
            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Account') }}</flux:legend>
                <flux:description>{{ __('Update the user\'s name and email associated with this account.') }}</flux:description>

                <div class="grid md:grid-cols-2 gap-6 mt-6">
                    @if ($photo)
                    <div class="flex items-center gap-3 md:col-span-2">
                        <flux:avatar size="xl" :src="$photo" :name="$user->name" />
                        <div class="flex flex-col gap-3">
                            <flux:label>{{ __('Profile photo') }}</flux:label>
                            <flux:button type="button" size="sm" variant="filled" wire:click="removePhoto">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </div>
                    @endif
                    <div>
                        <flux:input wire:model="name" label="{{ __('Full name') }}" />
                    </div>
                    <div>
                        <flux:input wire:model="email" type="email" label="{{ __('Email') }}" />
                    </div>
                    <div>
                        <flux:switch wire:model="active" label="{{ __('Allow login') }}" align="left" />
                    </div>
                </div>
            </flux:fieldset>

            <flux:separator />

            <flux:fieldset>
                <flux:legend>{{ __('Change password') }}</flux:legend>
                <flux:description>{{ __('Ensure the account is using a long, random password to stay secure.') }}</flux:description>

                <div class="grid md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <flux:input type="password" wire:model="password" viewable :label="__('Password')" />
                    </div>
                    <div>
                        <flux:input type="password" wire:model="password_confirmation" viewable :label="__('Confirm Password')" />
                    </div>
                </div>
            </flux:fieldset>
        </div>
    </div>
    <div class="mb-10 md:mb-0">
        <div class="flex flex-col-reverse md:flex-col items-center md:items-end md:justify-end gap-4 md:sticky top-0 pt-2">
            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Save') }}
                </flux:button>
                <flux:button wire:navigate href="{{ route('admin.users-index') }}" icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
            </div>
        </div>
    </div>
</form>