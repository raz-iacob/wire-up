<?php

declare(strict_types=1);

namespace App\Traits;

use Flux\Flux;

trait WithBulkSelection
{
    /**
     * @var array<int, int|string>
     */
    public array $selected = [];

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function updatedPaginators(): void
    {
        $this->clearSelection();
    }

    /**
     * @return array<int, int>
     */
    protected function selectedIds(): array
    {
        return array_values(array_unique(array_filter(array_map(intval(...), $this->selected))));
    }

    protected function finishBulkAction(string $message, ?string $variant = null): void
    {
        $this->clearSelection();

        Flux::toast($message, variant: $variant);
    }
}
