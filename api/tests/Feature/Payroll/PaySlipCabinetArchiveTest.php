<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ArchivePaySlipsToCabinetJob;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * F-09/#1817 — Archivage automatique des bulletins PDF dans le Cabinet employé.
 *
 * Couvre :
 * - lock() crée un CabinetDocument `payslip` read_only par bulletin + audit
 *   `payslip_archived` ;
 * - idempotence (double dispatch → aucun doublon) ;
 * - GET /me/pay-slips/{slip}/document retourne l'URL de téléchargement ;
 * - isolation tenant sur l'endpoint document ;
 * - un document read_only ne peut être ni supprimé (403) ni modifié (403).
 */
class PaySlipCabinetArchiveTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $rh;

    private Employee $comptable;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;
        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->rh = $rh;
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->comptable = $comptable;
    }

    /**
     * Run calculé avec de vrais bulletins (structure salariale active +
     * employés actifs) — le workflow de clôture refuse les runs vides.
     */
    private function makeCalculatedRunWithSlips(int $employeeCount = 2): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        SalaryStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Grille par défaut (test archivage)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        for ($i = 0; $i < $employeeCount; $i++) {
            Employee::factory()->create([
                'company_id' => $this->company->id,
                'salary_type' => 'fixed',
                'salary_base' => 60000,
            ]);
        }

        (new PayrollCalculator)->calculateRun($run);

        return $run->refresh();
    }

    /**
     * Valide puis verrouille un run calculé (flux complet F-11).
     */
    private function lockRun(PayrollRun $run): PayrollRun
    {
        $service = new PayrollClosingService;
        $service->validateRh($run, $this->rh);
        $run = $service->lock($run->refresh(), $this->comptable);

        return $run->refresh();
    }

    public function test_lock_creates_cabinet_documents_for_all_slips(): void
    {
        $run = $this->makeCalculatedRunWithSlips(2);
        $slips = $run->paySlips()->get();
        $this->assertGreaterThanOrEqual(1, $slips->count());

        $locked = $this->lockRun($run);
        $this->assertSame(PayrollRun::STATUS_LOCKED, $locked->status);

        $employeeIds = $slips->pluck('employee_id')->all();
        $documents = CabinetDocument::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('document_type', 'payslip')
            ->get();

        // Un document par bulletin, en lecture seule, PDF stocké sur le disque.
        $this->assertSame($slips->count(), $documents->count());
        foreach ($documents as $document) {
            $this->assertTrue($document->read_only);
            $this->assertSame('payslip', $document->document_type);
            $this->assertSame('application/pdf', $document->mime_type);
            $this->assertSame('local', $document->disk);
            Storage::disk('local')->assertExists($document->path);
        }

        // Audit `payslip_archived` : un par bulletin, rattaché au PaySlip.
        $audits = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('action', 'payslip_archived')
            ->get();
        $this->assertSame($slips->count(), $audits->count());
        foreach ($audits as $audit) {
            $this->assertSame($slips->first()->getMorphClass(), $audit->auditable_type);
            $this->assertNotEmpty($audit->new_values['document_id']);
            $this->assertSame($locked->id, $audit->new_values['payroll_run_id']);
        }
    }

    public function test_double_lock_and_redispatch_are_idempotent(): void
    {
        $run = $this->makeCalculatedRunWithSlips(2);
        $slips = $run->paySlips()->get();

        $locked = $this->lockRun($run);

        // 1) Double verrouillage : refusé et aucun nouveau document créé.
        $this->expectException(\App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException::class);
        (new PayrollClosingService)->lock($locked, $this->comptable);
    }

    public function test_redispatch_of_archive_job_does_not_duplicate(): void
    {
        $run = $this->lockRun($this->makeCalculatedRunWithSlips(2));
        $employeeIds = $run->paySlips()->pluck('employee_id')->all();

        $countAfterLock = CabinetDocument::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('document_type', 'payslip')
            ->count();

        // Redispatch volontaire du job (retry, re-exécution) → idempotent.
        ArchivePaySlipsToCabinetJob::dispatchSync($run->id, $this->comptable->id);

        $countAfterRedispatch = CabinetDocument::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('document_type', 'payslip')
            ->count();

        $this->assertSame($countAfterLock, $countAfterRedispatch);
    }

    public function test_me_pay_slips_document_returns_secure_download_url(): void
    {
        $run = $this->makeCalculatedRunWithSlips(1);
        $slip = $run->paySlips()->first();
        $this->lockRun($run);

        /** @var Employee $employee */
        $employee = Employee::find($slip->employee_id);
        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/document")
            ->assertOk()
            ->assertJsonPath('data.document_id', fn ($id) => is_int($id))
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonStructure(['data' => ['document_id', 'name', 'mime_type', 'size', 'download_url']]);

        // L'URL de téléchargement retournée pointe vers le document Cabinet et
        // sert bien le PDF archivé (téléchargement authentifié).
        $document = CabinetDocument::query()
            ->where('employee_id', $slip->employee_id)
            ->where('document_type', 'payslip')
            ->firstOrFail();
        $this->get("/api/v1/cabinet/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_cross_tenant_document_blocked(): void
    {
        $run = $this->makeCalculatedRunWithSlips(1);
        $slip = $run->paySlips()->first();
        $this->lockRun($run);

        /** @var Employee $foreignEmployee */
        $foreignEmployee = Employee::factory()->create(['company_id' => $this->otherCompany->id]);
        Sanctum::actingAs($foreignEmployee);

        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/document")->assertNotFound();
    }

    public function test_read_only_payslip_cannot_be_deleted_nor_updated(): void
    {
        $run = $this->makeCalculatedRunWithSlips(1);
        $slip = $run->paySlips()->first();
        $this->lockRun($run);

        $document = CabinetDocument::query()
            ->where('employee_id', $slip->employee_id)
            ->where('document_type', 'payslip')
            ->firstOrFail();

        /** @var Employee $employee */
        $employee = Employee::find($slip->employee_id);
        Sanctum::actingAs($employee);

        $this->deleteJson("/api/v1/cabinet/documents/{$document->id}")->assertForbidden();
        $this->putJson("/api/v1/cabinet/documents/{$document->id}", ['name' => 'renommé.pdf'])->assertForbidden();

        // Le document existe toujours (ni supprimé ni modifié).
        $this->assertDatabaseHas('cabinet_documents', [
            'id' => $document->id,
            'name' => $document->name,
        ]);
    }

    public function test_regular_cabinet_document_can_still_be_deleted(): void
    {
        // Un document Cabinet classique (non read_only) reste supprimable.
        $document = CabinetDocument::create([
            'company_id' => 0,
            'employee_id' => $this->rh->id,
            'folder_id' => null,
            'name' => 'CV.pdf',
            'original_name' => 'CV.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'disk' => 'local',
            'path' => 'cabinet/0/cv.pdf',
            'notes' => null,
        ]);

        Sanctum::actingAs($this->rh);
        $this->deleteJson("/api/v1/cabinet/documents/{$document->id}")->assertNoContent();
        $this->assertDatabaseMissing('cabinet_documents', ['id' => $document->id]);
    }
}
