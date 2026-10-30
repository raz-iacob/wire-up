<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RedirectOrigin;
use App\Models\Redirect;
use App\Services\RedirectService;
use Illuminate\Support\Facades\DB;

final readonly class ImportRedirectsAction
{
    /**
     * @var array<int, string>
     */
    private const array HEADINGS = ['from', 'source', 'old url', 'old address'];

    /**
     * @param  array<int, array{from: string, to: string, status: int|null}>  $rows
     * @return array{added: int, updated: int, skipped: array<int, array{row: int, from: string, reason: string}>}
     */
    public function handle(array $rows, RedirectOrigin $origin): array
    {
        $save = new SaveRedirectAction();
        $summary = ['added' => 0, 'updated' => 0, 'skipped' => []];

        DB::transaction(function () use ($rows, $origin, $save, &$summary): void {
            foreach ($rows as $row => ['from' => $from, 'to' => $to, 'status' => $status]) {
                $problems = $from === '' || $to === ''
                    ? ['from' => __('Both the old and the new address are needed.')]
                    : $save->problems($from, $to);

                if ($problems !== []) {
                    $summary['skipped'][] = ['row' => $row, 'from' => $from, 'reason' => (string) reset($problems)];

                    continue;
                }

                $exists = Redirect::query()->where('from', RedirectService::normalizeFrom($from))->exists();

                $save->handle($from, $to, in_array($status, [301, 302], true) ? $status : 301, $origin);

                $exists ? $summary['updated']++ : $summary['added']++;
            }
        });

        return $summary;
    }

    /**
     * @return array<int, array{from: string, to: string, status: int|null}>
     */
    public function csvRows(string $path): array
    {
        $contents = (string) file_get_contents($path);
        $contents = str_starts_with($contents, "\xEF\xBB\xBF") ? mb_substr($contents, 1) : $contents;
        $firstLine = strtok($contents, "\n");
        $delimiter = mb_substr_count((string) $firstLine, ';') > mb_substr_count((string) $firstLine, ',') ? ';' : ',';

        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $index => $line) {
            if (mb_trim($line) === '') {
                continue;
            }

            $cells = array_map(fn (?string $cell): string => mb_trim((string) $cell), str_getcsv($line, $delimiter, '"', ''));

            if ($index === 0 && in_array(mb_strtolower($cells[0]), self::HEADINGS, true)) {
                continue;
            }

            $rows[$index + 1] = [
                'from' => $cells[0],
                'to' => $cells[1] ?? '',
                'status' => isset($cells[2]) && $cells[2] !== '' ? (int) $cells[2] : null,
            ];
        }

        return $rows;
    }
}
