<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Colour theme
    |--------------------------------------------------------------------------
    |
    | A theme is a full palette applied to the public site. "slots" defines the
    | editable colours (grouped for the custom editor + validation); "presets"
    | are ready-made palettes. The "custom" theme lets an admin set every slot.
    |
    */

    'default' => 'default',

    'default_dark' => 'on',

    'slots' => [
        'background' => ['label' => 'Background', 'group' => 'General'],
        'text' => ['label' => 'Text', 'group' => 'General'],
        'muted' => ['label' => 'Muted text', 'group' => 'General'],
        'accent' => ['label' => 'Accent', 'group' => 'General'],
        'divider' => ['label' => 'Divider', 'group' => 'General'],
        'card_bg' => ['label' => 'Background', 'group' => 'Cards'],
        'card_text' => ['label' => 'Text', 'group' => 'Cards'],
        'card_border' => ['label' => 'Border', 'group' => 'Cards'],
        'input_bg' => ['label' => 'Background', 'group' => 'Inputs'],
        'input_text' => ['label' => 'Text', 'group' => 'Inputs'],
        'input_border' => ['label' => 'Border', 'group' => 'Inputs'],
        'primary_bg' => ['label' => 'Primary button', 'group' => 'Buttons'],
        'primary_text' => ['label' => 'Primary text', 'group' => 'Buttons'],
        'secondary_bg' => ['label' => 'Secondary button', 'group' => 'Buttons'],
        'secondary_text' => ['label' => 'Secondary text', 'group' => 'Buttons'],
        'primary_border' => ['label' => 'Primary border', 'group' => 'Buttons'],
        'secondary_border' => ['label' => 'Secondary border', 'group' => 'Buttons'],
        'hero_bg' => ['label' => 'Background', 'group' => 'Hero'],
        'hero_text' => ['label' => 'Text', 'group' => 'Hero'],
        'header_bg' => ['label' => 'Background', 'group' => 'Header'],
        'header_text' => ['label' => 'Text', 'group' => 'Header'],
        'footer_bg' => ['label' => 'Background', 'group' => 'Footer'],
        'footer_text' => ['label' => 'Text', 'group' => 'Footer'],
    ],

    'presets' => [
        'default' => [
            'label' => 'Default',
            'colors' => [
                'background' => '#ffffff', 'text' => '#18181b', 'muted' => '#71717a',
                'card_bg' => '#f4f4f5', 'card_text' => '#18181b', 'card_border' => '#e4e4e7', 'divider' => '#e4e4e7',
                'input_bg' => '#ffffff', 'input_text' => '#18181b', 'input_border' => '#d4d4d8',
                'hero_bg' => '#eaeaec', 'hero_text' => '#18181b',
                'header_bg' => '#ffffff', 'header_text' => '#18181b', 'footer_bg' => '#f4f4f5', 'footer_text' => '#3f3f46',
                'primary_bg' => '#18181b', 'primary_text' => '#ffffff', 'secondary_bg' => '#e4e4e7', 'secondary_text' => '#18181b', 'primary_border' => '#18181b', 'accent' => '#18181b', 'secondary_border' => '#e4e4e7',
            ],
            'colors_dark' => [
                'background' => '#0a0a0a', 'text' => '#fafafa', 'muted' => '#a1a1aa',
                'card_bg' => '#18181b', 'card_text' => '#fafafa', 'card_border' => '#27272a', 'divider' => '#27272a',
                'input_bg' => '#18181b', 'input_text' => '#fafafa', 'input_border' => '#3f3f46',
                'hero_bg' => '#232326', 'hero_text' => '#fafafa',
                'header_bg' => '#0a0a0a', 'header_text' => '#fafafa', 'footer_bg' => '#000000', 'footer_text' => '#a1a1aa',
                'primary_bg' => '#fafafa', 'primary_text' => '#18181b', 'secondary_bg' => '#27272a', 'secondary_text' => '#fafafa', 'primary_border' => '#fafafa', 'accent' => '#fafafa', 'secondary_border' => '#27272a',
            ],
        ],
        'slate' => [
            'label' => 'Slate',
            'colors' => [
                'background' => '#f8fafc', 'text' => '#0f172a', 'muted' => '#64748b',
                'card_bg' => '#ffffff', 'card_text' => '#0f172a', 'card_border' => '#e2e8f0', 'divider' => '#e2e8f0',
                'input_bg' => '#ffffff', 'input_text' => '#0f172a', 'input_border' => '#cbd5e1',
                'hero_bg' => '#e2e9f2', 'hero_text' => '#0f172a',
                'header_bg' => '#ffffff', 'header_text' => '#0f172a', 'footer_bg' => '#0f172a', 'footer_text' => '#cbd5e1',
                'primary_bg' => '#2563eb', 'primary_text' => '#ffffff', 'secondary_bg' => '#e2e8f0', 'secondary_text' => '#0f172a', 'primary_border' => '#2563eb', 'accent' => '#2563eb', 'secondary_border' => '#e2e8f0',
            ],
            'colors_dark' => [
                'background' => '#0f172a', 'text' => '#e2e8f0', 'muted' => '#94a3b8',
                'card_bg' => '#1e293b', 'card_text' => '#e2e8f0', 'card_border' => '#334155', 'divider' => '#334155',
                'input_bg' => '#1e293b', 'input_text' => '#e2e8f0', 'input_border' => '#475569',
                'hero_bg' => '#1e293b', 'hero_text' => '#e2e8f0',
                'header_bg' => '#0f172a', 'header_text' => '#e2e8f0', 'footer_bg' => '#020617', 'footer_text' => '#94a3b8',
                'primary_bg' => '#2563eb', 'primary_text' => '#ffffff', 'secondary_bg' => '#1e293b', 'secondary_text' => '#e2e8f0', 'primary_border' => '#2563eb', 'accent' => '#60a5fa', 'secondary_border' => '#334155',
            ],
        ],
        'blueprint' => [
            'label' => 'Blueprint',
            'colors' => [
                'background' => '#ffffff', 'text' => '#0a1220', 'muted' => '#5a6a7f',
                'card_bg' => '#f7f9fc', 'card_text' => '#0a1220', 'card_border' => '#e2e9f1', 'divider' => '#e4eaf1',
                'input_bg' => '#ffffff', 'input_text' => '#0a1220', 'input_border' => '#d3dde8',
                'hero_bg' => '#ffffff', 'hero_text' => '#0a1220',
                'header_bg' => '#ffffff', 'header_text' => '#0a1220', 'footer_bg' => '#f7f9fc', 'footer_text' => '#35475e',
                'primary_bg' => '#0a1220', 'primary_text' => '#ffffff', 'secondary_bg' => '#eef3f9', 'secondary_text' => '#0a1220', 'primary_border' => '#0a1220', 'accent' => '#0b73b4', 'secondary_border' => '#dde5ee',
            ],
            'colors_dark' => [
                'background' => '#060a11', 'text' => '#e7eef8', 'muted' => '#8d9bb0',
                'card_bg' => '#0b111b', 'card_text' => '#e7eef8', 'card_border' => '#1b2634', 'divider' => '#17212f',
                'input_bg' => '#0b111b', 'input_text' => '#e7eef8', 'input_border' => '#253141',
                'hero_bg' => '#141d2c', 'hero_text' => '#e7eef8',
                'header_bg' => '#060a11', 'header_text' => '#e7eef8', 'footer_bg' => '#04070d', 'footer_text' => '#9fb0c4',
                'primary_bg' => '#38b6ff', 'primary_text' => '#04121f', 'secondary_bg' => '#111a26', 'secondary_text' => '#e7eef8', 'primary_border' => '#38b6ff', 'accent' => '#38b6ff', 'secondary_border' => '#2a3646',
            ],
        ],
        'ocean' => [
            'label' => 'Ocean',
            'colors' => [
                'background' => '#f4fafe', 'text' => '#0c3f5c', 'muted' => '#4a7692',
                'card_bg' => '#ffffff', 'card_text' => '#0c3f5c', 'card_border' => '#d9e9f4', 'divider' => '#d5e8f5',
                'input_bg' => '#ffffff', 'input_text' => '#0c3f5c', 'input_border' => '#b9d5e8',
                'hero_bg' => '#0c3f5c', 'hero_text' => '#e6f4fd',
                'header_bg' => '#0c3f5c', 'header_text' => '#e6f4fd', 'footer_bg' => '#082f49', 'footer_text' => '#b9d5e8',
                'primary_bg' => '#0369a1', 'primary_text' => '#ffffff', 'secondary_bg' => '#e2eff8', 'secondary_text' => '#0c3f5c', 'primary_border' => '#0369a1', 'accent' => '#0369a1', 'secondary_border' => '#cfe3f0',
            ],
            'colors_dark' => [
                'background' => '#04202f', 'text' => '#dbeefb', 'muted' => '#8bb3cc',
                'card_bg' => '#0a3049', 'card_text' => '#dbeefb', 'card_border' => '#14455f', 'divider' => '#123c53',
                'input_bg' => '#0a3049', 'input_text' => '#dbeefb', 'input_border' => '#1d5878',
                'hero_bg' => '#0a3049', 'hero_text' => '#dbeefb',
                'header_bg' => '#04202f', 'header_text' => '#dbeefb', 'footer_bg' => '#01131d', 'footer_text' => '#9dc3d8',
                'primary_bg' => '#38bdf8', 'primary_text' => '#04202f', 'secondary_bg' => '#0a3049', 'secondary_text' => '#dbeefb', 'primary_border' => '#38bdf8', 'accent' => '#56ccff', 'secondary_border' => '#14455f',
            ],
        ],
        'lagoon' => [
            'label' => 'Lagoon',
            'colors' => [
                'background' => '#ffffff', 'text' => '#002642', 'muted' => '#55707f',
                'card_bg' => '#f7fafb', 'card_text' => '#002642', 'card_border' => '#e4eaee', 'divider' => '#ebeef0',
                'input_bg' => '#ffffff', 'input_text' => '#002642', 'input_border' => '#cfd8de',
                'hero_bg' => '#002642', 'hero_text' => '#dff1ef',
                'header_bg' => '#002642', 'header_text' => '#dff1ef', 'footer_bg' => '#002642', 'footer_text' => '#a8c4d2',
                'primary_bg' => '#008478', 'primary_text' => '#ffffff', 'secondary_bg' => '#e6f4f2', 'secondary_text' => '#002642', 'primary_border' => '#008478', 'accent' => '#00786e', 'secondary_border' => '#d3e7e4',
            ],
            'colors_dark' => [
                'background' => '#02161f', 'text' => '#dff1ef', 'muted' => '#8daeb4',
                'card_bg' => '#07262f', 'card_text' => '#dff1ef', 'card_border' => '#103641', 'divider' => '#0d303a',
                'input_bg' => '#07262f', 'input_text' => '#dff1ef', 'input_border' => '#17475a',
                'hero_bg' => '#0a3040', 'hero_text' => '#dff1ef',
                'header_bg' => '#02161f', 'header_text' => '#dff1ef', 'footer_bg' => '#000c13', 'footer_text' => '#9fbfc4',
                'primary_bg' => '#008478', 'primary_text' => '#ffffff', 'secondary_bg' => '#07262f', 'secondary_text' => '#dff1ef', 'primary_border' => '#008478', 'accent' => '#2fd9c7', 'secondary_border' => '#103641',
            ],
        ],
        'sunset' => [
            'label' => 'Sunset',
            'colors' => [
                'background' => '#fffbf7', 'text' => '#1f1512', 'muted' => '#6b5b52',
                'card_bg' => '#ffffff', 'card_text' => '#1f1512', 'card_border' => '#f0e5db', 'divider' => '#f2e8df',
                'input_bg' => '#ffffff', 'input_text' => '#1f1512', 'input_border' => '#e0d2c6',
                'hero_bg' => '#1f1512', 'hero_text' => '#ffeede',
                'header_bg' => '#ffffff', 'header_text' => '#1f1512', 'footer_bg' => '#1f1512', 'footer_text' => '#d8c6ba',
                'primary_bg' => '#b4470e', 'primary_text' => '#ffffff', 'secondary_bg' => '#fdf0e6', 'secondary_text' => '#1f1512', 'primary_border' => '#b4470e', 'accent' => '#b4470e', 'secondary_border' => '#f4e3d5',
            ],
            'colors_dark' => [
                'background' => '#14100e', 'text' => '#f5eae1', 'muted' => '#a89486',
                'card_bg' => '#1f1815', 'card_text' => '#f5eae1', 'card_border' => '#2f2621', 'divider' => '#2a221e',
                'input_bg' => '#1f1815', 'input_text' => '#f5eae1', 'input_border' => '#3d332c',
                'hero_bg' => '#241b17', 'hero_text' => '#f5eae1',
                'header_bg' => '#14100e', 'header_text' => '#f5eae1', 'footer_bg' => '#0d0a09', 'footer_text' => '#bfaea2',
                'primary_bg' => '#c2410c', 'primary_text' => '#ffffff', 'secondary_bg' => '#1f1815', 'secondary_text' => '#f5eae1', 'primary_border' => '#c2410c', 'accent' => '#fb923c', 'secondary_border' => '#2f2621',
            ],
        ],
        'rose' => [
            'label' => 'Rose',
            'colors' => [
                'background' => '#fffafb', 'text' => '#1c1014', 'muted' => '#6d5257',
                'card_bg' => '#ffffff', 'card_text' => '#1c1014', 'card_border' => '#f2e3e7', 'divider' => '#f5e8eb',
                'input_bg' => '#ffffff', 'input_text' => '#1c1014', 'input_border' => '#e6d1d6',
                'hero_bg' => '#1c1014', 'hero_text' => '#ffe8ee',
                'header_bg' => '#ffffff', 'header_text' => '#1c1014', 'footer_bg' => '#1c1014', 'footer_text' => '#dcc2ca',
                'primary_bg' => '#be123c', 'primary_text' => '#ffffff', 'secondary_bg' => '#fdeaef', 'secondary_text' => '#1c1014', 'primary_border' => '#be123c', 'accent' => '#be123c', 'secondary_border' => '#f6dde3',
            ],
            'colors_dark' => [
                'background' => '#130d0f', 'text' => '#f7e9ed', 'muted' => '#a98d95',
                'card_bg' => '#1e1418', 'card_text' => '#f7e9ed', 'card_border' => '#2e2025', 'divider' => '#291c21',
                'input_bg' => '#1e1418', 'input_text' => '#f7e9ed', 'input_border' => '#3c2b31',
                'hero_bg' => '#23171c', 'hero_text' => '#f7e9ed',
                'header_bg' => '#130d0f', 'header_text' => '#f7e9ed', 'footer_bg' => '#0c0709', 'footer_text' => '#c4a5ad',
                'primary_bg' => '#be123c', 'primary_text' => '#ffffff', 'secondary_bg' => '#1e1418', 'secondary_text' => '#f7e9ed', 'primary_border' => '#be123c', 'accent' => '#fb7185', 'secondary_border' => '#2e2025',
            ],
        ],
        'royal' => [
            'label' => 'Royal',
            'colors' => [
                'background' => '#fdfbff', 'text' => '#16101f', 'muted' => '#605571',
                'card_bg' => '#ffffff', 'card_text' => '#16101f', 'card_border' => '#e9e3f2', 'divider' => '#ede7f5',
                'input_bg' => '#ffffff', 'input_text' => '#16101f', 'input_border' => '#dad0ea',
                'hero_bg' => '#16101f', 'hero_text' => '#efe8ff',
                'header_bg' => '#ffffff', 'header_text' => '#16101f', 'footer_bg' => '#16101f', 'footer_text' => '#cdc2e0',
                'primary_bg' => '#6d28d9', 'primary_text' => '#ffffff', 'secondary_bg' => '#f2ecfd', 'secondary_text' => '#16101f', 'primary_border' => '#6d28d9', 'accent' => '#6d28d9', 'secondary_border' => '#e6dcf9',
            ],
            'colors_dark' => [
                'background' => '#0f0b17', 'text' => '#ece5f8', 'muted' => '#9b8fb2',
                'card_bg' => '#181123', 'card_text' => '#ece5f8', 'card_border' => '#281d38', 'divider' => '#231932',
                'input_bg' => '#181123', 'input_text' => '#ece5f8', 'input_border' => '#352748',
                'hero_bg' => '#1c1429', 'hero_text' => '#ece5f8',
                'header_bg' => '#0f0b17', 'header_text' => '#ece5f8', 'footer_bg' => '#090610', 'footer_text' => '#b3a6c9',
                'primary_bg' => '#7c3aed', 'primary_text' => '#ffffff', 'secondary_bg' => '#181123', 'secondary_text' => '#ece5f8', 'primary_border' => '#7c3aed', 'accent' => '#a78bfa', 'secondary_border' => '#281d38',
            ],
        ],
        'mono' => [
            'label' => 'Mono',
            'colors' => [
                'background' => '#ffffff', 'text' => '#0a0a0a', 'muted' => '#5c5c5c',
                'card_bg' => '#fafafa', 'card_text' => '#0a0a0a', 'card_border' => '#e5e5e5', 'divider' => '#e5e5e5',
                'input_bg' => '#ffffff', 'input_text' => '#0a0a0a', 'input_border' => '#c4c4c4',
                'hero_bg' => '#f0f0f0', 'hero_text' => '#0a0a0a',
                'header_bg' => '#0a0a0a', 'header_text' => '#ffffff', 'footer_bg' => '#0a0a0a', 'footer_text' => '#c4c4c4',
                'primary_bg' => '#0a0a0a', 'primary_text' => '#ffffff', 'secondary_bg' => '#ededed', 'secondary_text' => '#0a0a0a', 'primary_border' => '#0a0a0a', 'accent' => '#0a0a0a', 'secondary_border' => '#dedede',
            ],
            'colors_dark' => [
                'background' => '#000000', 'text' => '#fafafa', 'muted' => '#9e9e9e',
                'card_bg' => '#0d0d0d', 'card_text' => '#fafafa', 'card_border' => '#262626', 'divider' => '#242424',
                'input_bg' => '#0d0d0d', 'input_text' => '#fafafa', 'input_border' => '#4a4a4a',
                'hero_bg' => '#141414', 'hero_text' => '#fafafa',
                'header_bg' => '#000000', 'header_text' => '#fafafa', 'footer_bg' => '#000000', 'footer_text' => '#9e9e9e',
                'primary_bg' => '#fafafa', 'primary_text' => '#0a0a0a', 'secondary_bg' => '#1c1c1c', 'secondary_text' => '#fafafa', 'primary_border' => '#fafafa', 'accent' => '#fafafa', 'secondary_border' => '#2e2e2e',
            ],
        ],
        'sand' => [
            'label' => 'Sand',
            'colors' => [
                'background' => '#fdfcfa', 'text' => '#1c1917', 'muted' => '#6b625a',
                'card_bg' => '#ffffff', 'card_text' => '#1c1917', 'card_border' => '#ebe7e1', 'divider' => '#efece6',
                'input_bg' => '#ffffff', 'input_text' => '#1c1917', 'input_border' => '#ddd7cf',
                'hero_bg' => '#f2ede4', 'hero_text' => '#1c1917',
                'header_bg' => '#ffffff', 'header_text' => '#1c1917', 'footer_bg' => '#1c1917', 'footer_text' => '#cfc7bd',
                'primary_bg' => '#8a6410', 'primary_text' => '#ffffff', 'secondary_bg' => '#f5f0e6', 'secondary_text' => '#1c1917', 'primary_border' => '#8a6410', 'accent' => '#8a6410', 'secondary_border' => '#ebe4d6',
            ],
            'colors_dark' => [
                'background' => '#14120f', 'text' => '#f3efe8', 'muted' => '#a49a8c',
                'card_bg' => '#1e1b16', 'card_text' => '#f3efe8', 'card_border' => '#2d2921', 'divider' => '#28241d',
                'input_bg' => '#1e1b16', 'input_text' => '#f3efe8', 'input_border' => '#3a352b',
                'hero_bg' => '#231f19', 'hero_text' => '#f3efe8',
                'header_bg' => '#14120f', 'header_text' => '#f3efe8', 'footer_bg' => '#0d0b09', 'footer_text' => '#bcb1a2',
                'primary_bg' => '#8a6410', 'primary_text' => '#ffffff', 'secondary_bg' => '#1e1b16', 'secondary_text' => '#f3efe8', 'primary_border' => '#8a6410', 'accent' => '#e0b04a', 'secondary_border' => '#2d2921',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | "stack" is the CSS font-family; "google" is the Google Fonts family to
    | load ('' = no web font).
    |
    */

    'default_font' => 'instrument-sans',

    'fonts' => [
        'system' => ['label' => 'System', 'stack' => 'ui-sans-serif, system-ui, sans-serif', 'google' => ''],

        'instrument-sans' => ['label' => 'Instrument Sans', 'stack' => '"Instrument Sans", sans-serif', 'google' => 'Instrument Sans'],
        'inter' => ['label' => 'Inter', 'stack' => '"Inter", sans-serif', 'google' => 'Inter'],
        'roboto' => ['label' => 'Roboto', 'stack' => '"Roboto", sans-serif', 'google' => 'Roboto'],
        'open-sans' => ['label' => 'Open Sans', 'stack' => '"Open Sans", sans-serif', 'google' => 'Open Sans'],
        'lato' => ['label' => 'Lato', 'stack' => '"Lato", sans-serif', 'google' => 'Lato'],
        'montserrat' => ['label' => 'Montserrat', 'stack' => '"Montserrat", sans-serif', 'google' => 'Montserrat'],
        'poppins' => ['label' => 'Poppins', 'stack' => '"Poppins", sans-serif', 'google' => 'Poppins'],
        'nunito' => ['label' => 'Nunito', 'stack' => '"Nunito", sans-serif', 'google' => 'Nunito'],
        'nunito-sans' => ['label' => 'Nunito Sans', 'stack' => '"Nunito Sans", sans-serif', 'google' => 'Nunito Sans'],
        'raleway' => ['label' => 'Raleway', 'stack' => '"Raleway", sans-serif', 'google' => 'Raleway'],
        'work-sans' => ['label' => 'Work Sans', 'stack' => '"Work Sans", sans-serif', 'google' => 'Work Sans'],
        'dm-sans' => ['label' => 'DM Sans', 'stack' => '"DM Sans", sans-serif', 'google' => 'DM Sans'],
        'source-sans-3' => ['label' => 'Source Sans 3', 'stack' => '"Source Sans 3", sans-serif', 'google' => 'Source Sans 3'],
        'noto-sans' => ['label' => 'Noto Sans', 'stack' => '"Noto Sans", sans-serif', 'google' => 'Noto Sans'],
        'rubik' => ['label' => 'Rubik', 'stack' => '"Rubik", sans-serif', 'google' => 'Rubik'],
        'mulish' => ['label' => 'Mulish', 'stack' => '"Mulish", sans-serif', 'google' => 'Mulish'],
        'manrope' => ['label' => 'Manrope', 'stack' => '"Manrope", sans-serif', 'google' => 'Manrope'],
        'karla' => ['label' => 'Karla', 'stack' => '"Karla", sans-serif', 'google' => 'Karla'],
        'figtree' => ['label' => 'Figtree', 'stack' => '"Figtree", sans-serif', 'google' => 'Figtree'],
        'plus-jakarta-sans' => ['label' => 'Plus Jakarta Sans', 'stack' => '"Plus Jakarta Sans", sans-serif', 'google' => 'Plus Jakarta Sans'],
        'outfit' => ['label' => 'Outfit', 'stack' => '"Outfit", sans-serif', 'google' => 'Outfit'],
        'barlow' => ['label' => 'Barlow', 'stack' => '"Barlow", sans-serif', 'google' => 'Barlow'],
        'kanit' => ['label' => 'Kanit', 'stack' => '"Kanit", sans-serif', 'google' => 'Kanit'],
        'oswald' => ['label' => 'Oswald', 'stack' => '"Oswald", sans-serif', 'google' => 'Oswald'],
        'pt-sans' => ['label' => 'PT Sans', 'stack' => '"PT Sans", sans-serif', 'google' => 'PT Sans'],

        'playfair-display' => ['label' => 'Playfair Display', 'stack' => '"Playfair Display", serif', 'google' => 'Playfair Display'],
        'merriweather' => ['label' => 'Merriweather', 'stack' => '"Merriweather", serif', 'google' => 'Merriweather'],
        'lora' => ['label' => 'Lora', 'stack' => '"Lora", serif', 'google' => 'Lora'],
        'pt-serif' => ['label' => 'PT Serif', 'stack' => '"PT Serif", serif', 'google' => 'PT Serif'],
        'roboto-slab' => ['label' => 'Roboto Slab', 'stack' => '"Roboto Slab", serif', 'google' => 'Roboto Slab'],
        'source-serif-4' => ['label' => 'Source Serif 4', 'stack' => '"Source Serif 4", serif', 'google' => 'Source Serif 4'],
        'noto-serif' => ['label' => 'Noto Serif', 'stack' => '"Noto Serif", serif', 'google' => 'Noto Serif'],
        'libre-baskerville' => ['label' => 'Libre Baskerville', 'stack' => '"Libre Baskerville", serif', 'google' => 'Libre Baskerville'],
        'eb-garamond' => ['label' => 'EB Garamond', 'stack' => '"EB Garamond", serif', 'google' => 'EB Garamond'],
        'cormorant-garamond' => ['label' => 'Cormorant Garamond', 'stack' => '"Cormorant Garamond", serif', 'google' => 'Cormorant Garamond'],
        'bitter' => ['label' => 'Bitter', 'stack' => '"Bitter", serif', 'google' => 'Bitter'],
        'crimson-text' => ['label' => 'Crimson Text', 'stack' => '"Crimson Text", serif', 'google' => 'Crimson Text'],

        'bebas-neue' => ['label' => 'Bebas Neue', 'stack' => '"Bebas Neue", sans-serif', 'google' => 'Bebas Neue'],
        'anton' => ['label' => 'Anton', 'stack' => '"Anton", sans-serif', 'google' => 'Anton'],
        'archivo-black' => ['label' => 'Archivo Black', 'stack' => '"Archivo Black", sans-serif', 'google' => 'Archivo Black'],
        'abril-fatface' => ['label' => 'Abril Fatface', 'stack' => '"Abril Fatface", serif', 'google' => 'Abril Fatface'],
        'lobster' => ['label' => 'Lobster', 'stack' => '"Lobster", cursive', 'google' => 'Lobster'],
        'pacifico' => ['label' => 'Pacifico', 'stack' => '"Pacifico", cursive', 'google' => 'Pacifico'],
        'caveat' => ['label' => 'Caveat', 'stack' => '"Caveat", cursive', 'google' => 'Caveat'],
        'dancing-script' => ['label' => 'Dancing Script', 'stack' => '"Dancing Script", cursive', 'google' => 'Dancing Script'],

        'jetbrains-mono' => ['label' => 'JetBrains Mono', 'stack' => '"JetBrains Mono", monospace', 'google' => 'JetBrains Mono'],
        'fira-code' => ['label' => 'Fira Code', 'stack' => '"Fira Code", monospace', 'google' => 'Fira Code'],
        'source-code-pro' => ['label' => 'Source Code Pro', 'stack' => '"Source Code Pro", monospace', 'google' => 'Source Code Pro'],
        'space-mono' => ['label' => 'Space Mono', 'stack' => '"Space Mono", monospace', 'google' => 'Space Mono'],
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
        'small' => '0.875rem',
        'default' => '1rem',
        'large' => '1.125rem',
        'xl' => '1.25rem',
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

    /*
    |--------------------------------------------------------------------------
    | Border width
    |--------------------------------------------------------------------------
    |
    | The general border width token (--wire-border-width), used for button
    | borders and other site outlines.
    |
    */

    'default_border_width' => 'thin',

    'border_widths' => [
        'thin' => '1px',
        'medium' => '2px',
        'thick' => '3px',
    ],

    'default_container' => 'medium',

    'containers' => [
        'small' => '64rem',
        'medium' => '72rem',
        'large' => '80rem',
        'full' => '100%',
    ],

    /*
    |--------------------------------------------------------------------------
    | Block spacing
    |--------------------------------------------------------------------------
    |
    | Controls the vertical gap between page-builder blocks (and the inner
    | padding of blocks that use a background colour). Values map to the
    | gap and padding utilities in the page-content and block views.
    |
    */

    'default_block_spacing' => 'default',

    'block_spacings' => [
        'small' => 'Small',
        'default' => 'Default',
        'large' => 'Large',
    ],

    'default_header_logo_size' => 'md',
    'default_header_nav_size' => 'md',
    'default_header_nav_hover' => 'opacity',

    'element_sizes' => [
        'sm' => 'Small',
        'md' => 'Medium',
        'lg' => 'Large',
    ],

    'nav_hover_states' => [
        'opacity' => 'Fade',
        'underline' => 'Underline',
        'scale' => 'Grow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Header & footer layout variants
    |--------------------------------------------------------------------------
    |
    | Selectable layout skeletons for the public site header and footer,
    | rendered on the public site and mirrored in the admin preview.
    |
    */

    'default_header_layout' => 'simple',
    'default_footer_layout' => 'simple',

    'header_layouts' => [
        'simple' => ['label' => 'Simple',   'description' => 'Logo left, nav right'],
        'centered' => ['label' => 'Centered',  'description' => 'Logo centered, nav below'],
        'split' => ['label' => 'Split',     'description' => 'Logo left, nav center, CTA right'],
        'minimal' => ['label' => 'Minimal',   'description' => 'Logo only'],
    ],

    'footer_layouts' => [
        'simple' => ['label' => 'Simple',   'description' => 'Copyright left, links right'],
        'centered' => ['label' => 'Centered',  'description' => 'All content centered'],
        'columns' => ['label' => 'Columns',   'description' => 'Logo + tagline left, link columns right'],
        'minimal' => ['label' => 'Minimal',   'description' => 'Copyright only, centered'],
    ],

    'default_auth_layout' => 'simple',
    'default_auth_image_side' => 'left',

    'auth_layouts' => [
        'simple' => ['label' => 'Simple', 'description' => 'Centered form'],
        'card' => ['label' => 'Card',   'description' => 'Form inside a card'],
        'split' => ['label' => 'Split',  'description' => 'Form beside a full-height image'],
        'split-card' => ['label' => 'Split card', 'description' => 'Form and image inside a centered card'],
    ],

];
