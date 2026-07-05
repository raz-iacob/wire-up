<?php

declare(strict_types=1);

use App\Actions\UpdateUserAction;
use App\Actions\UpdateUserPasswordAction;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public ?int $roleId = null;

    public ?string $photo = null;

    public bool $active = false;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        $this->authorize('users.edit');

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->roleId = $user->role_id;
        $this->active = $user->active;
        $this->photo = $user->photo_url;
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()->orderBy('id')->get();
    }

    public function update(#[CurrentUser] User $editor, UpdateUserAction $action, UpdateUserPasswordAction $updatePassword): void
    {
        $this->authorize('users.edit');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user->id),
            ],

            'roleId' => ['required', Rule::exists('roles', 'id')],

            'active' => ['boolean'],
        ]);

        $targetRole = Role::query()->findOrFail($validated['roleId']);

        if ($this->user->is($editor) && $targetRole->id !== $this->user->role_id) {
            $this->addError('roleId', __('You cannot change your own role.'));

            return;
        }

        if ($this->user->role?->bypass && ! $targetRole->bypass && $this->isLastSuperAdmin()) {
            $this->addError('roleId', __('The site must keep at least one full-access role.'));

            return;
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['roleId'],
            'active' => $validated['active'] ?? $this->active,
        ];

        if (is_null($this->photo) && $this->user->photo) {
            Storage::disk('public')->delete($this->user->photo);
            $attributes['photo'] = null;
        }

        $action->handle($this->user, $attributes);

        if ($this->password !== '' && $this->password !== '0') {
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

    private function isLastSuperAdmin(): bool
    {
        return User::query()
            ->whereKeyNot($this->user->id)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('bypass', true))
            ->doesntExist();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Edit').' '.$this->user->name)
            ->layout('layouts::admin');
    }
};
?>

<form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-stretch">
    <div class="md:col-span-3">
        <div class="max-w-5xl space-y-6 mb-10">
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
    <div class="mb-10 md:mb-0 md:col-span-2">
        <flux:card class="flex flex-col gap-6 md:sticky md:top-24">
            <flux:accordion>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <div class="flex items-center justify-between">
                            {{ __('Allow login') }}
                            <flux:text class="{{ $active ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                {{ $active ? __('Yes') : __('No') }}
                            </flux:text>
                        </div>
                    </flux:accordion.heading>

                    <flux:accordion.content class="mt-3">
                        <flux:switch wire:model.live="active" label="{{ __('Allow this user to sign in') }}" align="left" />
                    </flux:accordion.content>
                </flux:accordion.item>

                <flux:accordion.item>
                    <flux:accordion.heading>
                        <div class="flex items-center justify-between">
                            {{ __('Role') }}
                            <flux:text>{{ $this->roles->firstWhere('id', $roleId)?->name }}</flux:text>
                        </div>
                    </flux:accordion.heading>

                    <flux:accordion.content class="mt-3">
                        <flux:select
                            variant="listbox"
                            wire:model.live="roleId"
                            :disabled="$user->id === auth()->id()"
                            :description="$user->id === auth()->id() ? __('You cannot change your own role.') : null"
                        >
                            @foreach ($this->roles as $roleOption)
                                <flux:select.option :value="$roleOption->id">{{ $roleOption->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>

            <div class="grid grid-cols-2 gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
                <flux:button wire:navigate href="{{ route('admin.users-index') }}" icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
            </div>

            <flux:text size="sm">
                {{ __('Last login') }} {{ $user->last_seen_at?->diffForHumans() ?? __('Never') }}
            </flux:text>
        </flux:card>
    </div>
</form>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.users-index') }}" wire:navigate>
            {{ __('Users') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ $user->name }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ Str::limit($user->name, 22) }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.users-index') }}" wire:navigate>{{ __('Users') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection