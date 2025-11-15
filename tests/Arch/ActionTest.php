<?php

declare(strict_types=1);

arch('actions')
    ->expect('App\Actions')
    ->toHaveMethod('handle')
    ->toHaveSuffix('Action');
