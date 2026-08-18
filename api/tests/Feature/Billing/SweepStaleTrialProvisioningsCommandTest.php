<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Issue #4948 — SweepStaleTrialProvisioningsCommand : les trial provisionings
 * restés bloqués en `pending`/`provisioning_sandbox` (worker de queue jamais
 * exécuté, ex. Redis down) doivent passer en `failed` après le seuil de
 * tolérance, au lieu de rester indéfiniment en attente (poll prospect sans
 * état terminal, funnel KO).
 */
class SweepStaleTrialProvisioningsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function insertProvisioning(array $overrides = []): void
    {
        DB::table('trial_provisionings')->insert(array_merge([
            'email' => 'prospect@example.dz',
            'provisioning_token' => str_repeat('a', 64),
            'status' => 'pending',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ], $overrides));
    }

    public function test_stale_pending_provisioning_is_marked_failed(): void
    {
        $this->insertProvisioning();

        $command = $this->artisan('trial-provisionings:sweep');
        if ($command instanceof PendingCommand) {
            $command->assertExitCode(0);
        } else {
            self::assertSame(0, $command);
        }

        $this->assertDatabaseHas('trial_provisionings', [
            'email' => 'prospect@example.dz',
            'status' => 'failed',
        ]);
        $row = DB::table('trial_provisionings')->where('email', 'prospect@example.dz')->first();
        self::assertNotNull($row);
        $this->assertStringContainsString('SWEEP_TIMEOUT', (string) $row->error);
    }

    public function test_fresh_pending_provisioning_is_preserved(): void
    {
        $this->insertProvisioning(['updated_at' => now()->subMinutes(5)]);

        $command = $this->artisan('trial-provisionings:sweep');
        if ($command instanceof PendingCommand) {
            $command->assertExitCode(0);
        } else {
            self::assertSame(0, $command);
        }

        $this->assertDatabaseHas('trial_provisionings', [
            'email' => 'prospect@example.dz',
            'status' => 'pending',
        ]);
    }

    public function test_ready_and_failed_are_preserved(): void
    {
        $this->insertProvisioning(['status' => 'ready', 'provisioning_token' => str_repeat('b', 64)]);
        $this->insertProvisioning(['status' => 'failed', 'provisioning_token' => str_repeat('c', 64)]);

        $command = $this->artisan('trial-provisionings:sweep');
        if ($command instanceof PendingCommand) {
            $command->assertExitCode(0);
        } else {
            self::assertSame(0, $command);
        }

        $this->assertDatabaseHas('trial_provisionings', ['provisioning_token' => str_repeat('b', 64), 'status' => 'ready']);
        $this->assertDatabaseHas('trial_provisionings', ['provisioning_token' => str_repeat('c', 64), 'status' => 'failed']);
    }

    public function test_dry_run_does_not_write(): void
    {
        $this->insertProvisioning();

        $command = $this->artisan('trial-provisionings:sweep --dry-run');
        if ($command instanceof PendingCommand) {
            $command->assertExitCode(0);
        } else {
            self::assertSame(0, $command);
        }

        $this->assertDatabaseHas('trial_provisionings', [
            'email' => 'prospect@example.dz',
            'status' => 'pending',
        ]);
    }
}
