<?php

declare(strict_types=1);

return [
    /*
     * Slug prefixes that may not be used by a record type because they collide
     * with existing application routes. Active locale codes are merged in at runtime.
     */
    'reserved_prefixes' => [
        'admin',
        'login',
        'logout',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
        'img',
        'robots.txt',
        'sitemap.xml',
        'llms.txt',
        'llms-full.txt',
        'category',
    ],

    /*
     * Field keys reserved by the record editor itself; a custom field may not use them.
     */
    'reserved_field_keys' => [
        'title',
        'description',
        'slug',
        'status',
        'published_at',
        'og_image',
    ],
];
