<?php

declare(strict_types=1);

use App\Models\Page;
use App\Services\SettingsService;
use Illuminate\View\View;
use Livewire\Component;

return new class extends Component
{
    public ?Page $page = null;

    public function mount(): void
    {
        $this->page = SettingsService::current()->homePage();
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->page?->title ?: config()->string('app.name'))
            ->layoutData(['description' => $this->page?->description]);
    }
};
?>

<div>
    @if ($page)
        <x-site.page-article :page="$page" />
    @else
        <x-site.home-fallback />
    @endif
</div>
