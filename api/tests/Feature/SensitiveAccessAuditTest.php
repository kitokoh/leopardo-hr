<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-2 (#1662) — Journalisation des accès aux données sensibles.
 *
 * Les lectures sensibles (bulletins, documents de paiement, journal de paie,
 * exports bancaires, fin de contrat, certificat) doivent être tracées dans
 * `audit_logs` via `DataAccessAuditLogger::recordSensitive()` avec la
 * catégorie `sensitive_data_access`, avec échantillonnage configurable.
 */
class SensitiveAccessAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

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
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_start' => '2023-07-01',
            'salary_base' => 60000,
            'position' => 'Développeur',
        ]);
        $this->employee = $employee;

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Cadre moyen DZ',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Storage::fake('local');
    }

    private function sensitiveCount(string $action): int
    {
        return AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('action', $action)
            ->where('metadata->category', 'sensitive_data_access')
            ->count();
    }

    public function test_pay_slip_pdf_download_is_traced(): void
    {
        $run = $this->makeRun();
        Storage::disk('local')->put('pdfs/bulletin-1.pdf', 'PDF-FAKE');

        $paySlip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 60000,
            'net_salary' => 48000,
            'status' => 'validated',
            'pdf_path' => 'pdfs/bulletin-1.pdf',
        ]);

        Sanctum::actingAs($this->employee);

        $this->getJson("/api/v1/me/pay-slips/{$paySlip->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(1, $this->sensitiveCount('hr_data.pay_slip_downloaded'));
    }

    public function test_payment_document_download_is_traced(): void
    {
        Storage::disk('local')->put('docs/paie-1.pdf', 'PDF-FAKE');

        $document = PaymentDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'document_type' => 'payslip',
            'status' => PaymentDocument::STATUS_AVAILABLE,
            'path' => 'docs/paie-1.pdf',
            'filename' => 'bulletin.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'local',
        ]);

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/me/payment-documents/{$document->id}/download")
            ->assertOk();

        $this->assertSame(1, $this->sensitiveCount('hr_data.payment_doc_downloaded'));
    }

    public function test_payroll_journal_view_is_traced(): void
    {
        $run = $this->makeRun();

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")
            ->assertOk();

        $this->assertSame(1, $this->sensitiveCount('hr_data.payroll_journal_viewed'));
    }

    public function test_bank_export_view_is_traced(): void
    {
        $run = $this->makeRun();

        $export = BankExport::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->company->id,
            'format' => 'sepa_xml',
            'file_path' => null,
            'total_amount' => 100000,
            'transfer_count' => 2,
            'status' => BankExport::STATUS_GENERATED,
        ]);

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/bank-exports/{$export->id}")
            ->assertOk();

        $this->assertSame(1, $this->sensitiveCount('hr_data.bank_export_viewed'));
    }

    public function test_end_of_contract_view_is_traced(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/employees/{$this->employee->id}/end-of-contract")
            ->assertOk();

        $this->assertSame(1, $this->sensitiveCount('hr_data.end_of_contract_viewed'));
    }

    public function test_certificate_of_employment_download_is_traced(): void
    {
        Sanctum::actingAs($this->manager);

        $this->get("/api/v1/employees/{$this->employee->id}/certificate-of-employment")
            ->assertOk();

        $this->assertSame(1, $this->sensitiveCount('hr_data.certificate_downloaded'));
    }

    public function test_sampling_zero_disables_tracing(): void
    {
        config(['audit.sensitive_access.sampling' => 0.0]);

        $run = $this->makeRun();

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")
            ->assertOk();

        $this->assertSame(0, $this->sensitiveCount('hr_data.payroll_journal_viewed'));
    }

    public function test_sensitive_report_command_lists_traces(): void
    {
        $run = $this->makeRun();

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")
            ->assertOk();

        /** @var \Illuminate\Testing\PendingCommand $cmd */
        $cmd = $this->artisan('audit:sensitive-report', ['--days' => 30]);
        $cmd->expectsOutputToContain('Accès sensibles tracés');
        $cmd->expectsOutputToContain('hr_data.payroll_journal_viewed');
        $cmd->assertSuccessful();
        $cmd->run();
    }

    private function makeRun(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        return $run;
    }
}
