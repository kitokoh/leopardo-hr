<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * S-2 (#1662) — Journalisation des accès en lecture aux données sensibles
 * (paie, exports, bulletins, journal, certificat, end-of-contract).
 *
 * Un test par ressource sensible + bornage du volume (échantillonnage) +
 * commande audit:sensitive-report.
 */
class SensitiveDataAccessAuditTest extends TestCase
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
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->employee = $employee;

        Sanctum::actingAs($this->manager);
    }

    /**
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function seededRun(): array
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $this->employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 60000,
            'net_salary' => 48000,
            'status' => 'validated',
        ]);

        return [$run, $slip];
    }

    private function lastSensitiveLog(): ?AuditLog
    {
        return AuditLog::query()
            ->where('action', 'sensitive_data_access')
            ->latest('id')
            ->first();
    }

    // ── Une trace par ressource sensible ────────────────────────────────────

    public function test_pay_slip_list_is_logged(): void
    {
        $this->seededRun();

        $this->getJson('/api/v1/pay-slips')->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('pay_slip.list', $log->metadata['resource']);
        $this->assertSame($this->manager->id, $log->user_id);
    }

    public function test_pay_slip_detail_is_logged(): void
    {
        [, $slip] = $this->seededRun();

        $this->getJson('/api/v1/pay-slips/'.$slip->id)->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('pay_slip.detail', $log->metadata['resource']);
    }

    public function test_pay_slip_download_is_logged(): void
    {
        [, $slip] = $this->seededRun();
        Storage::fake('local');
        Storage::disk('local')->put('slips/bulletin.pdf', 'fake-pdf');
        $slip->update(['pdf_path' => 'slips/bulletin.pdf']);

        $this->get('/api/v1/pay-slips/'.$slip->id.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('pay_slip.download', $log->metadata['resource']);
    }

    public function test_payroll_journal_is_logged(): void
    {
        [$run] = $this->seededRun();

        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('payroll.journal', $log->metadata['resource']);
    }

    public function test_end_of_contract_settlement_is_logged(): void
    {
        $this->getJson('/api/v1/employees/'.$this->employee->id.'/end-of-contract')->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('payroll.settlement', $log->metadata['resource']);
    }

    public function test_certificate_is_logged(): void
    {
        $this->get('/api/v1/employees/'.$this->employee->id.'/certificate-of-employment')
            ->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('payroll.certificate', $log->metadata['resource']);
    }

    public function test_cnas_declaration_is_logged(): void
    {
        $this->seededRun();

        $this->postJson('/api/v1/social-declarations/cnas-dz', [
            'quarter' => 'Q3',
            'year' => '2026',
        ])->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('payroll.cnas_declaration', $log->metadata['resource']);
    }

    public function test_bank_export_download_is_logged(): void
    {
        [$run] = $this->seededRun();
        Storage::fake('local');
        Storage::disk('local')->put('exports/virement.xml', 'fake-xml');

        /** @var BankExport $export */
        $export = BankExport::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->company->id,
            'format' => 'sepa_xml',
            'file_path' => 'exports/virement.xml',
            'total_amount' => 1000,
            'transfer_count' => 2,
            'status' => BankExport::STATUS_GENERATED,
        ]);

        $this->get("/api/v1/bank-exports/{$export->id}/download")->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('payroll.bank_export', $log->metadata['resource']);
    }

    public function test_accounting_export_is_logged(): void
    {
        $this->seededRun();

        $this->getJson('/api/v1/export/payroll-journal')->assertOk();

        $log = $this->lastSensitiveLog();
        $this->assertNotNull($log);
        $this->assertSame('payroll.accounting_export', $log->metadata['resource']);
    }

    // ── Bornage du volume (échantillonnage configurable) ────────────────────

    public function test_sampling_rate_zero_disables_logging(): void
    {
        config(['security.sensitive_access_logging.sampling_rate' => 0]);
        $this->seededRun();

        $this->getJson('/api/v1/pay-slips')->assertOk();

        $this->assertNull($this->lastSensitiveLog());
    }

    public function test_disabled_flag_disables_logging(): void
    {
        config(['security.sensitive_access_logging.enabled' => false]);
        $this->seededRun();

        $this->getJson('/api/v1/pay-slips')->assertOk();

        $this->assertNull($this->lastSensitiveLog());
    }

    public function test_resource_not_in_allowlist_is_not_logged(): void
    {
        config(['security.sensitive_access_logging.resources' => ['pay_slip.download']]);
        $this->seededRun();

        $this->getJson('/api/v1/pay-slips')->assertOk();

        $this->assertNull($this->lastSensitiveLog());
    }

    // ── Rapport périodique ──────────────────────────────────────────────────

    public function test_sensitive_report_command_lists_resources(): void
    {
        $this->seededRun();
        $this->getJson('/api/v1/pay-slips')->assertOk();
        $this->getJson('/api/v1/export/payroll-journal')->assertOk();

        $exitCode = Artisan::call('audit:sensitive-report', ['--days' => 30]);
        $this->assertSame(0, $exitCode);
        // Artisan::output() DRAINE le buffer : on capture une seule fois avant
        // les assertions (sinon le 2e appel renvoie '' et le test échoue).
        $output = Artisan::output();
        $this->assertStringContainsString('pay_slip.list', $output);
        $this->assertStringContainsString('payroll.accounting_export', $output);
    }
}
