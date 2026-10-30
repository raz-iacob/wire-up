<?php

declare(strict_types=1);

use App\Console\Commands\CheckForUpdatesCommand;
use App\Console\Commands\CleanTempUploadsCommand;
use App\Console\Commands\CreateBackupCommand;
use App\Console\Commands\PruneAbandonedOrdersCommand;
use App\Console\Commands\PruneImageCacheCommand;
use App\Console\Commands\ReconcileOrdersCommand;
use App\Models\Activity;
use App\Models\NotFoundPath;
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

Schedule::command('model:prune', ['--model' => [NotFoundPath::class, Activity::class]])
    ->daily()
    ->withoutOverlapping();

Schedule::command(CreateBackupCommand::class, ['--scheduled'])
    ->dailyAt('03:00')
    ->withoutOverlapping();
