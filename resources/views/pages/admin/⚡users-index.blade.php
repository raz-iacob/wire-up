<?php

declare(strict_types=1);

use Flux\Flux;
use App\Models\User;
use Livewire\Component;
use App\Traits\WithSorting;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Actions\UpdateUserAction;
use Livewire\Attributes\Computed;
use App\Actions\InviteAdminAction;
use Illuminate\Contracts\View\View;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

return new class extends Component
{
    use WithPagination, WithSorting;

    #[Url(as: 'q', except: '')] 
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $name = '';

    public string $email = '';

    public int $perPage = 20;

    public function create(#[CurrentUser] User $inviter, InviteAdminAction $action): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = $action->handle($inviter, $this->name, $this->email);

        Flux::modal('add-new')->close();
        Flux::toast(__('Invitation email sent to '.$user->email));
    }

    public function toggleStatus(User $user, UpdateUserAction $action): void
    {
        $action->handle($user, [
            'active' => ! $user->active,
        ]);
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->status, fn (Builder $query, string $status): Builder => $query->where('active', $status === 'active'))
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereAny(['name', 'email'], 'like', "%{$search}%"))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Users'))
            ->layout('layouts::admin');
    }
};
?>
<div>
    <div class="space-y-6 md:space-y-8">
        <div class="flex items-center gap-3">
            <flux:modal.trigger name="add-new">
                <flux:button variant="primary" class="shrink-0" size="sm" icon="plus" iconVariant="outline">{{ __('Add') }}</flux:button>
            </flux:modal.trigger>

            <flux:dropdown position="bottom" align="start">
                <flux:button class="shrink-0" size="sm" icon="funnel" iconVariant="outline">{{ __('Filter') }}</flux:button>

                <flux:menu>
                    <flux:menu.submenu heading="{{ __('Status') }}">
                        <flux:menu.radio.group wire:model.live="status" heading="{{ __('Status') }}">
                            <flux:menu.radio value="" checked>{{ __('All') }}</flux:menu.radio>
                            <flux:menu.radio value="active">{{ __('Active') }}</flux:menu.radio>
                            <flux:menu.radio value="disabled">{{ __('Disabled') }}</flux:menu.radio>
                        </flux:menu.radio.group>
                    </flux:menu.submenu>
                </flux:menu>
            </flux:dropdown>

            <div class="w-full md:w-52 sm:shrink-0">
                <flux:input icon="magnifying-glass" wire:model.live="search" size="sm" placeholder="{{ __('Search...') }}" clearable />
            </div>
        </div>

        <flux:table class="md:table-fixed md:w-full max-h-[calc(100dvh-12rem)]" :paginate="$this->users" container:class="max-h-[calc(100dvh-12rem)]">
            <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column class="w-1/6">{{ __('Role') }}</flux:table.column>
                <flux:table.column class="w-1/6">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-1/6" sortable :sorted="$sortBy === 'last_seen_at'" :direction="$sortDirection" wire:click="sort('last_seen_at')">{{ __('Last login') }}</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->users as $row)
                <flux:table.row wire:key="{{ $row->id }}">
                    <flux:table.cell>
                        <a href="{{ route('admin.users-edit', $row->id) }}" class="flex items-center gap-3">
                            <flux:avatar :src="$row->photo_url" :name="$row->name" />
                            <div>
                                <flux:text variant="strong" class="hover:underline">{{ $row->name }}</flux:text>
                                <flux:text>{{ $row->email }}</flux:text>
                            </div>
                        </a>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $row->admin ? __('Admin') : __('User') }}</flux:table.cell>

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
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                            <flux:menu>
                                <flux:menu.item icon="pencil" href="{{ route('admin.users-edit', $row->id) }}" >
                                    {{ __('Edit') }}
                                </flux:menu.item>

                                <flux:menu.separator />
                                @if($row->active)
                                <flux:menu.item icon="no-symbol" variant="danger" wire:click="toggleStatus({{ $row->id }})">
                                    {{ __('Disable') }}
                                </flux:menu.item>
                                @else
                                <flux:menu.item icon="check-circle" icon:variant="outline" class="hover:text-green-500!" wire:click="toggleStatus({{ $row->id }})">
                                    {{ __('Enable') }}
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
            <flux:input wire:model="name" label="{{ __('Name') }}" badge="Required" autofocus />
            <flux:input wire:model="email" label="{{ __('Email') }}" badge="Required" />
            <div class="flex mt-6">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Send Invite') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Users') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection