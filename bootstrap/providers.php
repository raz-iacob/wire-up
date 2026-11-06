<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\IntegrationServiceProvider::class,
    Laravel\Nightwatch\NightwatchServiceProvider::class,
    Sentry\Laravel\ServiceProvider::class,
];
