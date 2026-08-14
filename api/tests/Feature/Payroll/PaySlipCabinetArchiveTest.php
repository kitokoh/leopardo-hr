<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ArchivePaySlipsToCabinetJob;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
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
 *
 * À la clôture (lock) d'un run, chaque bulletin est archivé dans le Cabinet
 * de l'employé : PDF généré + CabinetDocument read_only + audit
 * `payslip_archived`. Idempotent, scopé au tenant, non supprimable par
 * l'employé, et exposé via GET /me/pay-slips/{slip}/document.
 */
class PaySlipCabinetArchiveTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Flux complet de production : run draft → calculé → validé (RH) →
     * bulletins `validated` → verrouillé (comptable). Le job d'archivage est
     * dispatché par PayrollClosingService::lock() (queue sync en test).
     */
    private function makeLockedRun(Company $company, Employee $comptable): PayrollRun
    {
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
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
        $run = $run->refresh();

        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        (new PayrollClosingService)->validateRh($run, $rh);

        // Miroir du contrôleur validateRun : les bulletins passent en `validated`.
        $run->paySlips()->update(['status' => 'validated']);

        return (new PayrollClosingService)->lock($run->refresh(), $comptable);
    }

    public function test_lock_creates_cabinet_documents_for_all_slips(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeLockedRun($company, $comptable);

        $slips = $run->paySlips()->get();
        $this->assertNotEmpty($slips);

        $documents = CabinetDocument::query()
            ->where('company_id', $company->id)
            ->where('document_type', CabinetDocument::TYPE_PAYSLIP)
            ->get();

        $this->assertCount($slips->count(), $documents);

        foreach ($documents as $document) {
            $this->assertTrue($document->read_only);
            $this->assertSame(CabinetDocument::TYPE_PAYSLIP, $document->document_type);
            $this->assertSame($company->id, $document->company_id);
            $this->assertSame('application/pdf', $document->mime_type);
            $this->assertStringContainsString(
                sprintf('payslips/%s/', $company->id),
                $document->path
            );
            $this->assertStringEndsWith(sprintf('_%d.pdf', $run->id), $document->path);
            $this->assertTrue(Storage::disk('local')->exists($document->path));
        }

        // Audit log `payslip_archived` présent pour chaque bulletin.
        $this->assertSame(
            $slips->count(),
            AuditLog::query()
                ->where('company_id', $company->id)
                ->where('action', 'payslip_archived')
                ->count()
        );
    }

    public function test_double_lock_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeLockedRun($company, $comptable);
        $slipCount = $run->paySlips()->count();

        $this->assertSame($slipCount, CabinetDocument::query()->where('company_id', $company->id)->count());

        // Un second verrouillage est refusé — et ne crée aucun doublon.
        try {
            (new PayrollClosingService)->lock($run, $comptable);
            $this->fail('Un second lock aurait dû lever PayrollRunLockedException.');
        } catch (PayrollRunLockedException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($slipCount, CabinetDocument::query()->where('company_id', $company->id)->count());

        // Re-dispatch direct du job (retry, unlock+relock…) : idempotent.
        (new ArchivePaySlipsToCabinetJob($run->id))->handle(app(PaySlipPdfGenerator::class));

        $this->assertSame($slipCount, CabinetDocument::query()->where('company_id', $company->id)->count());
        $this->assertSame(
            $slipCount,
            AuditLog::query()->where('company_id', $company->id)->where('action', 'payslip_archived')->count()
        );
    }

    public function test_read_only_payslip_cannot_be_deleted(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeLockedRun($company, $comptable);

        /** @var \App\Modules\Payroll\Domain\Models\PaySlip $slip */
        $slip = $run->paySlips()->with('employee')->first();
        $employee = $slip->employee;
        $this->assertInstanceOf(Employee::class, $employee);

        /** @var CabinetDocument $document */
        $document = CabinetDocument::query()
            ->where('company_id', $company->id)
            ->where('document_type', CabinetDocument::TYPE_PAYSLIP)
            ->first();

        $this->assertTrue($document->read_only);

        Sanctum::actingAs($employee);
        $this->deleteJson('/api/v1/cabinet/documents/'.$document->id)->assertForbidden();
        $this->putJson('/api/v1/cabinet/documents/'.$document->id, ['name' => 'Renommé'])->assertForbidden();
        $this->patchJson('/api/v1/cabinet/documents/'.$document->id.'/move', ['folder_id' => null])->assertForbidden();

        // Pas de régression : un document normal reste supprimable par son
        // propriétaire.
        $normal = CabinetDocument::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'name' => 'CV',
            'original_name' => 'cv.pdf',
            'mime_type' => 'application/pdf',
            'size' => 42,
            'disk' => 'local',
            'path' => 'cabinet/'.$company->id.'/'.$employee->id.'/cv.pdf',
            'read_only' => false,
        ]);

        $this->deleteJson('/api/v1/cabinet/documents/'.$normal->id)->assertStatus(204);
        $this->assertDatabaseMissing('cabinet_documents', ['id' => $normal->id]);
    }

    public function test_employee_gets_secure_download_url_for_archived_payslip(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = $this->makeLockedRun($company, $comptable);

        /** @var \App\Modules\Payroll\Domain\Models\PaySlip $slip */
        $slip = $run->paySlips()->with('employee')->first();
        $employee = $slip->employee;
        $this->assertInstanceOf(Employee::class, $employee);

        /** @var CabinetDocument $document */
        $document = CabinetDocument::query()
            ->where('company_id', $company->id)
            ->where('document_type', CabinetDocument::TYPE_PAYSLIP)
            ->first();

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/pay-slips/'.$slip->id.'/document');
        $response->assertOk();
        $response->assertJsonPath('data.document_id', $document->id);

        $url = $response->json('data.url');
        $this->assertIsString($url);

        $urlPath = parse_url($url, PHP_URL_PATH);
        $this->assertIsString($urlPath);
        $this->assertStringContainsString('/api/v1/cabinet/documents/'.$document->id.'/download', $url);

        // L'URL renvoyée est réellement téléchargeable (PDF, autorisation OK).
        $this->getJson($urlPath)->assertOk();

        // Cross-tenant : un employé d'une autre société → 404.
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherEmployee);
        $this->getJson('/api/v1/me/pay-slips/'.$slip->id.'/document')->assertNotFound();
    }
}
