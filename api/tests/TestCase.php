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

        $defaultConnection = config('database.default', 'pgsql');
        $connection = is_string($defaultConnection) && $defaultConnection !== ''
            ? $defaultConnection
            : 'pgsql';
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'pgsql') {
            return;
        }

        $host = $this->envValueForTesting(
            'DB_HOST',
            $this->configString("database.connections.{$connection}.host", '127.0.0.1')
        );

        if ($this->isRunningInsideDocker() && in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $host = 'pgsql';
        }

        config([
            "database.connections.{$connection}.host" => $host,
            "database.connections.{$connection}.port" => $this->envValueForTesting('DB_PORT', $this->configString("database.connections.{$connection}.port", '5432')),
            "database.connections.{$connection}.database" => $this->envValueForTesting('DB_DATABASE', $this->configString("database.connections.{$connection}.database", 'leopardo_test')),
            "database.connections.{$connection}.username" => $this->envValueForTesting('DB_USERNAME', $this->configString("database.connections.{$connection}.username", 'leopardo_user')),
            "database.connections.{$connection}.password" => $this->envValueForTesting('DB_PASSWORD', $this->configString("database.connections.{$connection}.password", 'leopardo_pass_test')),
            "database.connections.{$connection}.search_path" => $this->envValueForTesting('DB_SEARCH_PATH', $this->configString("database.connections.{$connection}.search_path", 'shared_tenants,public')),
        ]);

        DB::purge($connection);
        DB::reconnect($connection);
    }

    private function envValueForTesting(string $key, string $fallback): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        return $value;
    }

    private function configString(string $key, string $fallback): string
    {
        $value = config($key, $fallback);

        return is_string($value) && $value !== ''
            ? $value
            : $fallback;
    }

    private function isRunningInsideDocker(): bool
    {
        return file_exists('/.dockerenv');
    }
}
