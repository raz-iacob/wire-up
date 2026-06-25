<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

return new class extends Component
{
    public Page $page;

    public string $pageTitle = '';

    public string $pageDescription = '';

    /**
     * @var array<string, mixed>
     */
    public array $siteLayout = [];

    public function mount(Page $page, string $token): void
    {
        /** @var array{page_id: int, locale: string, title: string, description: string, blocks: array<int, array{type: string, content?: array<string, mixed>, position?: int}>, layout?: array<string, mixed>}|null $snapshot */
        $snapshot = Cache::get("page-preview:{$page->id}:".auth()->id().":{$token}");

        abort_unless(is_array($snapshot) && $snapshot['page_id'] === $page->id, 404);

        app()->setLocale($snapshot['locale']);

        $this->pageTitle = $snapshot['title'];
        $this->pageDescription = $snapshot['description'];
        $this->siteLayout = Page::normalizeLayout($snapshot['layout'] ?? []);

        $page->setRelation('blocks', collect($snapshot['blocks'])
            ->map(fn (array $block): Block => new Block([
                'type' => $block['type'],
                'content' => $block['content'] ?? [],
                'position' => $block['position'] ?? 0,
            ]))
            ->values());

        $this->page = $page;
    }

    public function render(): View
    {
        return $this->view()
            ->layout('layouts.app')
            ->title($this->pageTitle)
            ->layoutData([
                'description' => $this->pageDescription,
                'siteLayout' => $this->siteLayout,
            ]);
    }
};
?>

<div>
    <x-site.page-content :page="$page" />
</div>
