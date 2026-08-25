<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ArchivePaySlipsToCabinetJob;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1817 — Archivage automatique des bulletins PDF dans le Cabinet
 * employé après clôture (F-09/#1548).
 */
class PaySlipCabinetArchiveTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCalculatedRunWithSlips(Company $company): PayrollRun
    {
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_DRAFT,
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

        return $run->refresh();
    }

    public function test_lock_creates_cabinet_documents_for_all_slips(): void
    {
        Storage::fake('private');

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeCalculatedRunWithSlips($company);
        $slipCount = PaySlip::query()->where('payroll_run_id', $run->id)->count();
        $this->assertGreaterThanOrEqual(1, $slipCount);

        $service = new PayrollClosingService;
        $run = $service->validateRh($run, $rh);
        $run = $service->lock($run, $comptable);

        $this->assertSame(PayrollRun::STATUS_LOCKED, $run->status);

        // Le job d'archivage a tourné (queue sync) : un document par bulletin.
        $docs = CabinetDocument::query()
            ->where('document_type', 'payslip')
            ->whereNotNull('source_id')
            ->get();

        $this->assertCount($slipCount, $docs);
        foreach ($docs as $doc) {
            $this->assertTrue($doc->read_only);
            $this->assertSame('payslip', $doc->document_type);
            Storage::disk('private')->assertExists($doc->path);
        }

        // Audit immuable par bulletin.
        $this->assertSame($slipCount, AuditLog::query()->where('action', 'payslip_archived')->count());
    }

    public function test_double_lock_is_idempotent(): void
    {
        Storage::fake('private');

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeCalculatedRunWithSlips($company);
        $slipId = PaySlip::query()->where('payroll_run_id', $run->id)->value('id');

        // Exécution manuelle du job deux fois → un seul document.
        (new ArchivePaySlipsToCabinetJob($run->id))->handle(new PaySlipPdfGenerator);
        (new ArchivePaySlipsToCabinetJob($run->id))->handle(new PaySlipPdfGenerator);

        $this->assertSame(
            1,
            CabinetDocument::query()->where('document_type', 'payslip')->where('source_id', $slipId)->count(),
        );
    }

    public function test_read_only_payslip_cannot_be_deleted(): void
    {
        Storage::fake('private');

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeCalculatedRunWithSlips($company);
        $slip = PaySlip::query()->where('payroll_run_id', $run->id)->firstOrFail();

        (new ArchivePaySlipsToCabinetJob($run->id))->handle(new PaySlipPdfGenerator);

        $document = CabinetDocument::query()->where('document_type', 'payslip')->firstOrFail();

        Sanctum::actingAs($manager);
        $this->deleteJson("/api/v1/cabinet/documents/{$document->id}")->assertStatus(403);

        // Toujours présent.
        $this->assertNotNull(CabinetDocument::find($document->id));
    }

    public function test_document_endpoint_returns_archived_file(): void
    {
        Storage::fake('private');

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'salary_type' => 'fixed', 'salary_base' => 60000]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeCalculatedRunWithSlips($company);
        $slip = PaySlip::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->first();

        // Si l'employé de structure n'est pas celui attendu, on prend le premier bulletin.
        $slip ??= PaySlip::query()->where('payroll_run_id', $run->id)->firstOrFail();

        (new ArchivePaySlipsToCabinetJob($run->id))->handle(new PaySlipPdfGenerator);

        // Propriétaire du bulletin.
        /** @var Employee $owner */
        $owner = Employee::query()->findOrFail((int) $slip->employee_id);
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/document")->assertStatus(200);

        // Manager de la même société.
        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/document")->assertStatus(200);

        // Employé d'une AUTRE société → 404 (isolation tenant).
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $outsider */
        $outsider = Employee::factory()->create(['company_id' => $otherCompany->id]);
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/document")->assertStatus(404);
    }
}
