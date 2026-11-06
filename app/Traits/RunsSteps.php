<?php

declare(strict_types=1);

namespace App\Traits;

use Closure;
use Illuminate\Support\Facades\Process;
use Throwable;

trait RunsSteps
{
    /**
     * @param  list<string>|Closure(): mixed  $step
     * @return array{bool, string}
     */
    private function performStep(array|Closure $step): array
    {
        if ($step instanceof Closure) {
            try {
                $step();

                return [true, ''];
            } catch (Throwable $throwable) {
                return [false, $throwable->getMessage()];
            }
        }

        $result = Process::path(base_path())->timeout(600)->run($step);

        return [$result->successful(), mb_trim($result->output()."\n".$result->errorOutput())];
    }
}
