<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Social platforms
    |--------------------------------------------------------------------------
    |
    | The social networks an admin can link to. Stored on the singleton
    | Settings under metadata.social (key => url) and surfaced in the public
    | footer. Order here drives the order of the admin form fields.
    |
    */

    'platforms' => [
        'facebook' => ['label' => 'Facebook URL', 'placeholder' => 'https://facebook.com/yourpage'],
        'linkedin' => ['label' => 'LinkedIn URL', 'placeholder' => 'https://linkedin.com/company/yourcompany'],
        'x' => ['label' => 'X (Twitter) URL', 'placeholder' => 'https://x.com/yourhandle'],
        'youtube' => ['label' => 'YouTube URL', 'placeholder' => 'https://youtube.com/@yourchannel'],
        'instagram' => ['label' => 'Instagram URL', 'placeholder' => 'https://instagram.com/yourhandle'],
        'tiktok' => ['label' => 'TikTok URL', 'placeholder' => 'https://tiktok.com/@yourhandle'],
    ],

];
