<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExport
{
    private const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $csv = $this->build($headers, $rows);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public function build(array $headers, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+b') ?: throw new RuntimeException('Could not open a temporary stream for the CSV export.');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map($this->cell(...), $headers), escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map($this->cell(...), $row), escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function cell(string|int|float|null $value): string
    {
        $text = (string) $value;

        if ($text === '' || is_numeric($text) || ! in_array($text[0], self::FORMULA_TRIGGERS, true)) {
            return $text;
        }

        return "'".$text;
    }
}
