<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Social platforms
    |--------------------------------------------------------------------------
    |
    | The social networks an admin can link to. Stored in the flat settings
    | store under `social` (key => url) and surfaced in the public footer.
    | Order here drives the order of the admin form fields. "icon" is the SVG
    | basename in resources/images/socials (variant suffix added at render).
    |
    */

    'default_icon_variant' => 'solid',

    'icon_variants' => [
        'solid' => 'Solid',
        'outline' => 'Outline',
    ],

    'platforms' => [
        'facebook' => ['label' => 'Facebook URL', 'placeholder' => 'https://facebook.com/yourpage', 'icon' => 'facebook'],
        'linkedin' => ['label' => 'LinkedIn URL', 'placeholder' => 'https://linkedin.com/company/yourcompany', 'icon' => 'linkedin'],
        'x' => ['label' => 'X (Twitter) URL', 'placeholder' => 'https://x.com/yourhandle', 'icon' => 'x-twitter'],
        'youtube' => ['label' => 'YouTube URL', 'placeholder' => 'https://youtube.com/@yourchannel', 'icon' => 'youtube'],
        'instagram' => ['label' => 'Instagram URL', 'placeholder' => 'https://instagram.com/yourhandle', 'icon' => 'instagram'],
        'tiktok' => ['label' => 'TikTok URL', 'placeholder' => 'https://tiktok.com/@yourhandle', 'icon' => 'tiktok'],
    ],

];
