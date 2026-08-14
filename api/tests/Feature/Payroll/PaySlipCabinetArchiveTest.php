<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PaySlipCabinetArchiver;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-09/#1548 (issue #1817) : archivage automatique des
 * bulletins PDF dans le Cabinet employé après clôture.
 *
 * Flux couvert : calculateRun → validateRh → lock (dispatch du job
 * ArchivePaySlipsToCabinetJob, queue sync en test) → CabinetDocument
 * read_only + audit `payslip_archived`, idempotence, garde read_only 403,
 * endpoint /me/pay-slips/{slip}/document et isolation tenant.
 */
class PaySlipCabinetArchiveTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function makeEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
        ]);

        return $employee;
    }

    private function makeManager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    /**
     * Run calculé AVEC un vrai bulletin (structure salariale active + employé).
     * Retourne [run, employé] pour garder une référence fiable (les managers
     * créés par lockRun sont aussi des lignes employees).
     *
     * @return array{0: PayrollRun, 1: Employee}
     */
    private function makeCalculatedRun(Company $company): array
    {
        $employee = $this->makeEmployee($company);

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

        (new PayrollCalculator)->calculateRun($run);

        return [$run->refresh(), $employee];
    }

    private function lockRun(PayrollRun $run, Company $company): PayrollRun
    {
        $rh = $this->makeManager($company);
        $comptable = $this->makeManager($company);

        $service = new PayrollClosingService;
        $service->validateRh($run, $rh);

        return $service->lock($run, $comptable);
    }

    public function test_lock_creates_cabinet_documents_for_all_slips(): void
    {
        $company = $this->makeCompany();
        [$run, $employee] = $this->makeCalculatedRun($company);

        $this->lockRun($run, $company);

        /** @var CabinetDocument $document */
        $document = CabinetDocument::query()
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertTrue($document->read_only);
        $this->assertSame('payslip', $document->document_type);
        $this->assertNotNull($document->pay_slip_id);
        $this->assertSame($run->id, $document->paySlip->payroll_run_id);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertStringContainsString('slip_', $document->path);
        $this->assertTrue(Storage::disk('local')->exists($document->path));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payslip_archived',
            'auditable_id' => $document->pay_slip_id,
        ]);
    }

    public function test_archive_is_idempotent_no_double_lock_duplicates(): void
    {
        $company = $this->makeCompany();
        [$run] = $this->makeCalculatedRun($company);

        $this->lockRun($run, $company);

        $countAfterLock = CabinetDocument::query()
            ->where('document_type', 'payslip')
            ->whereIn('pay_slip_id', $run->paySlips()->pluck('id'))
            ->count();

        // Rejouer l'archivage (retry queue, double dispatch) → aucun doublon.
        $result = (new PaySlipCabinetArchiver(new \App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator))
            ->archiveRun($run);

        $this->assertSame(0, $result['archived']);
        $this->assertSame((int) $countAfterLock, $result['skipped']);
        $this->assertSame((int) $countAfterLock, CabinetDocument::query()
            ->where('document_type', 'payslip')
            ->whereIn('pay_slip_id', $run->paySlips()->pluck('id'))
            ->count());
    }

    public function test_read_only_payslip_cannot_be_deleted(): void
    {
        $company = $this->makeCompany();
        [$run, $employee] = $this->makeCalculatedRun($company);

        $this->lockRun($run, $company);

        /** @var CabinetDocument $document */
        $document = CabinetDocument::where('employee_id', $employee->id)->firstOrFail();

        Sanctum::actingAs($employee);

        $this->deleteJson("/api/v1/cabinet/documents/{$document->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('cabinet_documents', ['id' => $document->id]);
    }

    public function test_me_payslip_document_endpoint_returns_download_url(): void
    {
        $company = $this->makeCompany();
        [$run, $employee] = $this->makeCalculatedRun($company);

        $this->lockRun($run, $company);

        $slip = $run->paySlips()->firstOrFail();
        $document = CabinetDocument::where('employee_id', $employee->id)->firstOrFail();

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/document")
            ->assertOk()
            ->assertJsonPath('data.document_id', $document->id)
            ->assertJsonPath('data.read_only', true)
            ->assertJsonPath('data.download_url', "/api/v1/cabinet/documents/{$document->id}/download");

        // Le lien de téléchargement Cabinet sert réellement le PDF archivé.
        $this->getJson("/api/v1/cabinet/documents/{$document->id}/download")
            ->assertOk();
    }

    public function test_cross_tenant_employee_cannot_access_archived_document(): void
    {
        $companyA = $this->makeCompany();
        [$runA] = $this->makeCalculatedRun($companyA);
        $this->lockRun($runA, $companyA);

        $slipA = $runA->paySlips()->firstOrFail();

        // Employé d'un AUTRE tenant → 404 sur le bulletin comme sur le document.
        $companyB = $this->makeCompany();
        $employeeB = $this->makeEmployee($companyB);

        Sanctum::actingAs($employeeB);

        $this->getJson("/api/v1/me/pay-slips/{$slipA->id}/document")
            ->assertNotFound();
    }

    public function test_audit_trail_records_every_archived_slip(): void
    {
        $company = $this->makeCompany();
        [$run] = $this->makeCalculatedRun($company);

        $this->lockRun($run, $company);

        $slipIds = $run->paySlips()->pluck('id')->sort()->values();

        $archived = AuditLog::query()
            ->where('action', 'payslip_archived')
            ->whereIn('auditable_id', $slipIds)
            ->pluck('auditable_id')
            ->sort()
            ->values();

        $this->assertSame($slipIds->all(), $archived->all());
    }
}
