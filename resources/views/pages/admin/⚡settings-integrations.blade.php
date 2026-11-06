<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\CardIntegration;
use App\Integrations\IntegrationRegistry;
use App\Livewire\Forms\CustomCodeForm;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public CustomCodeForm $customCodeForm;

    public function mount(): void
    {
        $this->customCodeForm->head_scripts = is_string(config('site.head_scripts')) ? config()->string('site.head_scripts') : '';
        $this->customCodeForm->body_scripts = is_string(config('site.body_scripts')) ? config()->string('site.body_scripts') : '';
    }

    /**
     * @return list<string>
     */
    public function integrations(): array
    {
        return array_map(fn (CardIntegration $integration): string => $integration->key(), IntegrationRegistry::cards());
    }

    public function updateCustomCode(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->customCodeForm->validate();

        $action->handle([
            'head_scripts' => mb_trim((string) ($validated['head_scripts'] ?? '')),
            'body_scripts' => mb_trim((string) ($validated['body_scripts'] ?? '')),
        ]);

        Flux::toast(__('Settings have been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Integrations'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <div class="space-y-10">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->integrations() as $integration)
                <livewire:is :component="'admin.integrations.'.$integration" :wire:key="$integration" />
            @endforeach
        </div>

        <flux:separator variant="subtle" />

        <form
            wire:submit="updateCustomCode"
            wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
            class="max-w-3xl space-y-6"
        >
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Custom code') }}</flux:heading>
                <flux:text>{{ __('Paste tracking pixels, analytics or other third-party snippets. Code is added as-is to every page on your public site, so only add code from sources you trust.') }}</flux:text>
                <div class="flex flex-wrap items-center gap-3">
                    <flux:modal.trigger name="site-head-scripts">
                        <flux:button
                            icon="code-bracket"
                            variant="filled"
                        >{{ $this->customCodeForm->head_scripts !== '' ? __('Edit head code') : __('Add head code') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="site-body-scripts">
                        <flux:button
                            icon="code-bracket"
                            variant="filled"
                        >{{ $this->customCodeForm->body_scripts !== '' ? __('Edit body code') : __('Add body code') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <flux:modal name="site-head-scripts" class="w-full md:max-w-2xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Custom head code') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Added inside the <head> tag on every page. Use for Facebook Pixel, BugHerd, verification tags and similar snippets.') }}</flux:text>
                    </div>
                    <flux:textarea
                        wire:model="customCodeForm.head_scripts"
                        rows="12"
                        class="font-mono text-sm"
                        placeholder="&lt;script&gt;…&lt;/script&gt;"
                    />
                    <div class="flex justify-end">
                        <flux:modal.close>
                            <flux:button variant="primary">{{ __('Done') }}</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>

            <flux:modal name="site-body-scripts" class="w-full md:max-w-2xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Custom body code') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Added just before the closing </body> tag on every page. Use for chat widgets and scripts that should load last.') }}</flux:text>
                    </div>
                    <flux:textarea
                        wire:model="customCodeForm.body_scripts"
                        rows="12"
                        class="font-mono text-sm"
                        placeholder="&lt;script&gt;…&lt;/script&gt;"
                    />
                    <div class="flex justify-end">
                        <flux:modal.close>
                            <flux:button variant="primary">{{ __('Done') }}</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" icon="check"> {{ __('Update') }} </flux:button>
            </div>
        </form>
    </div>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('App Integrations') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('App Integrations') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
