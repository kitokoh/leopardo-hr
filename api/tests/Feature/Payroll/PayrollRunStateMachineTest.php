<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2221 — machine à états du run de paie.
 *
 * 1. Un échec de calcul ne laisse JAMAIS le run bloqué en `calculating`
 *    (statut restauré `draft` → recalcul possible).
 * 2. Le paiement groupé exige une validation RH (plus de `calculated`).
 * 3. Les métadonnées de règles (rules_version/identifier/period) sont
 *    persistées sur le run après calcul (promesse #1871).
 */
class PayrollRunStateMachineTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    private function makeRun(string $status, string $countryCode = 'DZ'): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $countryCode,
            'status' => $status,
        ]);

        return $run;
    }

    public function test_calculate_failure_restores_draft_status(): void
    {
        // Pays sans règles enregistrées → calculateRun() lève une exception.
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED, 'ZZ');

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertStatus(422);

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        // Le run reste recalculable (garde [draft, calculated] satisfaite).
        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertStatus(422); // re-échec, mais PAS de blocage permanent
    }

    public function test_bulk_pay_rejects_unvalidated_run(): void
    {
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Le run doit être validé (ou verrouillé) avant le paiement en masse.');

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => PayrollRun::STATUS_CALCULATED,
        ]);
    }

    public function test_bulk_pay_accepts_validated_run(): void
    {
        $run = $this->makeRun(PayrollRun::STATUS_VALIDATED);

        Sanctum::actingAs($this->manager);

        // Le run validé est accepté par la garde (le traitement effectif est
        // dispatché en job async → 202 Accepted).
        $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay")
            ->assertStatus(202);
    }

    public function test_calculate_persists_rules_metadata_on_run(): void
    {
        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);

        SalaryStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Grille par défaut (test)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertOk();

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => PayrollRun::STATUS_CALCULATED,
            'rules_identifier' => 'AlgeriaPayrollRules',
        ]);

        /** @var PayrollRun $fresh */
        $fresh = PayrollRun::query()->findOrFail($run->id);
        $this->assertNotNull($fresh->rules_version);
        $this->assertSame('2026-07-01', $fresh->rules_period?->toDateString());
    }
}
