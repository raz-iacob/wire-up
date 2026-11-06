@props(['name', 'description', 'modal', 'connected' => false])

<flux:card class="h-full space-y-4">
    <div class="flex items-start justify-between gap-3">
        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-zinc-950/5 dark:bg-white/5 dark:ring-white/10">
            {{ $logo }}
        </div>
        <flux:modal.trigger :name="$modal">
            <flux:button size="sm" :variant="$connected ? 'primary' : 'outline'" :icon="$connected ? 'check' : null">
                {{ $connected ? __('Connected') : __('Connect') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <div class="space-y-1">
        @isset($badge)
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ $name }}</flux:heading>
                {{ $badge }}
            </div>
        @else
            <flux:heading size="lg">{{ $name }}</flux:heading>
        @endisset
        <flux:text>{{ $description }}</flux:text>
    </div>
</flux:card>
