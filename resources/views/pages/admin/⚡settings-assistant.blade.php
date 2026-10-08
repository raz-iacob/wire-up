<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;

return new class extends Component
{
    public const int MAX_GUIDES = 20;

    /**
     * @var array<int, array{title: string, description: string, instructions: string}>
     */
    public array $guides = [];

    public function mount(): void
    {
        $this->guides = array_map(fn (array $guide): array => [
            'title' => $guide['title'],
            'description' => $guide['description'],
            'instructions' => $guide['instructions'],
        ], SettingsService::current()->assistantGuides());
    }

    public function addGuide(): void
    {
        if (count($this->guides) < self::MAX_GUIDES) {
            $this->guides[] = ['title' => '', 'description' => '', 'instructions' => ''];
        }
    }

    public function removeGuide(int $index): void
    {
        unset($this->guides[$index]);

        $this->guides = array_values($this->guides);
    }

    public function update(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'guides' => ['array', 'max:'.self::MAX_GUIDES],
            'guides.*.title' => ['required', 'string', 'max:60'],
            'guides.*.description' => ['required', 'string', 'max:300'],
            'guides.*.instructions' => ['required', 'string', 'max:20000'],
        ], attributes: [
            'guides.*.title' => __('name'),
            'guides.*.description' => __('when to use it'),
            'guides.*.instructions' => __('instructions'),
        ]);

        $names = [];

        foreach ($validated['guides'] ?? [] as $index => $guide) {
            $name = Str::slug((string) $guide['title']);

            if ($name === '' || in_array($name, $names, true) || in_array($name, $this->builtInNames(), true)) {
                $this->addError("guides.$index.title", __('Choose a different name; this one is already taken.'));

                return;
            }

            $names[] = $name;
        }

        $action->handle([
            'assistant_guides' => array_map(fn (array $guide): array => [
                'name' => Str::slug((string) $guide['title']),
                'title' => mb_trim((string) $guide['title']),
                'description' => mb_trim((string) $guide['description']),
                'instructions' => mb_trim((string) $guide['instructions']),
            ], array_values($validated['guides'] ?? [])),
        ]);

        Flux::toast(__('Assistant guides have been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('AI Assistant'))
            ->layout('layouts::admin');
    }

    /**
     * @return array<int, string>
     */
    private function builtInNames(): array
    {
        return array_map(basename(...), File::directories(resource_path('skills')));
    }
};
?>

<x-admin.settings-layout>
    <form
        wire:submit="update"
        wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid items-start gap-10 md:grid-cols-5"
    >
        <div class="space-y-8 md:col-span-3">
            <div>
                <flux:heading size="lg">{{ __('Guides') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Teach the assistant how you like things done. It reads a guide whenever a request matches it.') }}</flux:text>
            </div>

            @foreach ($guides as $index => $guide)
                <flux:card wire:key="guide-{{ $index }}" class="space-y-4">
                    <div class="flex items-start gap-3">
                        <flux:input
                            wire:model="guides.{{ $index }}.title"
                            :label="__('Name')"
                            :placeholder="__('e.g. House style')"
                            class="flex-1"
                        />
                        <flux:button
                            variant="subtle"
                            icon="trash"
                            class="mt-6"
                            wire:click="removeGuide({{ $index }})"
                            :aria-label="__('Remove guide')"
                        />
                    </div>
                    <flux:input
                        wire:model="guides.{{ $index }}.description"
                        :label="__('When to use it')"
                        :placeholder="__('e.g. Use when writing any text for the site.')"
                    />
                    <flux:textarea
                        wire:model="guides.{{ $index }}.instructions"
                        :label="__('Instructions')"
                        rows="8"
                        :placeholder="__('e.g. Write in a warm, plain voice. Use Canadian spelling. Keep headings under six words.')"
                    />
                </flux:card>
            @endforeach

            @if (count($guides) < $this::MAX_GUIDES)
                <flux:button icon="plus" wire:click="addGuide">{{ __('Add guide') }}</flux:button>
            @endif

            <div>
                <flux:button type="submit" variant="primary" icon="check"> {{ __('Update') }} </flux:button>
            </div>
        </div>
    </form>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('AI Assistant') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('AI Assistant') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
