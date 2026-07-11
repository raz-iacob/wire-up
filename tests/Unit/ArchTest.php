<?php

declare(strict_types=1);

use App\Actions\ImportMediaFromUrlAction;
use App\Actions\ImportPexelsMediaAction;

arch()->preset()->php();
arch()->preset()->laravel();
arch()->preset()->security()->ignoring([ImportPexelsMediaAction::class, ImportMediaFromUrlAction::class]);
