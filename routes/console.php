<?php

declare(strict_types=1);

use App\Console\Commands\CheckForUpdatesCommand;
use App\Console\Commands\CleanTempUploadsCommand;
use App\Console\Commands\PruneAbandonedOrdersCommand;
use App\Console\Commands\PruneImageCacheCommand;
use App\Console\Commands\ReconcileOrdersCommand;
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

Schedule::command(ReconcileOrdersCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(PruneAbandonedOrdersCommand::class)
    ->daily()
    ->withoutOverlapping();
