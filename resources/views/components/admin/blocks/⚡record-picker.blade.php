<?php

declare(strict_types=1);

use Livewire\Component;

return new class extends Component
{
    public string $blockId;

    public ?int $recordTypeId = null;

    public int $max = 30;

    public ?string $label = null;

    /**
     * @var array<int, string>
     */
    public array $value = [];

    public function mount(): void
    {
        $this->value = $this->normalize($this->value);
    }

    public function updatedValue(): void
    {
        $this->dispatch(
            'block-records-updated',
            blockId: $this->blockId,
            value: $this->normalize($this->value),
        );
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, string>
     */
    private function normalize(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $id = (string) $id;

            if (! in_array($id, $normalized, true)) {
                $normalized[] = $id;
            }
        }

        return $normalized;
    }
};
?>

<div>
    <livewire:admin.record-selector
        wire:model.live="value"
        wire:key="record-selector-{{ $blockId }}-{{ $recordTypeId }}-inner"
        :name="'block-records-'.$blockId"
        :record-type-id="$recordTypeId"
        :max="$max"
        :label="$label" />
</div>
