@props(['record', 'field'])

@php
    $fieldType = \App\Enums\FieldType::tryFrom((string) ($field['type'] ?? '')) ?? \App\Enums\FieldType::TEXT;
    $key = (string) $field['key'];
    $value = $record->fieldValue($key, (bool) ($field['translatable'] ?? false));
    $parseDate = fn (mixed $raw): ?\Illuminate\Support\Carbon => is_string($raw) && $raw !== ''
        ? rescue(fn (): \Illuminate\Support\Carbon => \Illuminate\Support\Carbon::parse($raw), null, false)
        : null;

    $text = is_scalar($value) ? (string) $value : '';
    $items = [];
    $href = null;
    $swatch = null;

    if (in_array($fieldType, [\App\Enums\FieldType::TAGS, \App\Enums\FieldType::MULTI_SELECT], true)) {
        $items = array_values(array_filter(is_array($value) ? $value : [], fn (mixed $item): bool => is_string($item) && mb_trim($item) !== ''));
    } elseif ($fieldType === \App\Enums\FieldType::BOOLEAN) {
        $text = $value === null || $value === '' ? '' : ($value ? __('Yes') : __('No'));
    } elseif ($fieldType === \App\Enums\FieldType::MONEY) {
        $text = is_numeric($value) ? \App\Services\SettingsService::current()->formatMoney($value) : '';
    } elseif ($fieldType === \App\Enums\FieldType::DATE) {
        $text = $parseDate($value)?->translatedFormat('F j, Y') ?? '';
    } elseif ($fieldType === \App\Enums\FieldType::DATETIME) {
        $start = $parseDate($value);
        $endField = $key === 'starts_at' ? $record->recordType->fieldByKey('ends_at') : null;
        $end = $endField !== null ? $parseDate($record->fieldValue('ends_at', (bool) ($endField['translatable'] ?? false))) : null;
        $text = match (true) {
            $start === null => '',
            $end !== null && $end->isSameDay($start) => $start->translatedFormat('F j, Y · g:i A').' – '.$end->translatedFormat('g:i A'),
            $end !== null => $start->translatedFormat('F j, Y · g:i A').' – '.$end->translatedFormat('F j, Y · g:i A'),
            default => $start->translatedFormat('F j, Y · g:i A'),
        };
    } elseif ($fieldType === \App\Enums\FieldType::URL && preg_match('#^https?://#i', $text) === 1) {
        $href = $text;
        $host = (string) preg_replace('#^www\.#', '', (string) parse_url($text, PHP_URL_HOST));
        $text = $host !== '' ? $host : $text;
    } elseif ($fieldType === \App\Enums\FieldType::EMAIL && filter_var($text, FILTER_VALIDATE_EMAIL) !== false) {
        $href = 'mailto:'.$text;
    } elseif ($fieldType === \App\Enums\FieldType::COLOR && preg_match('/^#[0-9a-f]{3,8}$/i', $text) === 1) {
        $swatch = $text;
    }
@endphp

@if (mb_trim($text) !== '' || $items !== [])
    <div data-field="{{ $key }}">
        <div class="text-sm opacity-60">{{ $record->recordType->fieldLabel($field) }}</div>
        <div class="mt-1 font-medium">
            @if ($items !== [])
                <ul class="flex flex-wrap gap-2">
                    @foreach ($items as $item)
                        <li class="rounded-full bg-current/10 px-3 py-1 text-sm font-normal">{{ $item }}</li>
                    @endforeach
                </ul>
            @elseif ($href !== null)
                <a
                    href="{{ $href }}"
                    @if ($fieldType === \App\Enums\FieldType::URL) target="_blank" rel="noopener noreferrer" @endif
                    class="break-all text-(--wire-accent) underline underline-offset-4"
                >{{ $text }}</a>
            @elseif ($swatch !== null)
                <span class="inline-flex items-center gap-2">
                    <span
                        class="size-5 rounded-full border border-current/15"
                        style="background-color: {{ $swatch }}"
                    ></span>
                    {{ $swatch }}
                </span>
            @else
                {{ $text }}
            @endif
        </div>
    </div>
@endif
