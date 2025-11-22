<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
use App\Enums\PageStatus;
use App\Models\Page;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
    public Page $page;

    /**
     * @var array<string, string>
     */
    public array $title = [];

    /**
     * @var array<string, string>
     */
    public array $description = [];

    /**
     * @var array<string, string>
     */
    public array $slugs = [];

    public PageStatus $status;

    public ?CarbonImmutable $published_at = null;

    #[Url(except: 'en')]
    public string $locale;

    /**
     * @var array<string, mixed>
     */
    public array $activeLocales = [];

    public function mount(Page $page): void
    {
        $page->load('translations');
        $this->page = $page;
        $this->status = $page->computed_status;
        $this->published_at = $page->published_at;
        $this->title = $page->translationsFor('title');
        $this->description = $page->translationsFor('description');
        $this->slugs = $page->getSlugsArray();
        $this->locale = app()->getLocale();
        $this->activeLocales = app('localization')->getActiveLocales();
    }

    public function update(UpdatePageAction $action): void
    {
        /** @var array<string, array<int, string>> */
        $rules = [
            'status' => ['required', Rule::enum(PageStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];

        foreach (array_keys($this->activeLocales) as $locale) {
            $rules["title.$locale"] = ['required', 'string', 'min:3'];
            $rules["description.$locale"] = ['nullable', 'string', 'max:160'];
            $rules["slugs.$locale"] = [
                'required', 'string', 'min:3',
                Rule::unique('slugs', 'slug')->where('locale', $locale)
                    ->where(function (Builder $query): void {
                        $query->whereNot(function (Builder $q): void {
                            $q->where('sluggable_id', $this->page->id)
                                ->where('sluggable_type', 'page');
                        });
                    }),
            ];
        }

        $validated = $this->validate($rules);

        $action->handle($this->page, $validated);

        Flux::toast(__('Page content has been updated.'));
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Edit').' '.$this->page->title)
            ->layout('layouts::admin');
    }
};
?>

<form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-3 gap-6 items-stretch">
    <div class="md:col-span-2">
        <div class="gap-4 mb-6 md:mb-0">
            <flux:heading size="xl" class="cursor-pointer hover:underline">
                {{ __('Edit') }} {{ $page->title }}
            </flux:heading>
            <flux:subheading size="sm">
                {{ __('Created on') }} {{ $page->created_at?->format('M d, Y H:i') }}
            </flux:subheading>
        </div>
        <div class="max-w-3xl mt-8 space-y-6 mb-10">
            
            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Content') }}</flux:legend>
                <flux:description>{{ __('Build and customize this page using flexible content blocks.') }}</flux:description>

                
            </flux:fieldset>
            
            <flux:separator />

            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('Visibility') }}</flux:legend>
                <flux:description>{{ __('Control when and how this page becomes visible to visitors.') }}</flux:description>

                <div class="grid md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <flux:select variant="listbox" placeholder="Choose status" label="{{ __('Status') }}" wire:model="status">
                            @foreach(PageStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}">
                                    {{ $status->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div x-cloak x-show="$wire.status === '{{ PageStatus::SCHEDULED->value }}'">
                        <flux:date-picker wire:model="published_at" label="{{ __('Publish on') }}" />
                    </div>
                </div>
            </flux:fieldset>

            <flux:separator />

            <flux:fieldset class="pb-6">
                <flux:legend>{{ __('SEO Settings') }}</flux:legend>
                <flux:description>{{ __('Manage how this page appears in search results.') }}</flux:description>

                <div class="flex flex-col gap-6 mt-6">
                    <x-forms.input-translated name="title" :$locale :multiple="count($activeLocales) > 1" label="{{ __('Title') }}" />
                    <x-forms.url-translated name="slugs" :$locale :multiple="count($activeLocales) > 1" label="{{ __('Web Address') }}" />
                    <x-forms.textarea-translated name="description" :$locale :multiple="count($activeLocales) > 1" label="{{ __('Description') }}" />
                </div>
            </flux:fieldset>
        </div>
    </div>
    <div class="mb-10 md:mb-0">
        <div class="flex flex-col-reverse md:flex-col items-center md:items-end md:justify-end gap-4 md:sticky md:top-8 pt-2">
            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Save') }}
                </flux:button>
                <flux:button wire:navigate href="{{ route('admin.pages-index') }}" icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
            </div>
        </div>
    </div>
</form>