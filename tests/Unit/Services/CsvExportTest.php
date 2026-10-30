<?php

declare(strict_types=1);

use App\Services\CsvExport;

it('writes a csv that spreadsheets open as utf-8, quoting cells that need it', function (): void {
    $csv = new CsvExport()->build(['Name', 'Note'], [['Zoë', 'Says "hi", twice'], ['Bob', null]]);

    expect($csv)->toBe("\xEF\xBB\xBFName,Note\nZoë,\"Says \"\"hi\"\", twice\"\nBob,\n");
});

it('neutralises cells a spreadsheet would run as a formula but leaves numbers alone', function (): void {
    $export = new CsvExport();

    expect($export->cell('=HYPERLINK("https://evil.test")'))->toBe('\'=HYPERLINK("https://evil.test")')
        ->and($export->cell('@SUM(A1:A9)'))->toBe("'@SUM(A1:A9)")
        ->and($export->cell('+1 555 0100'))->toBe("'+1 555 0100")
        ->and($export->cell('-dash'))->toBe("'-dash")
        ->and($export->cell("\tcmd"))->toBe("'\tcmd")
        ->and($export->cell('-5.25'))->toBe('-5.25')
        ->and($export->cell(-3))->toBe('-3')
        ->and($export->cell(19.5))->toBe('19.5')
        ->and($export->cell('a = b'))->toBe('a = b')
        ->and($export->cell(null))->toBeEmpty();
});
