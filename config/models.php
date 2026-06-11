<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;

return [
    /*
    |--------------------------------------------------------------------------
    | Morph Maps
    |--------------------------------------------------------------------------
    |
    | The config for the morph Map relations
    |
    */

    'map' => [
        'page' => Page::class,
        'media' => Media::class,
        'settings' => Settings::class,
    ],
];
