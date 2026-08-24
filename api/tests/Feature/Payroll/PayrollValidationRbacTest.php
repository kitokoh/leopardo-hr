<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5246 — workflow de validation RBAC : la validation (vise), le
 * verrouillage (clôture) et le déverrouillage d'un run de paie sont réservés
 * aux managers `principal`/`comptable` — un `rh` peut PRÉPARER (calculer)
 * mais pas auto-valider ni clôturer (séparation des tâches, contrat
 * RBAC_ROUTE_MATRIX.md F-11/#1541).
 */
class PayrollValidationRbacTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $other;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->other = $other;
    }

    private function makeRun(string $status): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => $status,
        ]);

        return $run;
    }

    private function addPaySlip(PayrollRun $run): void
    {
        /** @var Employee $employee */
        /** @var Employee $employee */
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 60000,
            'total_deductions' => 12442,
            'net_salary' => 47558,
            'employer_contributions' => 15600,
            'total_cost' => 75600,
            'status' => 'calculated',
        ]);
    }

    public function test_rh_cannot_calculate_validate_lock_or_unlock(): void
    {
        // Issue #5246 — le workflow de paie est réservé aux managers
        // principal/comptable (route middleware `api.manager:principal,comptable`,
        // contrat RBAC_ROUTE_MATRIX.md) : un `rh` est refusé sur TOUTE la
        // chaîne (calcul, validation, clôture, déverrouillage) avec
        // INSUFFICIENT_ROLE — la séparation RH/comptable/principal est
        // appliquée au niveau route ET doublée dans le controller (défense
        // en profondeur, guards alignés en dur dans PayrollRunController).
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);
        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);

        Sanctum::actingAs($rh);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertStatus(403)
            ->assertJsonPath('error', 'INSUFFICIENT_ROLE');
        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")
            ->assertStatus(403)
            ->assertJsonPath('error', 'INSUFFICIENT_ROLE');
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")
            ->assertStatus(403)
            ->assertJsonPath('error', 'INSUFFICIENT_ROLE');
        $this->postJson("/api/v1/payroll-runs/{$run->id}/unlock", ['reason' => 'test'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'INSUFFICIENT_ROLE');
    }

    public function test_comptable_can_validate_and_lock(): void
    {
        // Comptable : vérification/vise (validate) puis clôture (lock) — le
        // flux F-11 « validation RH → clôture comptable » avec les vrais rôles.
        /** @var Employee $comptable */
        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $this->company->id]);
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);
        $this->addPaySlip($run);

        Sanctum::actingAs($comptable);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertStatus(200);
        $this->assertDatabaseHas('payroll_runs', ['id' => $run->id, 'status' => PayrollRun::STATUS_VALIDATED]);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")->assertStatus(200);
        $this->assertDatabaseHas('payroll_runs', ['id' => $run->id, 'status' => PayrollRun::STATUS_LOCKED]);
    }

    public function test_principal_can_validate_lock_and_unlock(): void
    {
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);
        $this->addPaySlip($run);

        Sanctum::actingAs($principal);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertStatus(200);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")->assertStatus(200);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/unlock", ['reason' => 'correction validée'])
            ->assertStatus(200);
        $this->assertDatabaseHas('payroll_runs', ['id' => $run->id, 'status' => PayrollRun::STATUS_VALIDATED]);
    }

    public function test_employee_cannot_validate(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")
            ->assertStatus(403)
            ->assertJsonPath('error', 'MANAGER_REQUIRED');
    }

    public function test_cross_tenant_comptable_is_rejected_with_404(): void
    {
        // Isolation tenant : un comptable d'une AUTRE société ne voit pas le
        // run (404, pas 403) — aucune fuite cross-tenant (constitution §II).
        /** @var Employee $foreignComptable */
        $foreignComptable = Employee::factory()->managerComptable()->create(['company_id' => $this->other->id]);
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);

        Sanctum::actingAs($foreignComptable);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertStatus(404);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")->assertStatus(404);
    }

    public function test_validation_workflow_writes_audit_trail(): void
    {
        // « Aucune exécution sans approbation tracée » (DoD #5246) : chaque
        // étape sensible écrit une entrée d'audit (qui, quoi, quand).
        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $this->company->id]);
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);
        $this->addPaySlip($run);

        Sanctum::actingAs($comptable);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertStatus(200);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")->assertStatus(200);

        $events = DB::table('audit_logs')
            ->where('company_id', $this->company->id)
            ->where('auditable_type', PayrollRun::class)
            ->where('auditable_id', $run->id)
            ->pluck('action')
            ->all();

        $this->assertContains('payroll_run_validated', $events, 'Événement d\'audit validate manquant');
        $this->assertContains('payroll_run_locked', $events, 'Événement d\'audit lock manquant');
    }
}
