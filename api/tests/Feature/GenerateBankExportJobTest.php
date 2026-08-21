<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\GenerateBankExportJob;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-PAY-014 — GenerateBankExportJob::handle() exercised directly, since
 * BankExportControllerTest only asserts the job is dispatched (Queue::fake).
 * Verifies the pending -> generating -> generated/failed lifecycle, that
 * the file is actually written to disk, and that a failure is recorded on
 * the BankExport row with error_message instead of leaving it stuck as
 * "generating".
 */
class GenerateBankExportJobTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_job_generates_csv_file_and_marks_export_generated(): void
    {
        [$company, $employee] = $this->companyAndEmployee();
        [$run] = $this->payrollSlip($company, $employee);

        $export = BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'csv_generic',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_PENDING,
        ]);

        (new GenerateBankExportJob($export->id))->handle(app(BankExportGenerator::class));

        $export->refresh();

        $this->assertSame(BankExport::STATUS_GENERATED, $export->status);
        $this->assertNotNull($export->file_path);
        $this->assertNotNull($export->generated_at);
        $this->assertNull($export->error_message);
        $this->assertSame(1, $export->transfer_count);
        $this->assertSame(98000.0, (float) $export->total_amount);

        $this->assertTrue(Storage::disk('local')->exists($export->file_path));
    }

    public function test_job_marks_export_failed_and_rethrows_when_payroll_run_is_missing(): void
    {
        [$company] = $this->companyAndEmployee();

        // Le scénario « run manquant » est défensif (le FK bank_exports →
        // payroll_runs empêche normalement une référence orpheline) : on crée
        // la ligne avec les FKs désactivés pour cette session, comme le ferait
        // une base migrée avant l'ajout de la contrainte.
        // #5178 : le scénario « run manquant » est défensif (le FK
        // bank_exports → payroll_runs empêche normalement une référence
        // orpheline). On désactive les FKs pour TOUTE la durée du job : le
        // job met à jour la ligne (`status => generating` puis `failed`),
        // chaque UPDATE re-déclencherait sinon la FK → 23503 → 25P02
        // (transaction avortée). Restauration en finally.
        DB::statement('SET session_replication_role = replica');
        try {
            $export = BankExport::query()->create([
                'payroll_run_id' => 999_999,
                'company_id' => $company->id,
                'format' => 'csv_generic',
                'file_path' => null,
                'total_amount' => 0,
                'transfer_count' => 0,
                'status' => BankExport::STATUS_PENDING,
            ]);

            $thrown = null;

            try {
                (new GenerateBankExportJob($export->id))->handle(app(BankExportGenerator::class));
            } catch (\Throwable $e) {
                $thrown = $e;
            }

            $this->assertNotNull($thrown, 'Expected the job to rethrow the missing payroll run failure.');

            $export->refresh();
        } finally {
            DB::statement('SET session_replication_role = DEFAULT');
        }

        $this->assertSame(BankExport::STATUS_FAILED, $export->status);
        $this->assertNotNull($export->error_message);
        $this->assertNull($export->file_path);
    }

    public function test_job_generates_sepa_xml_with_company_bank_details_from_metadata(): void
    {
        [$company, $employee] = $this->companyAndEmployee();

        $company->forceFill([
            'metadata' => [
                'bank' => [
                    'iban' => 'FR7630006000011234567890189',
                    'bic' => 'AGRIFRPP',
                ],
            ],
        ])->save();

        [$run, $slip] = $this->payrollSlip($company, $employee);
        $slip->forceFill(['status' => 'validated'])->save();

        $export = BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'sepa_xml',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_PENDING,
        ]);

        (new GenerateBankExportJob($export->id))->handle(app(BankExportGenerator::class));

        $export->refresh();

        $this->assertSame(BankExport::STATUS_GENERATED, $export->status);
        $this->assertNull($export->error_message);

        $this->assertNotNull($export->file_path, 'Expected a generated file path.');
        $content = Storage::disk('local')->get($export->file_path);
        $this->assertIsString($content);
        $this->assertStringContainsString(
            '<DbtrAcct><Id><IBAN>FR7630006000011234567890189</IBAN></Id></DbtrAcct>',
            $content
        );
        $this->assertStringContainsString(
            '<DbtrAgt><FinInstnId><BIC>AGRIFRPP</BIC></FinInstnId></DbtrAgt>',
            $content
        );
        $this->assertStringNotContainsString('PLACEHOLDER', $content);
    }

    public function test_job_marks_sepa_export_failed_when_company_bank_details_are_missing(): void
    {
        [$company, $employee] = $this->companyAndEmployee();
        [$run, $slip] = $this->payrollSlip($company, $employee);
        $slip->forceFill(['status' => 'validated'])->save();

        $export = BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'sepa_xml',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_PENDING,
        ]);

        $thrown = null;

        try {
            (new GenerateBankExportJob($export->id))->handle(app(BankExportGenerator::class));
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Expected the job to rethrow the missing bank details failure.');

        $export->refresh();

        $this->assertSame(BankExport::STATUS_FAILED, $export->status);
        $this->assertStringContainsString('Configuration bancaire entreprise manquante', (string) $export->error_message);
        $this->assertNull($export->file_path);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function companyAndEmployee(): array
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [$company, $employee];
    }

    /**
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function payrollSlip(Company $company, Employee $employee): array
    {
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'validated',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 22000,
            'net_salary' => 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        return [$run, $slip];
    }
}
