<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTestingDatabaseConnection();
        $this->resetTestSearchPath();
    }

    protected function tearDown(): void
    {
        $this->resetTestSearchPath();
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function resetTestSearchPath(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SET search_path TO shared_tenants,public');
    }

    private function configureTestingDatabaseConnection(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $connection = config('database.default', 'pgsql');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'pgsql') {
            return;
        }

        $host = $this->testEnvValue('DB_HOST', (string) config("database.connections.{$connection}.host", '127.0.0.1'));

        if ($this->isRunningInsideDocker() && in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $host = 'pgsql';
        }

        config([
            "database.connections.{$connection}.host" => $host,
            "database.connections.{$connection}.port" => $this->testEnvValue('DB_PORT', (string) config("database.connections.{$connection}.port", '5432')),
            "database.connections.{$connection}.database" => $this->testEnvValue('DB_DATABASE', (string) config("database.connections.{$connection}.database", 'leopardo_test')),
            "database.connections.{$connection}.username" => $this->testEnvValue('DB_USERNAME', (string) config("database.connections.{$connection}.username", 'leopardo_user')),
            "database.connections.{$connection}.password" => $this->testEnvValue('DB_PASSWORD', (string) config("database.connections.{$connection}.password", 'leopardo_pass_test')),
            "database.connections.{$connection}.search_path" => $this->testEnvValue('DB_SEARCH_PATH', (string) config("database.connections.{$connection}.search_path", 'shared_tenants,public')),
        ]);

        DB::purge($connection);
        DB::reconnect($connection);
    }

    private function testEnvValue(string $key, string $fallback): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $fallback;
        }

        return (string) $value;
    }

    private function isRunningInsideDocker(): bool
    {
        return file_exists('/.dockerenv');
    }
}
