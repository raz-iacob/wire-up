<?php

declare(strict_types=1);

use App\Console\Commands\CleanTempUploadsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CleanTempUploadsCommand::class)
    ->daily()
    ->withoutOverlapping();
