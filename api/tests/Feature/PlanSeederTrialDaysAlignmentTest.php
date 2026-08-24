<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3851 — résiduel #3496/#3164 : PlanSeeder hardcodait `trial_days = 30`
 * pour tous les plans. Depuis l'ADR-0014 (2026-08-15, accepté), la durée
 * d'essai est différenciée :
 *   - Free (freemium)      → 30 jours (avantage distinctif, seeder)
 *   - Pilot/Operations/etc → config('billing.trial_days') = 14 jours
 * Cette garde verrouille l'alignement : les plans PAYANTS doivent lire la
 * constante billing (lue par TrialWelcomeMail, CompanyProvisioningService,
 * PlatformPlanController), et le plan Free doit rester à 30 jours — une
 * réintroduction de 30 jours côté payants fait échouer la CI.
 */
class PlanSeederTrialDaysAlignmentTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_plans_seed_trial_days_aligned_on_billing_config(): void
    {
        DB::statement('SET search_path TO public');

        $this->artisan('db:seed', ['--class' => PlanSeeder::class])
            ->assertSuccessful();

        $expected = (int) config('billing.trial_days');
        $this->assertGreaterThan(0, $expected, 'config(billing.trial_days) doit être > 0');

        $plans = DB::table('plans')->get(['name', 'trial_days']);

        $this->assertNotEmpty($plans, 'PlanSeeder doit créer au moins un plan');
        foreach ($plans as $plan) {
            if ($plan->name === 'Free') {
                // ADR-0014 : le plan Free garde 30 jours (levier freemium).
                $this->assertSame(
                    30,
                    (int) $plan->trial_days,
                    "plan Free : trial_days ({$plan->trial_days}) doit rester 30 (ADR-0014)"
                );

                continue;
            }

            $this->assertSame(
                $expected,
                (int) $plan->trial_days,
                "plan {$plan->name} : trial_days ({$plan->trial_days}) doit être aligné sur config('billing.trial_days') ({$expected})"
            );
        }
    }
}
