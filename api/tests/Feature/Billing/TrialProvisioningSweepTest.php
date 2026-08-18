<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Jobs\ProvisionDemoTenantJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4948 — sweeper `trial:provisioning-sweep`.
 *
 * Couvre les 3 branches du self-healing des provisionings restés `pending`
 * (worker de queue down, épic #3765/#3766) :
 *  1. ligne reconstructible → re-dispatch borné du job (attempts + 1) ;
 *  2. attempts épuisés → passage en `failed` + aucun re-dispatch ;
 *  3. ligne antérieure à la migration 000001 (company_name/country absents)
 *     → passage en `failed` sans re-dispatch.
 *
 * `trial_provisionings` vit dans le schéma `public` — RefreshTenantDatabase
 * couvre ce cas (mêmes prérequis que TrialSignupSlugRaceTest).
 */
class TrialProvisioningSweepTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @param array<string, mixed> $overrides */
    private function insertStalledRow(array $overrides = []): int
    {
        return DB::table('trial_provisionings')->insertGetId(array_merge([
            'email' => 'prospect@example.com',
            'provisioning_token' => 'tok_'.str()->random(32),
            'company_name' => 'Acme Test SARL',
            'country' => 'DZ',
            'attempts' => 0,
            'status' => 'pending',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ], $overrides));
    }

    private function assertSweepCommand(string $command = 'trial:provisioning-sweep'): void
    {
        $result = $this->artisan($command);
        if ($result instanceof PendingCommand) {
            $result->assertExitCode(0);
        } else {
            self::assertSame(0, $result);
        }
    }

    public function test_stalled_pending_row_is_redispatched_with_original_arguments(): void
    {
        Queue::fake();
        $token = 'tok_redispatched';
        $this->insertStalledRow(['provisioning_token' => $token]);

        $this->assertSweepCommand();

        Queue::assertPushed(ProvisionDemoTenantJob::class, function (ProvisionDemoTenantJob $job) use ($token): bool {
            return $job->email === 'prospect@example.com'
                && $job->companyName === 'Acme Test SARL'
                && $job->country === 'DZ'
                && $job->provisioningToken === $token;
        });

        $this->assertSame(
            1,
            (int) DB::table('trial_provisionings')->where('provisioning_token', $token)->value('attempts'),
            'attempts doit être incrémenté à 1 après le premier re-dispatch.',
        );
        $this->assertSame('pending', DB::table('trial_provisionings')->where('provisioning_token', $token)->value('status'));
    }

    public function test_row_after_max_attempts_is_failed_and_not_redispatched(): void
    {
        Queue::fake();
        $token = 'tok_exhausted';
        $this->insertStalledRow(['provisioning_token' => $token, 'attempts' => 3]);

        $this->assertSweepCommand();

        Queue::assertNotPushed(ProvisionDemoTenantJob::class);

        $row = DB::table('trial_provisionings')->where('provisioning_token', $token)->first();
        self::assertNotNull($row);
        $this->assertSame('failed', $row->status);
        $this->assertSame('sweeper:worker_stalled_after_3_attempts', $row->error);
    }

    public function test_row_without_recovery_columns_is_failed_without_redispatch(): void
    {
        Queue::fake();
        $token = 'tok_legacy';
        // Ligne « antérieure » à la migration 000001 : company_name/country NULL.
        $this->insertStalledRow(['provisioning_token' => $token, 'company_name' => null, 'country' => null]);

        $this->assertSweepCommand();

        Queue::assertNotPushed(ProvisionDemoTenantJob::class);

        $row = DB::table('trial_provisionings')->where('provisioning_token', $token)->first();
        self::assertNotNull($row);
        $this->assertSame('failed', $row->status);
        $this->assertSame('sweeper:company_context_missing', $row->error);
    }

    public function test_fresh_pending_row_is_left_untouched(): void
    {
        Queue::fake();
        $token = 'tok_fresh';
        $this->insertStalledRow(['provisioning_token' => $token, 'created_at' => now()->subMinutes(5)]);

        $this->assertSweepCommand();

        Queue::assertNotPushed(ProvisionDemoTenantJob::class);
        $this->assertSame('pending', DB::table('trial_provisionings')->where('provisioning_token', $token)->value('status'));
    }
}
