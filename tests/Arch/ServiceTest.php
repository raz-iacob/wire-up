<?php

declare(strict_types=1);

arch('services')
    ->expect('App\Services')
    ->toHaveSuffix('Service');
