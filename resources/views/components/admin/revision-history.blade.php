@props([
    'show' => false,
    'revisions' => [],
])

<flux:modal name="revision-history" flyout class="w-full md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('History') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Restoring loads a version into the editor. Nothing changes until you click Update.') }}</flux:text>
        </div>

        @if ($show)
            <div class="divide-y divide-zinc-100 dark:divide-white/10">
                @forelse ($revisions as $revision)
                    <div class="flex items-center justify-between gap-4 py-3" wire:key="revision-{{ $revision->id }}">
                        <div class="min-w-0">
                            <flux:text
                                class="font-medium text-zinc-800 dark:text-white"
                                title="{{ $revision->created_at->isoFormat('LLL') }}"
                            >
                                {{ $revision->created_at->diffForHumans() }}
                            </flux:text>
                            <flux:text size="sm">{{ $revision->authorName() }}</flux:text>
                        </div>

                        @if ($loop->first)
                            <flux:badge size="sm">{{ __('Current') }}</flux:badge>
                        @else
                            <flux:button
                                size="sm"
                                wire:click="restoreRevision({{ $revision->id }})"
                            >{{ __('Restore') }}</flux:button>
                        @endif
                    </div>
                @empty
                    <flux:text>{{ __('No earlier versions yet. A copy is kept every time you click Update.') }}</flux:text>
                @endforelse
            </div>
        @endif
    </div>
</flux:modal>
