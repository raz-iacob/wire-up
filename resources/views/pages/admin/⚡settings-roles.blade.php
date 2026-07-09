<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $roles = [];

    public int $seq = 0;

    public ?string $removeKey = null;

    public int $removeUserCount = 0;

    public function mount(): void
    {
        $this->roles = Role::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role): array => $this->hydrateRole($role))
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, group: string, actions: array<int, string>}>
     */
    #[Computed]
    public function resources(): array
    {
        return PermissionRegistry::resources();
    }

    public function actionLabel(string $action): string
    {
        return PermissionAction::from($action)->label();
    }

    public function addRole(): void
    {
        $this->authorize('roles.create');

        $grants = [];

        foreach (array_keys(PermissionRegistry::resources()) as $index) {
            $grants[$index] = [];
        }

        $this->roles[] = [
            '_key' => (string) $this->seq++,
            'id' => null,
            'key' => '',
            'name' => '',
            'bypass' => false,
            'is_protected' => false,
            'grants' => $grants,
            'open' => true,
        ];
    }

    public function confirmRemove(string $key): void
    {
        $this->authorize('roles.delete');

        $role = collect($this->roles)->firstWhere('_key', $key);

        if ($role === null || $role['is_protected']) {
            return;
        }

        $this->removeKey = $key;
        $this->removeUserCount = $role['id'] ? User::query()->where('role_id', $role['id'])->count() : 0;

        Flux::modal('confirm-remove-role')->show();
    }

    public function removeRole(): void
    {
        if ($this->removeUserCount > 0 || $this->removeKey === null) {
            return;
        }

        $this->roles = array_values(array_filter(
            $this->roles,
            fn (array $role): bool => $role['_key'] !== $this->removeKey,
        ));

        $this->removeKey = null;
        Flux::modal('confirm-remove-role')->close();
    }

    public function save(): void
    {
        $this->authorize('roles.edit');

        foreach ($this->roles as $index => $role) {
            if (mb_trim((string) $role['name']) === '') {
                $this->addError("roles.{$index}.name", __('Give this role a name.'));

                return;
            }
        }

        $keptIds = array_values(array_filter(array_column($this->roles, 'id')));

        $removable = Role::query()
            ->whereNotIn('id', $keptIds)
            ->where('is_protected', false)
            ->get();

        foreach ($removable as $role) {
            if ($role->isInUse()) {
                $this->addError('roles', __(':name still has members — reassign them first.', ['name' => $role->name]));

                return;
            }

            $role->delete();
        }

        foreach ($this->roles as $role) {
            $this->persist($role);
        }

        $this->mount();

        Flux::toast(__('Roles have been updated.'));
        $this->dispatch('roles-updated');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Roles'))
            ->layout('layouts::admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrateRole(Role $role): array
    {
        $grants = [];

        foreach (PermissionRegistry::resources() as $index => $resource) {
            $grants[$index] = array_values(array_filter(
                $resource['actions'],
                fn (string $action): bool => in_array($resource['key'].'.'.$action, $role->abilities, true),
            ));
        }

        return [
            '_key' => (string) $this->seq++,
            'id' => $role->id,
            'key' => $role->key,
            'name' => $role->name,
            'bypass' => $role->bypass,
            'is_protected' => $role->is_protected,
            'grants' => $grants,
            'open' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $role
     */
    private function persist(array $role): void
    {
        $name = mb_trim((string) $role['name']);

        if ($role['id']) {
            $model = Role::query()->findOrFail($role['id']);
            $model->name = $name;

            if (! $model->bypass && ! $model->is_protected) {
                $model->abilities = $this->serialize($role['grants']);
            }

            $model->save();

            return;
        }

        Role::query()->create([
            'key' => $this->uniqueKey($name),
            'name' => $name,
            'abilities' => $this->serialize($role['grants']),
            'bypass' => false,
            'is_protected' => false,
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $grants
     * @return array<int, string>
     */
    private function serialize(array $grants): array
    {
        $resources = PermissionRegistry::resources();
        $abilities = [];

        foreach ($grants as $index => $actions) {
            if (! isset($resources[$index])) {
                continue;
            }

            foreach ($actions as $action) {
                if (in_array($action, $resources[$index]['actions'], true)) {
                    $abilities[] = $resources[$index]['key'].'.'.$action;
                }
            }
        }

        return $abilities;
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $key = $base;
        $suffix = 2;

        while (Role::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$suffix++;
        }

        return $key;
    }
};
?>

<x-admin.settings-layout>
    <div class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-4 md:col-span-3">
            <div>
                <flux:label>{{ __('Roles') }}</flux:label>
                <flux:text variant="subtle" class="mt-1">{{ __('Control what each role can do across the admin.') }}</flux:text>
            </div>

            <form wire:submit="save" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="space-y-10">
                @error('roles')
                    <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
                @enderror

                <div class="space-y-4">
                    @foreach ($roles as $i => $role)
                        <div
                            wire:key="role-{{ $role['_key'] }}"
                            class="overflow-hidden rounded-lg border border-zinc-200 dark:border-white/10"
                        >
                            <div x-data="{ open: @js((bool) $role['open']) }">
                                <div class="flex items-center gap-2 bg-zinc-50 dark:bg-white/5 py-1.5 pl-3 pr-1.5">
                                    <button type="button" x-on:click="open = ! open" class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                        <flux:heading class="truncate text-sm!">
                                            {{ $role['name'] !== '' ? $role['name'] : __('Untitled role') }}
                                        </flux:heading>
                                        @if ($role['bypass'])
                                            <flux:badge color="green" size="sm">{{ __('Full access') }}</flux:badge>
                                        @elseif ($role['is_protected'])
                                            <flux:badge color="zinc" size="sm">{{ __('System') }}</flux:badge>
                                        @endif
                                    </button>

                                    <flux:button size="sm" variant="subtle" square x-on:click="open = ! open" :tooltip="__('Toggle')">
                                        <flux:icon name="chevron-down" variant="micro" x-show="! open" />
                                        <flux:icon name="chevron-up" variant="micro" x-show="open" x-cloak />
                                    </flux:button>

                                    @unless ($role['is_protected'])
                                        <flux:button size="sm" variant="subtle" square icon="x-mark" :tooltip="__('Remove')" wire:click="confirmRemove('{{ $role['_key'] }}')" />
                                    @endunless
                                </div>

                                <div class="space-y-6 p-4" x-show="open" x-cloak>
                                    <flux:field>
                                        <flux:label>{{ __('Name') }}</flux:label>
                                        <flux:input wire:model="roles.{{ $i }}.name" :readonly="$role['is_protected']" class="max-w-xs" />
                                        <flux:error name="roles.{{ $i }}.name" />
                                    </flux:field>

                                    @if ($role['bypass'])
                                        <flux:text>{{ __('This role has unrestricted access to everything.') }}</flux:text>
                                    @elseif ($role['is_protected'])
                                        <flux:text>{{ __('This role has no admin access and is assigned to new sign-ups.') }}</flux:text>
                                    @else
                                        <div class="space-y-4">
                                            @foreach ($this->resources as $resourceIndex => $resource)
                                                <flux:pillbox
                                                    wire:model="roles.{{ $i }}.grants.{{ $resourceIndex }}"
                                                    multiple
                                                    :label="$resource['label']"
                                                    :placeholder="__('No access')"
                                                    wire:key="grant-{{ $role['_key'] }}-{{ $resourceIndex }}"
                                                >
                                                    @foreach ($resource['actions'] as $action)
                                                        <flux:pillbox.option :value="$action" :label="$this->actionLabel($action)" />
                                                    @endforeach
                                                </flux:pillbox>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <flux:button type="button" size="sm" icon="plus" wire:click="addRole">{{ __('Add') }}</flux:button>
                </div>

                <div>
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Update') }}</flux:button>
                </div>
            </form>
        </div>
    </div>

    <flux:modal name="confirm-remove-role" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete role') }}</flux:heading>
            @if ($removeUserCount > 0)
                <flux:text>{{ __('This role is assigned to :count member(s). Reassign them before deleting.', ['count' => $removeUserCount]) }}</flux:text>
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button>{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            @else
                <flux:text>{{ __('This role will be removed when you save.') }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" icon="trash" wire:click="removeRole">{{ __('Remove') }}</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Roles') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
