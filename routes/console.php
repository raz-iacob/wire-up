<?php

declare(strict_types=1);

use App\Console\Commands\CheckForUpdatesCommand;
use App\Console\Commands\CleanTempUploadsCommand;
use App\Console\Commands\PruneImageCacheCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CleanTempUploadsCommand::class)
    ->daily()
    ->withoutOverlapping();

Schedule::command(PruneImageCacheCommand::class)
    ->daily()
    ->withoutOverlapping();

Schedule::command(CheckForUpdatesCommand::class)
    ->daily()
    ->withoutOverlapping();
