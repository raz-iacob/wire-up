<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Playwright;
use Tests\Support\FakeStripe;
use Tests\TestCase;

throw_if(file_exists(__DIR__.'/../bootstrap/cache/config.php'), RuntimeException::class, 'bootstrap/cache/config.php exists, which overrides the sqlite test connection and points the suite '
.'at your real database — a parallel run would drop every table in it. Run: php artisan config:clear');

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Validator::fakeDnsLookups();
        Sleep::fake();
        $this->app->instance(FakeStripe::class, FakeStripe::install());

        config(['media.cache_path' => storage_path('framework/images/test-'.(ParallelTesting::token() ?: 'single'))]);

        $this->freezeTime();
    })
    ->in('Browser', 'Console', 'Feature', 'Unit');

pest()->beforeEach(function (): void {
    Playwright::setTimeout(ParallelTesting::token() ? 60_000 : 15_000);
})->in('Browser');

function stripe(): FakeStripe
{
    return resolve(FakeStripe::class);
}

/**
 * @param  array<string, mixed>  $object
 */
function stripeWebhook(string $type, array $object, ?string $secret = null): TestResponse
{
    $payload = (string) json_encode(['id' => 'evt_'.Str::random(14), 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret ?? config()->string('cashier.webhook.secret'));

    return test()->call('POST', route('cashier.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
    ], content: $payload);
}

function connectStripe(): void
{
    config(['cashier.key' => 'pk_test_1', 'cashier.secret' => 'sk_test_1', 'cashier.webhook.secret' => 'whsec_1']);
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $attributes
 */
function sellableRecord(array $data = ['current_price' => '19.99'], array $attributes = [], bool $sellable = true): Record
{
    $type = $sellable ? RecordType::factory()->sellable()->create() : RecordType::factory()->create();

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'data' => $data,
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    $record->slugs()->create(['locale' => config()->string('app.default_locale', 'en'), 'slug' => 'item-'.$record->id, 'base_path' => $type->slug_prefix]);

    return $record;
}

expect()->extend('toBeOne', fn () => $this->toBe(1));

function assertScriptEventually(PendingAwaitablePage $browser, string $expression, mixed $expected, float $seconds = 15.0): void
{
    $deadline = microtime(true) + $seconds;

    while (microtime(true) < $deadline && $browser->script($expression) !== $expected) {
        $browser->wait(0.1);
    }

    $browser->assertScript($expression, $expected);
}

function something(): void
{
    //
}

function unreachableDatabase(): QueryException
{
    return new QueryException(
        'sqlite',
        'select 1',
        [],
        new SQLiteDatabaseDoesNotExistException('/missing/database.sqlite'),
    );
}

/**
 * @param  array<string, array<string, array<int, array<string, mixed>>>>  $menus  keyed by menu key => [locale => items]
 * @return array<int, array{key: string, name: string, builtin: bool, items: array<string, array<int, array<string, mixed>>>}>
 */
function menusPayload(array $menus): array
{
    $payload = [];

    foreach ($menus as $key => $items) {
        $payload[] = [
            'key' => $key,
            'name' => ucfirst($key),
            'builtin' => in_array($key, ['header', 'footer'], true),
            'items' => $items,
        ];
    }

    return $payload;
}
