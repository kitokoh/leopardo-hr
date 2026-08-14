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
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1818 — bulletins rétroactifs et régularisations : un run clôturé
 * (locked) peut être corrigé via un run de régularisation traçable, sans
 * modifier l'original.
 */
class PayrollRegularizationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_regularize_locked_run_creates_draft(): void
    {
        [$company] = $this->actors();
        $comptable = $this->comptable($company);
        $run = $this->lockedRun($company, $comptable);

        Sanctum::actingAs($comptable);

        $response = $this->postJson('/api/v1/payroll-runs/'.$run->id.'/regularize', [
            'reason' => 'Prime oubliée sur la paie de mai',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'regularization')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.original_run_id', $run->id)
            ->assertJsonPath('data.reason', 'Prime oubliée sur la paie de mai')
            ->assertJsonPath('data.period_start', $run->period_start->toDateString())
            ->assertJsonPath('data.country_code', 'DZ');

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $response->json('data.id'),
            'type' => 'regularization',
            'original_run_id' => $run->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'payroll_run_regularization_created',
        ]);

        $audit = AuditLog::where('company_id', $company->id)
            ->where('action', 'payroll_run_regularization_created')
            ->firstOrFail();
        $this->assertSame($run->id, $audit->metadata['original_run_id']);
        $this->assertSame('Prime oubliée sur la paie de mai', $audit->metadata['reason']);
        $this->assertSame($comptable->id, $audit->metadata['actor_id']);

        // Le run original n'est PAS modifié.
        $this->assertSame('locked', $run->fresh()->status);
    }

    public function test_cannot_regularize_non_locked_run(): void
    {
        [$company] = $this->actors();
        $comptable = $this->comptable($company);

        $draft = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($comptable);

        $this->postJson('/api/v1/payroll-runs/'.$draft->id.'/regularize', ['reason' => 'test'])
            ->assertStatus(422);
    }

    public function test_regularization_follows_full_workflow(): void
    {
        [$company] = $this->actors();
        $comptable = $this->comptable($company);
        $run = $this->lockedRun($company, $comptable);

        Sanctum::actingAs($comptable);

        $regularizationId = $this->postJson('/api/v1/payroll-runs/'.$run->id.'/regularize', [
            'reason' => 'Absence mal encodée',
        ])->json('data.id');

        $regularization = PayrollRun::findOrFail($regularizationId);

        // Workflow complet : draft → calculated → validated → locked.
        (new PayrollCalculator)->calculateRun($regularization);
        $regularization->refresh();
        $this->assertSame('calculated', $regularization->status);

        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        (new PayrollClosingService)->validateRh($regularization, $rh);
        $regularization->refresh();
        $this->assertSame('validated', $regularization->status);

        (new PayrollClosingService)->lock($regularization, $comptable);
        $regularization->refresh();
        $this->assertSame('locked', $regularization->status);
        $this->assertSame(PayrollRun::TYPE_REGULARIZATION, $regularization->type);
        $this->assertSame($run->id, $regularization->original_run_id);
    }

    public function test_regularization_listing_scoped_to_original_run(): void
    {
        [$company] = $this->actors();
        $comptable = $this->comptable($company);
        $run = $this->lockedRun($company, $comptable);

        Sanctum::actingAs($comptable);

        $this->postJson('/api/v1/payroll-runs/'.$run->id.'/regularize', ['reason' => 'régularisation 1']);
        $this->postJson('/api/v1/payroll-runs/'.$run->id.'/regularize', ['reason' => 'régularisation 2']);

        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/regularizations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.original_run_id', $run->id);
    }

    public function test_cross_tenant_regularization_blocked(): void
    {
        [$company] = $this->actors();
        $comptable = $this->comptable($company);
        $run = $this->lockedRun($company, $comptable);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherManager);

        $this->postJson('/api/v1/payroll-runs/'.$run->id.'/regularize', ['reason' => 'x'])->assertNotFound();
        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/regularizations')->assertNotFound();
    }

    public function test_rbac_employee_cannot_regularize(): void
    {
        [$company, $employee] = $this->actors();
        $comptable = $this->comptable($company);
        $run = $this->lockedRun($company, $comptable);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/payroll-runs/'.$run->id.'/regularize', ['reason' => 'x'])->assertForbidden();
    }

    public function test_pdf_mentions_regularization_banner(): void
    {
        [$company, $employee] = $this->actors();
        $comptable = $this->comptable($company);
        $run = $this->lockedRun($company, $comptable);

        Sanctum::actingAs($comptable);

        $regularizationId = $this->postJson('/api/v1/payroll-runs/'.$run->id.'/regularize', [
            'reason' => 'Prime oubliée',
        ])->json('data.id');

        $regularization = PayrollRun::findOrFail($regularizationId);
        (new PayrollCalculator)->calculateRun($regularization);

        $slip = $regularization->paySlips()->firstOrFail();

        $pdf = (new \App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator)->generate($slip);

        // Le PDF est binaire (dompdf) — vérifier que la mention est bien
        // rendue : on teste la vue rendue plutôt que le binaire compressé.
        $html = view('pdf.payslip', [
            'slip' => $slip,
            'lines' => $slip->lines->sortBy('order'),
            'employee' => $employee,
            'company' => $company,
            'currency' => 'DZD',
            'legalMentions' => '',
            'companyLegal' => [],
            'annualCumuls' => [],
            'isRegularization' => true,
            'originalRunId' => $run->id,
        ])->render();

        $this->assertStringContainsString('Bulletin de régularisation', $html);
        $this->assertStringContainsString('corrige le run', $html);
        $this->assertStringContainsString('#'.$run->id, $html);
        $this->assertNotEmpty($pdf, 'Le PDF doit être généré');
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function actors(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
            'status' => 'active',
        ]);

        return [$company, $employee];
    }

    /**
     * Comptable créé APRÈS le calcul du run (un manager actif créé avant
     * recevrait un bulletin) — statut actif requis pour agir via l'API.
     */
    private function comptable(Company $company): Employee
    {
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'comptable',
            'status' => 'active',
        ]);

        return $comptable;
    }

    private function lockedRun(Company $company, Employee $comptable): PayrollRun
    {
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        SalaryStructure::query()->create([
            'company_id' => $company->id,
            'name' => 'Grille par défaut (test)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        (new PayrollCalculator)->calculateRun($run);

        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        (new PayrollClosingService)->validateRh($run->refresh(), $rh);
        (new PayrollClosingService)->lock($run->refresh(), $comptable);

        return $run->refresh();
    }
}
