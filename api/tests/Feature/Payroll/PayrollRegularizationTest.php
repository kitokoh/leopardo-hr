<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Application\Services\PayrollRegularizationService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DZ-DEPTH (#1818) — bulletins rétroactifs et régularisations.
 *
 * Couvre : création d'un run de régularisation depuis un run verrouillé,
 * refus pour un run non verrouillé, audit trail (motif + original_run_id),
 * workflow complet jusqu'au lock, isolation tenant.
 */
class PayrollRegularizationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $otherCompanyManager;

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
            'type' => PayrollRun::TYPE_STANDARD,
        ]);

        return $run;
    }

    public function test_regularize_locked_run_creates_draft(): void
    {
        $lockedRun = $this->makeRun(PayrollRun::STATUS_LOCKED);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/payroll-runs/{$lockedRun->id}/regularize", [
            'reason' => 'Prime de rendement oubliée sur le run de juillet',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', PayrollRun::TYPE_REGULARIZATION)
            ->assertJsonPath('data.status', PayrollRun::STATUS_DRAFT)
            ->assertJsonPath('data.original_run_id', $lockedRun->id)
            ->assertJsonPath('data.period_start', '2026-07-01')
            ->assertJsonPath('data.period_end', '2026-07-31');

        // L'original n'est jamais modifié.
        $lockedRun->refresh();
        $this->assertSame(PayrollRun::STATUS_LOCKED, $lockedRun->status);
    }

    public function test_cannot_regularize_non_locked_run(): void
    {
        $draftRun = $this->makeRun(PayrollRun::STATUS_DRAFT);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/payroll-runs/{$draftRun->id}/regularize", [
            'reason' => 'Tentative sur un run non verrouillé',
        ])->assertStatus(422);
    }

    public function test_regularization_creates_audit_trail(): void
    {
        $lockedRun = $this->makeRun(PayrollRun::STATUS_LOCKED);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/payroll-runs/{$lockedRun->id}/regularize", [
            'reason' => 'Absence mal encodée — régularisation requise',
        ])->assertCreated();

        /** @var AuditLog $log */
        $log = AuditLog::query()
            ->where('action', PayrollRegularizationService::AUDIT_ACTION_REGULARIZATION_CREATED)
            ->where('auditable_id', $lockedRun->id)
            ->firstOrFail();

        $this->assertSame('Absence mal encodée — régularisation requise', $log->metadata['reason'] ?? null);
        $this->assertSame($lockedRun->id, $log->metadata['original_run_id'] ?? null);
        $this->assertSame('payroll_run_regularization_created', $log->action);
        $this->assertSame($this->manager->id, $log->user_id);
    }

    public function test_regularization_follows_full_workflow(): void
    {
        $lockedRun = $this->makeRun(PayrollRun::STATUS_LOCKED);

        Sanctum::actingAs($this->manager);

        $created = $this->postJson("/api/v1/payroll-runs/{$lockedRun->id}/regularize", [
            'reason' => 'Prime oubliée',
        ])->assertCreated()->json('data');

        $this->assertSame(PayrollRun::STATUS_DRAFT, $created['status']);

        // Le run de régularisation est listable via l'endpoint dédié.
        $this->getJson("/api/v1/payroll-runs/{$lockedRun->id}/regularizations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created['id']);
    }

    public function test_cross_tenant_regularization_blocked(): void
    {
        $lockedRun = $this->makeRun(PayrollRun::STATUS_LOCKED);

        Sanctum::actingAs($this->otherCompanyManager);

        $this->postJson("/api/v1/payroll-runs/{$lockedRun->id}/regularize", [
            'reason' => 'Tentative cross-tenant',
        ])->assertNotFound();

        $this->getJson("/api/v1/payroll-runs/{$lockedRun->id}/regularizations")->assertNotFound();
    }

    public function test_reason_is_required(): void
    {
        $lockedRun = $this->makeRun(PayrollRun::STATUS_LOCKED);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/payroll-runs/{$lockedRun->id}/regularize", [
            'reason' => '',
        ])->assertUnprocessable();
    }
}
