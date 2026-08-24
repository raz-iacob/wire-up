<?php

declare(strict_types=1);

use App\Models\Page;
use App\Services\SettingsService;
use Illuminate\View\View;
use Livewire\Component;

return new class extends Component
{
    public Page $page;

    public bool $unpublished = false;

    public function mount(): void
    {
        $privileged = auth()->user()?->canAccessAdmin() || request()->hasValidSignature();

        $home = SettingsService::current()->homePage(publishedOnly: ! $privileged);

        abort_unless($home instanceof Page, 404);

        $query = Page::query()->with('blocks')->whereKey($home->id);

        if ($privileged) {
            $this->page = $query->firstOrFail();
            $this->unpublished = ! $this->page->isLiveInLocale();

            return;
        }

        $this->page = $query->publishedInLocale()->firstOrFail();
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->page->title ?: config()->string('app.name'))
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
