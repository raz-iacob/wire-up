@props(['message'])

<div class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center p-4">
    <div class="pointer-events-auto flex items-center gap-2 rounded-full bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950 shadow-lg">
        <flux:icon name="eye-slash" variant="mini" />
        {{ $message }}
    </div>
</div>
