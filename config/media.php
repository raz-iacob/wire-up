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

];
