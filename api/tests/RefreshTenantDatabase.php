<?php

namespace Tests;

use Illuminate\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait RefreshTenantDatabase
{
    use RefreshDatabase;

    /**
     * Refresh the in-memory database.
     */
    protected function refreshInMemoryDatabase(): void
    {
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations/public',
        ]);

        $this->artisan('migrate', [
            '--path' => 'database/migrations/tenant',
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh the test database.
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', [
                '--path' => 'database/migrations/public',
            ]);

            $this->artisan('migrate', [
                '--path' => 'database/migrations/tenant',
            ]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}