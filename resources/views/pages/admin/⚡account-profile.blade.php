<?php

declare(strict_types=1);

use App\Actions\DeleteUserAction;
use App\Actions\UpdateUserAction;
use App\Models\User;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

return new class extends Component
{
    use WithFileUploads;

    public User $user;

    public ?TemporaryUploadedFile $photo = null;

    public string $name = '';

    public string $email = '';

    public bool $emailVerified = false;

    public string $password = '';

    public bool $photoRemoved = false;

    public function mount(#[CurrentUser] User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->emailVerified = $user->hasVerifiedEmail();
    }

    public function update(UpdateUserAction $action): void
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

        if ($this->photo || $this->photoRemoved) {
            $this->deletePhoto();

            $credentials['photo'] = $this->photo
                ? $this->uploadPhoto()
                : null;

            $this->reset(['photo', 'photoRemoved']);
        }

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

    public function updatedPhoto(): void
    {
        try {
            $this->validate([
                'photo' => ['image', 'max:10240'],
            ]);
        } catch (ValidationException $e) {
            $this->removePhoto();
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function removePhoto(): void
    {
        if ($this->photo) {
            $this->photo->delete();
            $this->photo = null;
        }

        $this->photoRemoved = true;
    }

    public function delete(DeleteUserAction $action): void
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

    public function render(): View
    {
        return $this->view()
            ->title(__('Account Profile'))
            ->layout('layouts::admin');
    }

    private function uploadPhoto(): string
    {
        $extension = $this->photo->getClientOriginalExtension();
        $originalName = pathinfo($this->photo->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = "users/{$this->user->id}_{$originalName}.{$extension}";

        return $this->photo->storeAs('', $filename, 'public');
    }

    private function deletePhoto(): void
    {
        if ($this->user->photo) {
            Storage::disk('public')->delete($this->user->photo);
        }
    }
};
?>

<x-account-layout :heading="__('Edit profile')" :subheading="__('Update your name and email address')">
    <form wire:submit="update" class="space-y-8">
        <div class="flex flex-row items-start justify-between gap-8">
            <div class="grow">
                <flux:file-upload wire:model="photo" :label="__('Profile photo')" accept="image/png, image/jpeg">
                    <flux:text class="mb-3">{{ __('Recommanded 300 x 300') }}</flux:text>
                    <flux:button size="sm" variant="filled" x-on:click.prevent="$el.closest('[data-flux-file-upload]').querySelector('input[type=file]').click()">
                        {{ ($user->photo_url || $photo) ? __('Change') : __('Upload') }}
                    </flux:button>
                    @if(($user->photo_url || $photo) && ! $photoRemoved)
                        <flux:button size="sm" variant="filled" class="ms-3" wire:click="removePhoto">
                            {{ __('Remove') }}
                        </flux:button>
                    @endif
                </flux:file-upload>
            </div>
            <div class="relative flex items-center justify-center size-20 rounded-lg border border-zinc-200 dark:border-white/10 bg-zinc-100 dark:bg-white/10 overflow-hidden">
                @if ($photo)
                    <img src="{{ $photo?->temporaryUrl() }}" class="size-full object-cover" />
                @elseif ($user->photo_url && ! $photoRemoved)
                    <img src="{{ $user->photo_url }}" alt="{{ $user->name }}" class="size-full object-cover" />
                @else
                    <flux:icon name="user" variant="solid" class="text-zinc-500 dark:text-zinc-400" />
                @endif
            </div>
        </div>

        <flux:input wire:model="name" :label="__('Full name')" type="text" required autofocus autocomplete="name" />

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

    <section class="mt-12 md:mt-20 space-y-6">
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