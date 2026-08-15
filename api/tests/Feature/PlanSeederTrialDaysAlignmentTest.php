<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3851 — résiduel #3496/#3164 : PlanSeeder hardcodait `trial_days = 30`
 * alors que le funnel vitrine et `config('billing.trial_days')` annoncent 14
 * jours (décision propriétaire 594c68f2). Cette garde verrouille l'alignement
 * entre la ligne `plans.trial_days` (lue par TrialWelcomeMail,
 * CompanyProvisioningService, PlatformPlanController) et la constante
 * billing — pour qu'une réintroduction de 30 jours fasse échouer la CI.
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
            $this->assertSame(
                $expected,
                (int) $plan->trial_days,
                "plan {$plan->name} : trial_days ({$plan->trial_days}) doit être aligné sur config('billing.trial_days') ({$expected})"
            );
        }
    }
}
