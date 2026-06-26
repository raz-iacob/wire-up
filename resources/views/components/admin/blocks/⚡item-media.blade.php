<?php

declare(strict_types=1);

use Livewire\Component;

return new class extends Component
{
    public string $blockId;

    public string $itemId;

    public string $field;

    public mixed $value = null;

    public string $mediaType = 'image';

    public bool $multiple = false;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $crops = [];

    public ?string $label = null;

    public string $locale = 'en';

    public bool $multiLocale = false;

    public function updatedValue(): void
    {
        $this->dispatch(
            'block-item-media-updated',
            blockId: $this->blockId,
            itemId: $this->itemId,
            field: $this->field,
            value: $this->value,
        );
    }
};
?>

<div>
    <livewire:admin.media-selector
        wire:model.live="value"
        wire:key="item-media-{{ $blockId }}-{{ $itemId }}-{{ $field }}-inner"
        name="item-media-{{ $blockId }}-{{ $itemId }}-{{ $field }}"
        :type="$mediaType"
        :multiple="$multiple"
        :crops="$crops"
        :locale="$locale"
        :multi-locale="$multiLocale"
        :label="$label" />
</div>
