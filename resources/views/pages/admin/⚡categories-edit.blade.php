<?php

declare(strict_types=1);

use App\Actions\UpdateCategoryAction;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    public Category $category;

    /**
     * @var array<string, string>
     */
    public array $name = [];

    public string $locale;

    /**
     * @var array<string, mixed>
     */
    public array $activeLocales = [];

    public function mount(Category $category): void
    {
        $category->load('translations');

        $this->category = $category;
        $this->name = $category->translationsFor('name');
        $this->locale = app()->getLocale();
        $this->activeLocales = resolve('localization')->getActiveLocales();
    }

    #[On('change-locale')]
    public function changeLocale(): void
    {
        $codes = array_keys($this->activeLocales);
        $index = array_search($this->locale, $codes, true);

        $this->locale = $codes[($index + 1) % count($codes)] ?? $this->locale;
    }

    public function update(UpdateCategoryAction $action): void
    {
        $default = resolve('localization')->getDefaultLocale();

        $rules = ["name.$default" => ['required', 'string', 'max:255']];

        foreach (array_keys($this->activeLocales) as $locale) {
            $rules["name.$locale"] ??= ['nullable', 'string', 'max:255'];
        }

        $this->validate($rules, [], ["name.$default" => __('name')]);

        $action->handle($this->category, ['name' => $this->name]);

        $this->category->refresh()->load('translations');

        Flux::toast(__('Changes saved.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Edit').' '.$this->category->name)
            ->layout('layouts::admin');
    }
};
?>

<form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-stretch">
    <div class="md:col-span-3">
        <div class="max-w-5xl space-y-6 mb-10">
            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Category') }}</flux:legend>
                <flux:description>{{ __('The name shown wherever this category is used.') }}</flux:description>

                <div class="flex flex-col gap-6 mt-6">
                    <div class="md:w-1/2">
                        <x-forms.input-translated name="name" :$locale :multi-locale="count($activeLocales) > 1" label="{{ __('Name') }}" />
                    </div>
                </div>
            </flux:fieldset>
        </div>
    </div>
    <div class="mb-10 md:mb-0 md:col-span-2">
        <flux:card class="flex flex-col gap-6 md:sticky md:top-24">
            <div class="grid grid-cols-2 gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
                <flux:button wire:navigate href="{{ route('admin.categories-index') }}" icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
            </div>

            <flux:text size="sm">
                {{ __('Last edited') }} {{ $category->updated_at?->diffForHumans() ?? __('Never') }}
            </flux:text>
        </flux:card>
    </div>
</form>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.categories-index') }}" wire:navigate>
            {{ __('Categories') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ $category->name !== '' ? $category->name : __('Untitled') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ Str::limit($category->name !== '' ? $category->name : __('Untitled'), 22) }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.categories-index') }}" wire:navigate>{{ __('Categories') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
