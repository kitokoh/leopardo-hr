<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-2 (#1662) — Journalisation des accès aux données sensibles.
 *
 * Les lectures de ressources sensibles (bulletins, exports, journal de paie,
 * certificat, fin de contrat, exports bancaires) sont tracées dans
 * `audit_logs` via `DataAccessAuditLogger` (metadata.category =
 * 'hr_data_access'), avec échantillonnage configurable et rapport
 * `audit:sensitive-report`.
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

        Date::setTestNow(Carbon::parse('2026-07-15T12:00:00+00:00'));

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
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function seededRun(): array
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
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
            'total_deductions' => 12000,
            'net_salary' => 48000,
            'status' => 'validated',
        ]);

        return [$run, $slip];
    }

    public function test_pay_slip_download_is_traced(): void
    {
        [, $slip] = $this->seededRun();
        Storage::disk('local')->put("pay-slips/test-{$slip->id}.pdf", '%PDF-fake');
        $slip->update(['pdf_path' => "pay-slips/test-{$slip->id}.pdf"]);

        Sanctum::actingAs($this->employee);

        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/pdf")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'action' => 'hr_data.pay_slip_downloaded',
            'auditable_type' => (new PaySlip)->getMorphClass(),
            'auditable_id' => $slip->id,
        ]);

        $log = AuditLog::query()->where('action', 'hr_data.pay_slip_downloaded')->first();
        $this->assertSame('hr_data_access', $log->metadata['category']);
    }

    public function test_pay_slip_view_by_manager_is_traced(): void
    {
        [, $slip] = $this->seededRun();

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/pay-slips/{$slip->id}")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'hr_data.pay_slip_viewed',
            'auditable_id' => $slip->id,
        ]);
    }

    public function test_payroll_journal_export_is_traced(): void
    {
        [$run] = $this->seededRun();

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'hr_data.payroll_journal_exported',
            'auditable_id' => $run->id,
        ]);
    }

    public function test_bank_export_download_is_traced(): void
    {
        [$run] = $this->seededRun();

        /** @var BankExport $bankExport */
        $bankExport = BankExport::create([
            'company_id' => $this->company->id,
            'payroll_run_id' => $run->id,
            'status' => BankExport::STATUS_GENERATED,
            'format' => 'sepa_xml',
            'file_path' => 'bank-exports/test-export.xml',
        ]);
        Storage::disk('local')->put('bank-exports/test-export.xml', '<xml>fake</xml>');

        Sanctum::actingAs($this->manager);

        $this->get("/api/v1/bank-exports/{$bankExport->id}/download")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'hr_data.bank_export_downloaded',
            'auditable_id' => $bankExport->id,
        ]);
    }

    public function test_end_of_contract_settlement_is_traced(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/employees/{$this->employee->id}/end-of-contract")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'hr_data.end_of_contract_viewed',
            'auditable_id' => $this->employee->id,
        ]);
    }

    public function test_certificate_download_is_traced(): void
    {
        Sanctum::actingAs($this->manager);

        $this->get("/api/v1/employees/{$this->employee->id}/certificate-of-employment")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'hr_data.certificate_downloaded',
            'auditable_id' => $this->employee->id,
        ]);
    }

    public function test_export_endpoint_is_traced(): void
    {
        $this->seededRun();

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/export/pay-slips')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'hr_data.export',
        ]);

        $log = AuditLog::query()->where('action', 'hr_data.export')->first();
        $this->assertSame('pay_slips_export', $log->metadata['export']);
    }

    public function test_sampling_rate_zero_disables_logging(): void
    {
        [, $slip] = $this->seededRun();
        Storage::disk('local')->put("pay-slips/test-{$slip->id}.pdf", '%PDF-fake');
        $slip->update(['pdf_path' => "pay-slips/test-{$slip->id}.pdf"]);

        config(['audit.data_access.sample_rate' => 0.0]);

        Sanctum::actingAs($this->employee);

        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/pdf")->assertOk();

        $this->assertDatabaseMissing('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'hr_data.pay_slip_downloaded',
        ]);
    }

    public function test_sampling_is_deterministic_per_actor_and_action(): void
    {
        [, $slip] = $this->seededRun();
        Storage::disk('local')->put("pay-slips/test-{$slip->id}.pdf", '%PDF-fake');
        $slip->update(['pdf_path' => "pay-slips/test-{$slip->id}.pdf"]);

        // Taux partiel : le choix est déterministe (crc32 acteur|action),
        // jamais aléatoire par requête — testé ici avec le même calcul.
        config(['audit.data_access.sample_rate' => 0.5]);
        $bucket = (((int) crc32('hr_data.pay_slip_downloaded|'.$this->employee->id)) % 10000) / 10000;

        Sanctum::actingAs($this->employee);

        $this->getJson("/api/v1/me/pay-slips/{$slip->id}/pdf")->assertOk();

        $exists = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('action', 'hr_data.pay_slip_downloaded')
            ->exists();

        $this->assertSame($bucket < 0.5, $exists);
    }

    public function test_sensitive_report_command_aggregates_accesses(): void
    {
        [$run] = $this->seededRun();

        Sanctum::actingAs($this->manager);
        $this->getJson("/api/v1/payroll-runs/{$run->id}/journal")->assertOk();
        $this->getJson("/api/v1/export/pay-slips")->assertOk();

        $this->artisan('audit:sensitive-report', ['--since' => '2026-07-01'])
            ->expectsOutputToContain('hr_data.payroll_journal_exported')
            ->expectsOutputToContain('hr_data.export')
            ->assertExitCode(0);
    }
}
