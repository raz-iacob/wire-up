@if (\App\Services\Impersonation::active())
    <div class="bg-amber-400 text-sm text-amber-950">
        <div class="mx-auto flex max-w-(--wire-container) flex-wrap items-center justify-center gap-x-3 gap-y-1 px-(--wire-gutter) py-2">
            <span>{{ __('Viewing the site as :name.', ['name' => auth()->user()?->name]) }}</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">
                @csrf
                <button type="submit" class="cursor-pointer font-semibold underline underline-offset-2">
                    {{ __('Back to the admin') }}
                </button>
            </form>
        </div>
    </div>
@endif
