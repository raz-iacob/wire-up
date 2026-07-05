<?php

declare(strict_types=1);

use App\Actions\DeleteSubmissionAction;
use App\Actions\MarkSubmissionReadAction;
use App\Models\Submission;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public Submission $submission;

    public function mount(Submission $submission, MarkSubmissionReadAction $action): void
    {
        $action->handle($submission);

        $this->submission = $submission;
    }

    public function delete(DeleteSubmissionAction $action): void
    {
        $this->authorize('inbox.delete');

        $action->handle($this->submission);

        Flux::toast(__('Message deleted.'), variant: 'success');

        $this->redirectRoute('admin.inbox-index', navigate: true);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    #[Computed]
    public function details(): array
    {
        $fields = [
            __('Email') => $this->submission->email,
            __('Phone') => $this->submission->phone,
            __('Subject') => $this->submission->subject,
        ];

        $rows = [];

        foreach ($fields as $label => $value) {
            if (is_string($value) && $value !== '') {
                $rows[] = ['label' => $label, 'value' => $value];
            }
        }

        foreach (is_array($this->submission->metadata) ? $this->submission->metadata : [] as $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = is_string($field['label'] ?? null) ? $field['label'] : '';
            $value = $field['value'] ?? null;
            if ($label === '') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            if ($value === '') {
                continue;
            }

            $rows[] = ['label' => $label, 'value' => is_bool($value) ? ($value ? __('Yes') : __('No')) : (string) $value];
        }

        return $rows;
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->submission->name ?: __('Message'))
            ->layout('layouts::admin');
    }
};
?>

<div class="grid md:grid-cols-5 gap-10 items-stretch">
    <div class="md:col-span-3">
        <div class="max-w-5xl space-y-6 mb-10">
            <flux:fieldset class="pb-6">
                <div class="flex items-center justify-between gap-3">
                    <flux:legend class="mb-0!">{{ $submission->name ?: __('Unknown sender') }}</flux:legend>
                    @if ($submission->form_name)
                        <flux:badge size="sm" color="zinc">{{ $submission->form_name }}</flux:badge>
                    @endif
                </div>
                <flux:description>{{ __('Received :date', ['date' => $submission->created_at?->format('M d, Y H:i')]) }}</flux:description>

                @if ($this->details !== [])
                    <dl class="mt-6 grid gap-x-6 gap-y-4 sm:grid-cols-[8rem_1fr]">
                        @foreach ($this->details as $detail)
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $detail['label'] }}</dt>
                            <dd class="break-words">{{ $detail['value'] }}</dd>
                        @endforeach
                    </dl>
                @endif
            </flux:fieldset>

            @if ($submission->message)
                <flux:separator />

                <flux:fieldset>
                    <flux:legend>{{ __('Message') }}</flux:legend>
                    <flux:text class="mt-4 whitespace-pre-line leading-relaxed">{{ $submission->message }}</flux:text>
                </flux:fieldset>
            @endif

            <flux:text size="sm" variant="subtle">
                {{ __('From IP :ip', ['ip' => $submission->ip ?: __('unknown')]) }}
                @if ($submission->locale && count(resolve('localization')->getActiveLocales()) > 1)
                    · {{ mb_strtoupper($submission->locale) }}
                @endif
                @if ($submission->countryName())
                    · {{ $submission->countryName() }}
                @endif
                @if ($submission->page)
                    · <a class="underline" href="{{ route('admin.pages-edit', $submission->page->id) }}" wire:navigate>{{ $submission->page->title }}</a>
                @endif
            </flux:text>
        </div>
    </div>

    <div class="mb-10 md:mb-0 md:col-span-2">
        <flux:card class="flex flex-col gap-6 md:sticky md:top-24">
            @if ($submission->email)
                <flux:button :href="'mailto:'.$submission->email" variant="primary" icon="arrow-uturn-left" class="w-full">
                    {{ __('Reply') }}
                </flux:button>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:button :href="route('admin.inbox-index')" wire:navigate icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>
                @can('inbox.delete')
                    <flux:modal.trigger name="delete-submission">
                        <flux:button variant="danger" icon="trash" class="w-full">{{ __('Delete') }}</flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>

            <flux:text size="sm">
                {{ __('Read') }} {{ $submission->read_at?->diffForHumans() }}
            </flux:text>
        </flux:card>
    </div>

    <flux:modal name="delete-submission" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete message') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Are you sure you want to delete this message? This cannot be undone.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item :href="route('admin.inbox-index')" wire:navigate>
            {{ __('Inbox') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $submission->name ?: __('Message') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ Str::limit($submission->name ?: __('Message'), 22) }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item :href="route('admin.inbox-index')" wire:navigate>{{ __('Inbox') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
