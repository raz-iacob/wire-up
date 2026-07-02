<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;

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
        'record' => Record::class,
        'category' => Category::class,
    ],
];
