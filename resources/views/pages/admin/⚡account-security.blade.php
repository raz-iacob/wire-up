<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public function render(): View
    {
        return $this->view()
            ->title(__('Security'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.account-layout>
    <livewire:shared.two-factor />
</x-admin.account-layout>
