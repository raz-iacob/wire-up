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
    <x-site.page-content :page="$page" />
    @else
    <div class="mx-auto flex min-h-[60vh] w-full max-w-2xl flex-col items-center justify-center gap-4 text-center">
        <h1 class="text-(length:--wire-heading-size) font-bold tracking-tight">{{ config('app.name') }}</h1>
        <p class="text-(length:--wire-body-size) text-(--wire-muted)">
            {{ __('This site doesn’t have a homepage yet.') }}
        </p>
    </div>
    @endif
</div>