<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Process;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        self::assertTestDatabaseIsIsolated((string) config('database.default'));
    }

    /**
     * Guard against the suite ever running on a real database. Tests must use
     * sqlite (see phpunit.xml); a stale config cache is the usual way the
     * connection silently becomes the dev MySQL database — in which case
     * RefreshDatabase would drop every table. Abort loudly instead.
     */
    public static function assertTestDatabaseIsIsolated(string $connection): void
    {
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            return;
        }

        $database = (string) config("database.connections.{$connection}.database");

        throw new RuntimeException(
            "Refusing to run the test suite against the '{$driver}' connection (database: '{$database}'). "
            .'Tests must run on sqlite — see phpunit.xml. This almost always means a stale config cache is '
            .'overriding the test connection. Run: php artisan config:clear'
        );
    }

    protected function actingAsAdmin(): self
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
            'active' => true,
            'role' => 'owner',
        ]);

        $this->actingAs($user);

        return $this;
    }
}
