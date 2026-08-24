<?php

declare(strict_types=1);

return [

    'version_file' => storage_path('app/version'),

    'backup_path' => storage_path('app/backups'),

    'transfer_path' => storage_path('app/transfers'),

    'media_import_path' => storage_path('app/import'),

    'og_images' => filter_var(env('WIREUP_OG_IMAGES', true), FILTER_VALIDATE_BOOLEAN),

    'og_path' => storage_path('app/og'),

    'backups_to_keep' => 5,

    'draft_preview_days' => 7,

];
