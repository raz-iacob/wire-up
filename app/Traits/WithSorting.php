<?php

declare(strict_types=1);

namespace App\Traits;

trait WithSorting
{
    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public function sort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }
}
