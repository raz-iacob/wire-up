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
        $this->page = Page::query()->with('blocks')->forSlug($slug)->publishedInLocale()->firstOrFail();
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->page->title)
            ->layoutData([
                'description' => $this->page->description,
                'siteLayout' => $this->page->resolvedLayout(),
            ]);
    }
};
?>

<div>
    <x-site.page-content :page="$page" />
</div>