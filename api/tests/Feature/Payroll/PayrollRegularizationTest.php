<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use App\Modules\Payroll\Infrastructure\Services\PayrollRegularizationService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DZ-DEPTH (issue #1818) — bulletins rétroactifs et régularisations :
 * correction d'un run clôturé (locked) sans modifier le run original.
 *
 * Couvre : création d'un run de régularisation (draft, même période, lien
 * original_run_id + reason), refus sur run non clôturé (422), workflow
 * complet jusqu'au lock, isolation tenant (404), audit trail.
 */
class PayrollRegularizationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function makeManager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function makeLockedRun(Company $company): PayrollRun
    {
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille par défaut (test)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
        ]);

        (new PayrollCalculator)->calculateRun($run);

        $service = new PayrollClosingService;
        $service->validateRh($run->refresh(), $this->makeManager($company));

        return $service->lock($run->refresh(), $this->makeManager($company));
    }

    public function test_regularize_locked_run_creates_draft(): void
    {
        $company = $this->makeCompany();
        $locked = $this->makeLockedRun($company);
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$locked->id}/regularize", [
            'reason' => 'Prime de rendement oubliée sur le run de juillet',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', PayrollRun::TYPE_REGULARIZATION)
            ->assertJsonPath('data.status', PayrollRun::STATUS_DRAFT)
            ->assertJsonPath('data.original_run_id', $locked->id)
            ->assertJsonPath('data.reason', 'Prime de rendement oubliée sur le run de juillet');

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $response->json('data.id'),
            'type' => PayrollRun::TYPE_REGULARIZATION,
            'original_run_id' => $locked->id,
        ]);

        // Audit trail.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payroll_run_regularization_created',
            'auditable_id' => $locked->id,
        ]);
    }

    public function test_cannot_regularize_non_locked_run(): void
    {
        $company = $this->makeCompany();
        $draft = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$draft->id}/regularize", ['reason' => 'Test'])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('payroll_runs', [
            'type' => PayrollRun::TYPE_REGULARIZATION,
        ]);
    }

    public function test_reason_is_required(): void
    {
        $company = $this->makeCompany();
        $locked = $this->makeLockedRun($company);
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$locked->id}/regularize", ['reason' => ''])
            ->assertUnprocessable();
    }

    public function test_regularization_follows_full_workflow(): void
    {
        $company = $this->makeCompany();
        $locked = $this->makeLockedRun($company);
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        $regularizationId = $this->postJson("/api/v1/payroll-runs/{$locked->id}/regularize", [
            'reason' => 'Régularisation prime',
        ])->assertCreated()->json('data.id');

        /** @var PayrollRun $regularization */
        $regularization = PayrollRun::findOrFail($regularizationId);

        // Workflow standard : calculate → validate → lock.
        (new PayrollCalculator)->calculateRun($regularization);
        $this->assertSame(PayrollRun::STATUS_CALCULATED, $regularization->refresh()->status);

        $rh = $this->makeManager($company);
        $comptable = $this->makeManager($company);
        $closing = new PayrollClosingService;
        $closing->validateRh($regularization, $rh);
        $closing->lock($regularization, $comptable);

        $this->assertSame(PayrollRun::STATUS_LOCKED, $regularization->refresh()->status);
    }

    public function test_cross_tenant_regularization_blocked(): void
    {
        $companyA = $this->makeCompany();
        $lockedA = $this->makeLockedRun($companyA);

        $companyB = $this->makeCompany();
        $managerB = $this->makeManager($companyB);

        Sanctum::actingAs($managerB);

        $this->postJson("/api/v1/payroll-runs/{$lockedA->id}/regularize", ['reason' => 'Test'])
            ->assertNotFound();

        $this->getJson("/api/v1/payroll-runs/{$lockedA->id}/regularizations")
            ->assertNotFound();
    }

    public function test_regularizations_list_returns_linked_runs(): void
    {
        $company = $this->makeCompany();
        $locked = $this->makeLockedRun($company);
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$locked->id}/regularize", [
            'reason' => 'Première régularisation',
        ])->assertCreated();

        $this->getJson("/api/v1/payroll-runs/{$locked->id}/regularizations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', PayrollRun::TYPE_REGULARIZATION);
    }

    public function test_audit_log_contains_reason_and_original_run_id(): void
    {
        $company = $this->makeCompany();
        $locked = $this->makeLockedRun($company);
        $manager = $this->makeManager($company);

        app(PayrollRegularizationService::class)
            ->createRegularization($locked, $manager, 'Correction absence mal encodée');

        $audit = AuditLog::query()
            ->where('action', 'payroll_run_regularization_created')
            ->where('auditable_id', $locked->id)
            ->firstOrFail();

        $this->assertSame('Correction absence mal encodée', $audit->new_values['reason']);
        $this->assertSame($locked->id, $audit->metadata['original_run_id']);
    }
}
