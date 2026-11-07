<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Role;
use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public bool $remote_mcp_enabled = false;

    public function mount(): void
    {
        $this->remote_mcp_enabled = SettingsService::current()->remoteMcpEnabled();
    }

    public function update(UpdateSettingsAction $action): void
    {
        $this->authorize(Role::DEVELOPER);

        $action->handle(['remote_mcp_enabled' => $this->remote_mcp_enabled]);

        Flux::toast($this->remote_mcp_enabled ? __('Remote MCP is on.') : __('Remote MCP is off.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Remote MCP'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <form
        wire:submit="update"
        wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="max-w-xl space-y-6"
    >
        <div>
            <flux:heading size="lg">{{ __('Remote MCP') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Let AI tools such as Claude Code and Cursor work on this site. People sign in with a token from Account → Security, and their role still applies.') }}
            </flux:text>
        </div>

        <flux:switch wire:model="remote_mcp_enabled" :label="__('Allow remote MCP')" align="left" />

        <flux:input :value="url('/mcp')" :label="__('Endpoint')" readonly copyable />

        <div>
            <flux:button type="submit" variant="primary" icon="check">{{ __('Update') }}</flux:button>
        </div>
    </form>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Remote MCP') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Remote MCP') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
