<?php

declare(strict_types=1);

use Tests\TestCase;

it('allows the suite to run on the sqlite connection', function (): void {
    expect(fn () => TestCase::assertTestDatabaseIsIsolated('sqlite'))
        ->not->toThrow(RuntimeException::class);
});

it('refuses to run the suite against a non-sqlite (real) database connection', function (): void {
    expect(fn () => TestCase::assertTestDatabaseIsIsolated('mysql'))
        ->toThrow(RuntimeException::class, 'Refusing to run the test suite');
});
