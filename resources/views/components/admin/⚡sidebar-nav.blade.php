<?php

declare(strict_types=1);

use App\Models\RecordType;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    /**
     * @return Collection<int, RecordType>
     */
    #[Computed]
    public function recordTypes(): Collection
    {
        return RecordType::ordered();
    }

    #[On('content-types-updated')]
    public function refreshTypes(): void
    {
        unset($this->recordTypes);
    }
};
?>

{{-- display:contents keeps the required Livewire root out of the sidebar's flex column,
     so each type sits on its own row like the items around it --}}
<div class="contents">
    @if ($this->recordTypes->isNotEmpty())
        @foreach ($this->recordTypes as $recordType)
            @can('records.'.$recordType->key.'.view')
                <flux:sidebar.item
                    wire:key="record-type-{{ $recordType->id }}"
                    :icon="$recordType->icon"
                    :href="route('admin.records-index', $recordType)"
                    :current="request()->routeIs('admin.records-*') && request()->route('recordType')?->key === $recordType->key"
                    wire:navigate.hover
                >
                    {{ $recordType->name }}</flux:sidebar.item>
            @endcan
        @endforeach
    @endif
</div>
