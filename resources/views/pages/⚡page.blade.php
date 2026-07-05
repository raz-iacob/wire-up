<?php

declare(strict_types=1);

use App\Models\Page;
use Livewire\Component;
use Illuminate\View\View;

return new class extends Component
{
    public Page $page;

    public bool $unpublished = false;

    public function mount(string $slug): void
    {
        $query = Page::query()->with('blocks')->forSlug($slug);

        if (auth()->user()?->canAccessAdmin()) {
            $this->page = $query->firstOrFail();
            $this->unpublished = ! $this->page->isLiveInLocale();
        } else {
            $this->page = $query->publishedInLocale()->firstOrFail();
        }
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->page->title)
            ->layoutData([
                'description' => $this->page->description,
                'siteLayout' => $this->page->resolvedLayout(),
                'page' => $this->page,
            ]);
    }
};
?>

<div>
    <x-site.page-content :page="$page" />

    @if ($unpublished)
        <x-site.unpublished-notice :message="__('This page is not published')" />
    @endif
</div>