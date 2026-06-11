<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Colour theme presets
    |--------------------------------------------------------------------------
    |
    | Each preset maps to a Tailwind colour name. The site accent is rendered
    | from Flux's per-shade variables (var(--color-{key}-600) etc.). "swatch"
    | is the display hex (Tailwind -500) used in the admin selector. The
    | special "custom" theme lets the admin pick any hex via the colour picker.
    |
    */

    'default' => 'zinc',

    'colors' => [
        'zinc' => ['label' => 'Zinc', 'swatch' => '#71717a'],
        'red' => ['label' => 'Red', 'swatch' => '#ef4444'],
        'orange' => ['label' => 'Orange', 'swatch' => '#f97316'],
        'amber' => ['label' => 'Amber', 'swatch' => '#f59e0b'],
        'yellow' => ['label' => 'Yellow', 'swatch' => '#eab308'],
        'lime' => ['label' => 'Lime', 'swatch' => '#84cc16'],
        'green' => ['label' => 'Green', 'swatch' => '#22c55e'],
        'emerald' => ['label' => 'Emerald', 'swatch' => '#10b981'],
        'teal' => ['label' => 'Teal', 'swatch' => '#14b8a6'],
        'cyan' => ['label' => 'Cyan', 'swatch' => '#06b6d4'],
        'sky' => ['label' => 'Sky', 'swatch' => '#0ea5e9'],
        'blue' => ['label' => 'Blue', 'swatch' => '#3b82f6'],
        'indigo' => ['label' => 'Indigo', 'swatch' => '#6366f1'],
        'violet' => ['label' => 'Violet', 'swatch' => '#8b5cf6'],
        'purple' => ['label' => 'Purple', 'swatch' => '#a855f7'],
        'fuchsia' => ['label' => 'Fuchsia', 'swatch' => '#d946ef'],
        'pink' => ['label' => 'Pink', 'swatch' => '#ec4899'],
        'rose' => ['label' => 'Rose', 'swatch' => '#f43f5e'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | "stack" is the CSS font-family applied via --font-sans / heading font.
    | "google" is the Google Fonts family name to load (null = no web font).
    |
    */

    'default_font' => 'instrument-sans',

    'fonts' => [
        'system' => ['label' => 'System', 'stack' => 'ui-sans-serif, system-ui, sans-serif', 'google' => null],
        'instrument-sans' => ['label' => 'Instrument Sans', 'stack' => '"Instrument Sans", sans-serif', 'google' => 'Instrument Sans'],
        'inter' => ['label' => 'Inter', 'stack' => '"Inter", sans-serif', 'google' => 'Inter'],
        'roboto' => ['label' => 'Roboto', 'stack' => '"Roboto", sans-serif', 'google' => 'Roboto'],
        'open-sans' => ['label' => 'Open Sans', 'stack' => '"Open Sans", sans-serif', 'google' => 'Open Sans'],
        'lato' => ['label' => 'Lato', 'stack' => '"Lato", sans-serif', 'google' => 'Lato'],
        'poppins' => ['label' => 'Poppins', 'stack' => '"Poppins", sans-serif', 'google' => 'Poppins'],
        'montserrat' => ['label' => 'Montserrat', 'stack' => '"Montserrat", sans-serif', 'google' => 'Montserrat'],
        'nunito' => ['label' => 'Nunito', 'stack' => '"Nunito", sans-serif', 'google' => 'Nunito'],
        'raleway' => ['label' => 'Raleway', 'stack' => '"Raleway", sans-serif', 'google' => 'Raleway'],
        'work-sans' => ['label' => 'Work Sans', 'stack' => '"Work Sans", sans-serif', 'google' => 'Work Sans'],
        'dm-sans' => ['label' => 'DM Sans', 'stack' => '"DM Sans", sans-serif', 'google' => 'DM Sans'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Font sizes (rem)
    |--------------------------------------------------------------------------
    */

    'default_heading_size' => 'default',
    'default_body_size' => 'default',

    'heading_sizes' => [
        'small' => '1.25rem',
        'default' => '1.5rem',
        'large' => '1.875rem',
        'xl' => '2.25rem',
    ],

    'body_sizes' => [
        'small' => '0.8125rem',
        'default' => '0.875rem',
        'large' => '1rem',
        'xl' => '1.125rem',
    ],

    /*
    |--------------------------------------------------------------------------
    | Border radius (rem)
    |--------------------------------------------------------------------------
    */

    'default_radius' => 'default',

    'radii' => [
        'none' => '0px',
        'small' => '0.25rem',
        'default' => '0.5rem',
        'large' => '1rem',
    ],

];
