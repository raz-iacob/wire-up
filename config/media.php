<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Maximum Upload Size
    |---------------------------------------------------------------------------
    |
    | An optional hard ceiling (in kilobytes) for media uploads. Set this to
    | match a lower limit imposed by a reverse proxy or web server (e.g. nginx
    | client_max_body_size) that PHP cannot detect on its own. When null, the
    | limit is derived from PHP's upload_max_filesize / post_max_size.
    |
    */

    'max_upload_kilobytes' => env('MEDIA_MAX_UPLOAD_KILOBYTES'),

    /*
    |---------------------------------------------------------------------------
    | Transform Cache Location
    |---------------------------------------------------------------------------
    |
    | Absolute path where transformed image variants are cached. The test suite
    | overrides this per parallel worker so runs stay isolated on disk.
    |
    */

    'cache_path' => storage_path('framework/images'),

    /*
    |---------------------------------------------------------------------------
    | Transform Cache Size
    |---------------------------------------------------------------------------
    |
    | Transformed image variants are cached under the path above so repeat
    | requests skip the resize work. The daily prune keeps the cache under this
    | many megabytes by deleting the least recently used variants.
    |
    */

    'transform_cache_max_megabytes' => (int) env('MEDIA_TRANSFORM_CACHE_MAX_MEGABYTES', 512),

];
