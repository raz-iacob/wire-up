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
        $this->page = Page::forSlug($slug)->firstOrFail();
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
    {{-- Page content --}}
</div>