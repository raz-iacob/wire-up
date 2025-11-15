<?php

declare(strict_types=1);

use App\Models\Page;

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
    ],
];
