<?php

declare(strict_types=1);

use App\Actions\ImportMediaFromUrlAction;
use App\Actions\ImportPexelsMediaAction;
use App\Mail\IntegrationTestMail;

arch()->preset()->php();
arch()->preset()->laravel()->ignoring(IntegrationTestMail::class);
arch()->preset()->security()->ignoring([ImportPexelsMediaAction::class, ImportMediaFromUrlAction::class]);
