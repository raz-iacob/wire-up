<?php

declare(strict_types=1);

use Livewire\Component;
use Illuminate\View\View;

return new class extends Component
{
    public function render(): View
    {
        return $this->view()
            ->title(__('Welcome'));
    }
};
?>

<div class="flex items-center justify-center h-screen text-3xl font-bold">
    {{ __('Welcome') }}
</div>