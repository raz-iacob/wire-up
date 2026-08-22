@props(['badge' => '', 'color' => 'zinc', 'shape' => 'pill'])

@if ($badge !== '')
    <span
        @class([
            'font-semibold',
            'ms-1.5 inline-block rounded-full px-2 py-0.5 align-middle text-[0.6875em] leading-tight' => $shape === 'pill',
            'shrink-0 rounded-(--wire-radius) px-1.5 py-0.5 text-xs' => $shape === 'chip',
            'bg-(--wire-primary-bg) text-(--wire-primary-text)' => $color === 'primary',
            'bg-green-600/15 text-green-700 dark:bg-green-400/20 dark:text-green-300' => $color === 'green',
            'bg-red-600/15 text-red-700 dark:bg-red-400/20 dark:text-red-300' => $color === 'red',
            'bg-amber-600/15 text-amber-700 dark:bg-amber-400/20 dark:text-amber-300' => $color === 'amber',
            'bg-blue-600/15 text-blue-700 dark:bg-blue-400/20 dark:text-blue-300' => $color === 'blue',
            'bg-purple-600/15 text-purple-700 dark:bg-purple-400/20 dark:text-purple-300' => $color === 'purple',
            'bg-current/10' => ! in_array($color, ['primary', 'green', 'red', 'amber', 'blue', 'purple'], true),
        ])
    >{{ $badge }}</span>
@endif
