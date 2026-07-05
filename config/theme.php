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
        'header_bg' => ['label' => 'Background', 'group' => 'Header'],
        'header_text' => ['label' => 'Text', 'group' => 'Header'],
        'footer_bg' => ['label' => 'Background', 'group' => 'Footer'],
        'footer_text' => ['label' => 'Text', 'group' => 'Footer'],
    ],

    'presets' => [
        'default' => ['label' => 'Default', 'colors' => [
            'background' => '#ffffff', 'text' => '#18181b', 'muted' => '#71717a',
            'card_bg' => '#f4f4f5', 'card_text' => '#18181b', 'card_border' => '#e4e4e7', 'divider' => '#e4e4e7',
            'input_bg' => '#ffffff', 'input_text' => '#18181b', 'input_border' => '#d4d4d8',
            'header_bg' => '#ffffff', 'header_text' => '#18181b', 'footer_bg' => '#f4f4f5', 'footer_text' => '#3f3f46',
            'primary_bg' => '#18181b', 'primary_text' => '#ffffff', 'secondary_bg' => '#e4e4e7', 'secondary_text' => '#18181b', 'primary_border' => '#18181b', 'accent' => '#18181b', 'secondary_border' => '#e4e4e7',
        ]],
        'slate' => ['label' => 'Slate', 'colors' => [
            'background' => '#f8fafc', 'text' => '#0f172a', 'muted' => '#64748b',
            'card_bg' => '#ffffff', 'card_text' => '#0f172a', 'card_border' => '#e2e8f0', 'divider' => '#e2e8f0',
            'input_bg' => '#ffffff', 'input_text' => '#0f172a', 'input_border' => '#cbd5e1',
            'header_bg' => '#ffffff', 'header_text' => '#0f172a', 'footer_bg' => '#0f172a', 'footer_text' => '#cbd5e1',
            'primary_bg' => '#2563eb', 'primary_text' => '#ffffff', 'secondary_bg' => '#e2e8f0', 'secondary_text' => '#0f172a', 'primary_border' => '#2563eb', 'accent' => '#2563eb', 'secondary_border' => '#e2e8f0',
        ]],
        'midnight' => ['label' => 'Midnight', 'colors' => [
            'background' => '#0a0a0a', 'text' => '#fafafa', 'muted' => '#a1a1aa',
            'card_bg' => '#18181b', 'card_text' => '#fafafa', 'card_border' => '#27272a', 'divider' => '#27272a',
            'input_bg' => '#18181b', 'input_text' => '#fafafa', 'input_border' => '#3f3f46',
            'header_bg' => '#0a0a0a', 'header_text' => '#fafafa', 'footer_bg' => '#000000', 'footer_text' => '#a1a1aa',
            'primary_bg' => '#6366f1', 'primary_text' => '#ffffff', 'secondary_bg' => '#27272a', 'secondary_text' => '#fafafa', 'primary_border' => '#6366f1', 'accent' => '#6366f1', 'secondary_border' => '#27272a',
        ]],
        'ocean' => ['label' => 'Ocean', 'colors' => [
            'background' => '#f0f9ff', 'text' => '#0c4a6e', 'muted' => '#0369a1',
            'card_bg' => '#ffffff', 'card_text' => '#0c4a6e', 'card_border' => '#bae6fd', 'divider' => '#bae6fd',
            'input_bg' => '#ffffff', 'input_text' => '#0c4a6e', 'input_border' => '#7dd3fc',
            'header_bg' => '#0c4a6e', 'header_text' => '#e0f2fe', 'footer_bg' => '#082f49', 'footer_text' => '#bae6fd',
            'primary_bg' => '#0ea5e9', 'primary_text' => '#ffffff', 'secondary_bg' => '#e0f2fe', 'secondary_text' => '#0c4a6e', 'primary_border' => '#0ea5e9', 'accent' => '#0ea5e9', 'secondary_border' => '#e0f2fe',
        ]],
        'forest' => ['label' => 'Forest', 'colors' => [
            'background' => '#f0fdf4', 'text' => '#14532d', 'muted' => '#15803d',
            'card_bg' => '#ffffff', 'card_text' => '#14532d', 'card_border' => '#bbf7d0', 'divider' => '#bbf7d0',
            'input_bg' => '#ffffff', 'input_text' => '#14532d', 'input_border' => '#86efac',
            'header_bg' => '#14532d', 'header_text' => '#dcfce7', 'footer_bg' => '#052e16', 'footer_text' => '#bbf7d0',
            'primary_bg' => '#16a34a', 'primary_text' => '#ffffff', 'secondary_bg' => '#dcfce7', 'secondary_text' => '#14532d', 'primary_border' => '#16a34a', 'accent' => '#16a34a', 'secondary_border' => '#dcfce7',
        ]],
        'sunset' => ['label' => 'Sunset', 'colors' => [
            'background' => '#fff7ed', 'text' => '#7c2d12', 'muted' => '#c2410c',
            'card_bg' => '#ffffff', 'card_text' => '#7c2d12', 'card_border' => '#fed7aa', 'divider' => '#fed7aa',
            'input_bg' => '#ffffff', 'input_text' => '#7c2d12', 'input_border' => '#fdba74',
            'header_bg' => '#7c2d12', 'header_text' => '#ffedd5', 'footer_bg' => '#431407', 'footer_text' => '#fed7aa',
            'primary_bg' => '#ea580c', 'primary_text' => '#ffffff', 'secondary_bg' => '#ffedd5', 'secondary_text' => '#7c2d12', 'primary_border' => '#ea580c', 'accent' => '#ea580c', 'secondary_border' => '#ffedd5',
        ]],
        'rose' => ['label' => 'Rose', 'colors' => [
            'background' => '#fff1f2', 'text' => '#881337', 'muted' => '#be123c',
            'card_bg' => '#ffffff', 'card_text' => '#881337', 'card_border' => '#fecdd3', 'divider' => '#fecdd3',
            'input_bg' => '#ffffff', 'input_text' => '#881337', 'input_border' => '#fda4af',
            'header_bg' => '#881337', 'header_text' => '#ffe4e6', 'footer_bg' => '#4c0519', 'footer_text' => '#fecdd3',
            'primary_bg' => '#e11d48', 'primary_text' => '#ffffff', 'secondary_bg' => '#ffe4e6', 'secondary_text' => '#881337', 'primary_border' => '#e11d48', 'accent' => '#e11d48', 'secondary_border' => '#ffe4e6',
        ]],
        'royal' => ['label' => 'Royal', 'colors' => [
            'background' => '#faf5ff', 'text' => '#3b0764', 'muted' => '#7e22ce',
            'card_bg' => '#ffffff', 'card_text' => '#3b0764', 'card_border' => '#e9d5ff', 'divider' => '#e9d5ff',
            'input_bg' => '#ffffff', 'input_text' => '#3b0764', 'input_border' => '#d8b4fe',
            'header_bg' => '#3b0764', 'header_text' => '#f3e8ff', 'footer_bg' => '#2e1065', 'footer_text' => '#e9d5ff',
            'primary_bg' => '#9333ea', 'primary_text' => '#ffffff', 'secondary_bg' => '#f3e8ff', 'secondary_text' => '#3b0764', 'primary_border' => '#9333ea', 'accent' => '#9333ea', 'secondary_border' => '#f3e8ff',
        ]],
        'mono' => ['label' => 'Mono', 'colors' => [
            'background' => '#ffffff', 'text' => '#000000', 'muted' => '#525252',
            'card_bg' => '#fafafa', 'card_text' => '#000000', 'card_border' => '#d4d4d4', 'divider' => '#d4d4d4',
            'input_bg' => '#ffffff', 'input_text' => '#000000', 'input_border' => '#a3a3a3',
            'header_bg' => '#000000', 'header_text' => '#ffffff', 'footer_bg' => '#000000', 'footer_text' => '#d4d4d4',
            'primary_bg' => '#000000', 'primary_text' => '#ffffff', 'secondary_bg' => '#e5e5e5', 'secondary_text' => '#000000', 'primary_border' => '#000000', 'accent' => '#000000', 'secondary_border' => '#e5e5e5',
        ]],
        'sand' => ['label' => 'Sand', 'colors' => [
            'background' => '#fafaf9', 'text' => '#292524', 'muted' => '#78716c',
            'card_bg' => '#ffffff', 'card_text' => '#292524', 'card_border' => '#e7e5e4', 'divider' => '#e7e5e4',
            'input_bg' => '#ffffff', 'input_text' => '#292524', 'input_border' => '#d6d3d1',
            'header_bg' => '#ffffff', 'header_text' => '#292524', 'footer_bg' => '#292524', 'footer_text' => '#d6d3d1',
            'primary_bg' => '#ca8a04', 'primary_text' => '#ffffff', 'secondary_bg' => '#e7e5e4', 'secondary_text' => '#292524', 'primary_border' => '#ca8a04', 'accent' => '#ca8a04', 'secondary_border' => '#e7e5e4',
        ]],
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
    | Selectable layout skeletons for the public site header and footer.
    | Preview-first: these drive the admin preview only for now.
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

    'auth_image_layouts' => ['split', 'split-card'],

];
