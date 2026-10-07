<div wire:key="field-wrapper-stock">
    <flux:input
        wire:model="stock"
        type="number"
        min="0"
        step="1"
        :label="__('Stock')"
        :placeholder="__('Not tracked')"
        :description:trailing="__('Leave empty to sell without counting.')"
    />
</div>
