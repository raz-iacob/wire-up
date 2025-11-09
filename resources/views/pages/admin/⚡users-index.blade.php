<?php

declare(strict_types=1);

use Flux\Flux;
use App\Models\User;
use Livewire\Component;
use App\Actions\UpdateUser;
use App\Traits\WithSorting;
use App\Actions\InviteAdmin;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

return new class extends Component
{
    use WithPagination, WithSorting;

    public string $search = '';

    public string $status = '';

    public string $name = '';

    public string $email = '';

    public int $perPage = 20;

    public function create(#[CurrentUser] User $inviter, InviteAdmin $action): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = $action->handle($inviter, $this->name, $this->email);

        Flux::modal('add-new')->close();
        Flux::toast(__('Invitation email sent to '.$user->email));
    }

    public function toggleStatus(User $user, UpdateUser $action): void
    {
        $action->handle($user, [
            'active' => ! $user->active,
        ]);
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $paginator = User::query()
            ->when($this->status, fn (Builder $query, string $status): Builder => $query->where('active', $status === 'active'))
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereAny(['name', 'email'], 'like', "%{$search}%"))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);

        return $paginator;
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Users'))
            ->layout('layouts::admin');
    }
};
?>

<div class="space-y-6 md:space-y-8">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="hidden md:block">
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage your users and their permissions') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-3 mt-4 md:mt-0">
            <div class="w-full md:w-52 sm:shrink-0">
                <flux:select wire:model.live="status">
                    <flux:select.option value="">{{ __('All Users') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="disabled">{{ __('Disabled') }}</flux:select.option>
                </flux:select>
            </div>
            <div class="w-full md:w-52 sm:shrink-0">
                <flux:input icon="magnifying-glass" wire:model.live="search" placeholder="{{ __('Search...') }}" clearable />
            </div>
            <flux:modal.trigger name="add-new">
                <flux:tooltip content="{{ __('Add User') }}">
                    <flux:button square variant="primary" class="shrink-0"><flux:icon.plus variant="solid" /></flux:button>
                </flux:tooltip>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:table class="md:table-fixed md:w-full max-h-[calc(100dvh-12rem)]" :paginate="$this->users" container:class="max-h-[calc(100dvh-12rem)]">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">Email</flux:table.column>
            <flux:table.column class="w-1/6">Role</flux:table.column>
            <flux:table.column class="w-1/6">Status</flux:table.column>
            <flux:table.column class="w-1/6" sortable :sorted="$sortBy === 'last_seen_at'" :direction="$sortDirection" wire:click="sort('last_seen_at')">Last login</flux:table.column>
            <flux:table.column class="w-10"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $row)
            <flux:table.row wire:key="{{ $row->id }}">
                <flux:table.cell>
                    <a href="{{ route('admin.users-edit', $row->id) }}" class="hover:underline">{{ $row->name }}</a>
                </flux:table.cell>

                <flux:table.cell class="whitespace-nowrap">{{ $row->email }}</flux:table.cell>

                <flux:table.cell class="whitespace-nowrap">-</flux:table.cell>

                <flux:table.cell>
                    <flux:badge color="{{ $row->active ? 'green' : 'zinc' }}" size="sm">
                        {{ $row->active ? __('Active') : __('Disabled') }}
                    </flux:badge>
                </flux:table.cell>

                <flux:table.cell>
                    {{ $row->last_seen_at?->format('M d, Y H:i') ?? __('Never') }}
                </flux:table.cell>

                <flux:table.cell>
                    <flux:dropdown class="flex justify-end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom">
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item icon="pencil" href="{{ route('admin.users-edit', $row->id) }}" >
                                Edit
                            </flux:menu.item>

                            <flux:menu.separator />
                            @if($row->active)
                            <flux:menu.item icon="no-symbol" variant="danger" wire:click="toggleStatus({{ $row->id }})">
                                Disable
                            </flux:menu.item>
                            @else
                            <flux:menu.item icon="check-circle" icon:variant="outline" class="hover:text-green-500!" wire:click="toggleStatus({{ $row->id }})">
                                Enable
                            </flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>

<flux:modal name="add-new" class="md:w-96">
    <flux:heading size="lg" class="mb-6">{{ __('Add a new user') }}</flux:heading>
    <form wire:submit="create" class="space-y-6">
        <flux:input wire:model="name" label="{{ __('Name') }}" badge="Required" />
        <flux:input wire:model="email" label="{{ __('Email') }}" badge="Required" />
        <div class="flex mt-6">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Send Invite') }}</flux:button>
        </div>
    </form>
</flux:modal>