<?php

declare(strict_types=1);

use App\Models\Page;
use Livewire\Component;
use Illuminate\View\View;

return new class extends Component
{
    public Page $page;

    public function mount(string $slug): void
    {
        $this->page = Page::query()->forSlug($slug)->published()->firstOrFail();
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->page->title)
            ->layoutData([
                'description' => $this->page->description,
            ]);
    }
};
?>

<div>
    <article class="mx-auto w-full max-w-3xl">
        <header class="space-y-4">
            <h1 class="text-(length:--wire-heading-size) font-bold tracking-tight">{{ $page->title }}</h1>
            @if (filled($page->description))
                <p class="text-(length:--wire-body-size) text-(--wire-muted)">{{ $page->description }}</p>
            @endif
        </header>

        {{-- Page content blocks render here once the block builder lands. --}}
    </article>
</div>