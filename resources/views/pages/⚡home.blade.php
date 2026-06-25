<?php

declare(strict_types=1);

use App\Models\Page;
use App\Services\SettingsService;
use Illuminate\View\View;
use Livewire\Component;

return new class extends Component
{
    public Page $page;

    public function mount(): void
    {
        $this->page = Page::query()
            ->whereKey(SettingsService::current()->homePageId())
            ->publishedInLocale()
            ->firstOrFail();
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->page->title ?: config()->string('app.name'))
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