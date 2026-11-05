<?php

declare(strict_types=1);

use App\Enums\WebhookEvent;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhooks;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $url = '';

    /**
     * @var array<int, string>
     */
    public array $events = [];

    public bool $active = true;

    public ?int $deletingId = null;

    public ?int $viewingId = null;

    public ?string $newSecret = null;

    /**
     * @return Collection<int, Webhook>
     */
    #[Computed]
    public function webhooks(): Collection
    {
        return Webhook::query()->latest('id')->get();
    }

    #[Computed]
    public function editing(): ?Webhook
    {
        return $this->editingId === null ? null : Webhook::query()->find($this->editingId);
    }

    /**
     * @return Collection<int, WebhookDelivery>
     */
    #[Computed]
    public function deliveries(): Collection
    {
        return $this->viewingId === null
            ? new Collection()
            : WebhookDelivery::query()->where('webhook_id', $this->viewingId)->latest('id')->limit(20)->get();
    }

    public function add(): void
    {
        $this->authorize('settings.edit');

        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->url = '';
        $this->events = [WebhookEvent::SUBMISSION_CREATED->value];
        $this->active = true;

        Flux::modal('webhook-form')->show();
    }

    public function edit(int $id): void
    {
        $this->authorize('settings.edit');

        $webhook = Webhook::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $webhook->id;
        $this->name = $webhook->name;
        $this->url = $webhook->url;
        $this->events = $webhook->events;
        $this->active = $webhook->active;

        Flux::modal('webhook-form')->show();
    }

    public function save(): void
    {
        $this->authorize('settings.edit');

        $this->name = mb_trim($this->name);
        $this->url = mb_trim($this->url);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:2000', 'url', 'starts_with:https://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_map(fn (WebhookEvent $event): string => $event->value, WebhookEvent::subscribable()))],
            'active' => ['boolean'],
        ], [
            'url.starts_with' => __('Use an address that starts with https://.'),
            'events.required' => __('Choose at least one thing to send.'),
            'events.min' => __('Choose at least one thing to send.'),
        ], [
            'name' => __('name'),
            'url' => __('address'),
            'events' => __('events'),
        ]);

        $attributes = [
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => array_values(array_unique($validated['events'])),
            'active' => $validated['active'],
        ];

        if ($this->editingId === null) {
            $secret = Webhook::generateSecret();
            Webhook::query()->create([...$attributes, 'secret' => $secret]);
            $this->newSecret = $secret;
        } else {
            Webhook::query()->findOrFail($this->editingId)->update($attributes);
        }

        unset($this->webhooks);

        Flux::modal('webhook-form')->close();

        if ($this->newSecret !== null) {
            Flux::modal('webhook-secret')->show();

            return;
        }

        Flux::toast(__('Webhook saved.'), variant: 'success');
    }

    public function regenerateSecret(): void
    {
        $this->authorize('settings.edit');

        $secret = Webhook::generateSecret();
        Webhook::query()->findOrFail($this->editingId)->update(['secret' => $secret]);
        unset($this->editing);

        Flux::toast(__('New signing secret created. Update it in the app that receives the webhook.'), variant: 'success');
    }

    public function closeSecret(): void
    {
        $this->newSecret = null;

        Flux::modal('webhook-secret')->close();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('settings.edit');

        $webhook = Webhook::query()->findOrFail($id);
        $webhook->update(['active' => ! $webhook->active]);
        unset($this->webhooks);
    }

    public function sendTest(int $id, Webhooks $webhooks): void
    {
        $this->authorize('settings.edit');

        $delivery = $webhooks->test(Webhook::query()->findOrFail($id));
        unset($this->webhooks, $this->deliveries);

        if ($delivery->succeeded()) {
            Flux::toast(__('Test delivered.'), variant: 'success');

            return;
        }

        Flux::toast(__('The test didn’t go through: :reason', ['reason' => (string) $delivery->error]), variant: 'danger');
    }

    public function showDeliveries(int $id): void
    {
        $this->viewingId = Webhook::query()->findOrFail($id)->id;
        unset($this->deliveries);

        Flux::modal('webhook-deliveries')->show();
    }

    public function redeliver(int $deliveryId, Webhooks $webhooks): void
    {
        $this->authorize('settings.edit');

        $webhooks->redeliver(WebhookDelivery::query()->findOrFail($deliveryId));
        unset($this->deliveries, $this->webhooks);

        Flux::toast(__('Sent again.'), variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('settings.edit');

        $this->deletingId = $id;

        Flux::modal('delete-webhook')->show();
    }

    public function delete(): void
    {
        $this->authorize('settings.edit');

        Webhook::query()->find($this->deletingId)?->delete();

        $this->deletingId = null;
        unset($this->webhooks);

        Flux::modal('delete-webhook')->close();
        Flux::toast(__('Webhook deleted.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Webhooks'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <flux:text class="max-w-xl">
                {{ __('Send form messages, newsletter sign-ups and orders to another app the moment they happen — Zapier, Make, n8n or your own CRM.') }}</flux:text>
            @can('settings.edit')
                <flux:button
                    variant="primary"
                    size="sm"
                    icon="plus"
                    iconVariant="outline"
                    wire:click="add"
                >{{ __('Add webhook') }}</flux:button>
            @endcan
        </div>

        @if ($this->webhooks->isEmpty())
            <flux:text variant="subtle">{{ __('No webhooks yet.') }}</flux:text>
        @else
            <flux:table class="md:table-fixed">
                <flux:table.columns>
                    <flux:table.column>{{ __('Webhook') }}</flux:table.column>
                    <flux:table.column>{{ __('Sends') }}</flux:table.column>
                    <flux:table.column class="w-44">{{ __('Last delivery') }}</flux:table.column>
                    <flux:table.column class="w-20">{{ __('Active') }}</flux:table.column>
                    <flux:table.column class="w-10"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->webhooks as $row)
                        <flux:table.row wire:key="webhook-{{ $row->id }}">
                            <flux:table.cell>
                                <flux:text variant="strong" class="truncate">{{ $row->name }}</flux:text>
                                <flux:text
                                    size="sm"
                                    class="truncate"
                                    title="{{ $row->url }}"
                                >{{ $row->url }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($row->events as $event)
                                        <flux:badge size="sm" color="zinc">
                                            {{ WebhookEvent::tryFrom($event)?->label() ?? $event }}</flux:badge>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($row->last_error !== null)
                                    <flux:badge size="sm" color="red" :title="$row->last_error">
                                        {{ $row->last_status !== null ? __('Failed (:status)', ['status' => $row->last_status]) : __('Failed') }}</flux:badge>
                                @elseif ($row->last_delivered_at !== null)
                                    <flux:badge size="sm" color="green">{{ __('Delivered') }}</flux:badge>
                                    <flux:text
                                        size="sm"
                                        class="mt-1"
                                    >{{ $row->last_delivered_at->diffForHumans() }}</flux:text>
                                @else
                                    <flux:text size="sm" variant="subtle">{{ __('Nothing sent yet') }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:switch
                                    :checked="$row->active"
                                    wire:click="toggleActive({{ $row->id }})"
                                    :disabled="auth()->user()?->cannot('settings.edit')"
                                    :aria-label="__('Active')"
                                />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown class="flex justify-end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                                    <flux:menu>
                                        <flux:menu.item icon="clock" wire:click="showDeliveries({{ $row->id }})">
                                            {{ __('Recent deliveries') }}</flux:menu.item>
                                        @can('settings.edit')
                                            <flux:menu.item icon="paper-airplane" wire:click="sendTest({{ $row->id }})">
                                                {{ __('Send test') }}</flux:menu.item>
                                            <flux:menu.item icon="pencil" wire:click="edit({{ $row->id }})">
                                                {{ __('Edit') }}</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="confirmDelete({{ $row->id }})"
                                            >
                                                {{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="webhook-deliveries" class="w-full md:max-w-2xl">
        <flux:heading size="lg" class="mb-6">{{ __('Recent deliveries') }}</flux:heading>

        @if ($this->deliveries->isEmpty())
            <flux:text variant="subtle">{{ __('Nothing sent yet.') }}</flux:text>
        @else
            <div class="divide-y divide-zinc-200 dark:divide-white/10">
                @foreach ($this->deliveries as $delivery)
                    <div class="flex items-start justify-between gap-4 py-3" wire:key="delivery-{{ $delivery->id }}">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($delivery->succeeded())
                                    <flux:badge size="sm" color="green">{{ __('Delivered') }}</flux:badge>
                                @elseif ($delivery->error !== null)
                                    <flux:badge size="sm" color="red">{{ __('Failed') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Waiting') }}</flux:badge>
                                @endif
                                <flux:text variant="strong">
                                    {{ WebhookEvent::tryFrom($delivery->event)?->label() ?? $delivery->event }}</flux:text>
                                <flux:text size="sm">{{ $delivery->created_at->format('M d, Y H:i') }}</flux:text>
                            </div>
                            @if ($delivery->error !== null)
                                <flux:text size="sm" class="break-words">{{ $delivery->error }}</flux:text>
                            @endif
                        </div>
                        @if (! $delivery->succeeded() && auth()->user()?->can('settings.edit'))
                            <flux:button
                                size="sm"
                                wire:click="redeliver({{ $delivery->id }})"
                            >{{ __('Send again') }}</flux:button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </flux:modal>

    @can('settings.edit')
        <flux:modal name="webhook-form" class="w-full md:max-w-lg">
            <flux:heading size="lg" class="mb-6">
                {{ $editingId === null ? __('Add a webhook') : __('Edit webhook') }}</flux:heading>
            <form wire:submit="save" class="space-y-6">
                <flux:input wire:model="name" :label="__('Name')" placeholder="Zapier" autofocus />
                <flux:input
                    wire:model="url"
                    type="url"
                    :label="__('Address')"
                    placeholder="https://hooks.zapier.com/…"
                />

                <flux:checkbox.group wire:model="events" :label="__('Send')">
                    @foreach (WebhookEvent::subscribable() as $event)
                        <flux:checkbox :value="$event->value" :label="$event->label()" />
                    @endforeach
                </flux:checkbox.group>

                <flux:switch wire:model="active" :label="__('Active')" align="left" />

                @if ($this->editing !== null)
                    <flux:field>
                        <flux:label>{{ __('Signing secret') }}</flux:label>
                        <flux:description>
                            {{ __('Check the X-Wire-Up-Signature header with this secret to make sure a request came from this site.') }}</flux:description>
                        <div class="flex items-center gap-2">
                            <flux:input
                                :value="$this->editing->secret"
                                type="password"
                                readonly
                                viewable
                                copyable
                                class="flex-1"
                            />
                            <flux:button wire:click="regenerateSecret">{{ __('Regenerate') }}</flux:button>
                        </div>
                    </flux:field>
                @endif

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="webhook-secret" class="w-full md:max-w-lg" :dismissible="false" :closable="false">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Webhook added') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Every request is signed with this secret in the X-Wire-Up-Signature header. Copy it into the app that receives the webhook. You can see it again by editing the webhook.') }}</flux:text>
                </div>
                <flux:input :value="$newSecret" readonly copyable />
                <div class="flex">
                    <flux:spacer />
                    <flux:button variant="primary" wire:click="closeSecret">{{ __('Done') }}</flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="delete-webhook" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete webhook') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Nothing more will be sent to this address.') }}</flux:text>
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
    @endcan
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Webhooks') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Webhooks') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
