@props(['name'])

<flux:select
    wire:model.lazy="{{ $name }}.headingLevel"
    label="{{ __('Heading level') }}"
    description="{{ __('Where this heading sits in the page outline. The size does not change.') }}"
>
    <flux:select.option value="">{{ __('Automatic') }}</flux:select.option>
    @foreach (\App\Services\BlockHeading::LEVELS as $level)
        <flux:select.option value="{{ $level }}">{{ mb_strtoupper($level) }}</flux:select.option>
    @endforeach
</flux:select>
