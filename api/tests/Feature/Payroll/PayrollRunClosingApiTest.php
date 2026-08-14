<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-11 (#1541) : workflow de clôture de paie exposé via l'API.
 *
 * Couvre les endpoints :
 *   POST /payroll-runs/{run}/validate  (étape 1 — validation RH, audit trail)
 *   POST /payroll-runs/{run}/lock      (étape 2 — clôture comptable, verrouillage)
 *   POST /payroll-runs/{run}/unlock    (déverrouillage motivé, tracé)
 *   POST /payroll-runs/{run}/cancel    (refusé après verrouillage)
 *
 * + isolation tenant, RBAC manager (principal/comptable), audit trail complet.
 */
class PayrollRunClosingApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $otherCompanyManager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $other->id]);
        $this->otherCompanyManager = $otherManager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
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

    /**
     * Issue #1767 : un run sans bulletin ne peut plus être validé/verrouillé —
     * le workflow de clôture doit donc disposer d'au moins un bulletin.
     */
    private function addPaySlip(PayrollRun $run, Employee $employee): void
    {
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
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'calculated',
        ]);
    }

    public function test_full_closing_workflow_via_api(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);
        $this->addPaySlip($run, $this->employee);

        // Étape 1 : validation RH → audit trail.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_VALIDATED);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'payroll_run_validated',
            'auditable_type' => $run->getMorphClass(),
            'auditable_id' => $run->id,
        ]);

        // Étape 2 : verrouillage comptable → audit trail + métadonnées.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_LOCKED)
            ->assertJsonPath('data.locked_by', $this->manager->id)
            ->assertJsonPath('data.locked_at', fn ($v) => $v !== null);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'payroll_run_locked',
            'auditable_id' => $run->id,
        ]);

        // Un run verrouillé ne peut plus être annulé, re-validé ni recalculé.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/cancel")->assertStatus(422);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertStatus(422);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")->assertStatus(422);

        // Déverrouillage sans raison → 422.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/unlock", [])->assertStatus(422);

        // Déverrouillage motivé → retour à validated, audit avec raison.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/unlock", [
            'reason' => 'Erreur de paramétrage IRG constatée par le comptable',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_VALIDATED)
            ->assertJsonPath('data.locked_by', null);

        $unlockLog = AuditLog::where('company_id', $this->company->id)
            ->where('action', 'payroll_run_unlocked')
            ->where('auditable_id', $run->id)
            ->first();
        $this->assertNotNull($unlockLog);
        $this->assertSame('Erreur de paramétrage IRG constatée par le comptable', $unlockLog->metadata['reason'] ?? null);
        // L'audit trail avant/après doit retenir les VRAIES valeurs pré-déverrouillage
        // (locked_by/locked_at non nuls) — régression gardée (PR #1632).
        $this->assertNotNull($unlockLog->old_values['locked_by'] ?? null);
        $this->assertNotNull($unlockLog->old_values['locked_at'] ?? null);
        $this->assertSame(PayrollRun::STATUS_LOCKED, $unlockLog->old_values['status'] ?? null);
        $this->assertNull($unlockLog->new_values['locked_by'] ?? null);
        $this->assertSame(PayrollRun::STATUS_VALIDATED, $unlockLog->new_values['status'] ?? null);

        // Re-verrouillage possible après déverrouillage.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_LOCKED);

        $actions = AuditLog::where('company_id', $this->company->id)
            ->where('auditable_type', $run->getMorphClass())
            ->where('auditable_id', $run->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();
        $this->assertSame([
            'payroll_run_validated',
            'payroll_run_locked',
            'payroll_run_unlocked',
            'payroll_run_locked',
        ], $actions);
    }

    public function test_validate_refused_on_draft_run(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")->assertStatus(422);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")->assertStatus(422);
    }

    public function test_employee_cannot_lock(): void
    {
        Sanctum::actingAs($this->employee);

        $run = $this->makeRun(PayrollRun::STATUS_VALIDATED);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")->assertStatus(403);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/unlock", ['reason' => 'x'])->assertStatus(403);
    }

    public function test_cross_tenant_lock_is_forbidden(): void
    {
        Sanctum::actingAs($this->otherCompanyManager);

        $run = $this->makeRun(PayrollRun::STATUS_VALIDATED);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")->assertStatus(404);
    }

    public function test_lock_requires_rh_validation_first(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);

        // Lock sans validation préalable → 422 (message explicite).
        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Un run doit être validé (étape RH) avant verrouillage comptable.');
    }
}
