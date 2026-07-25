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
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMvpSchema;
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
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
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

        $this->assertSame(BankExport::STATUS_FAILED, $export->status);
        $this->assertNotNull($export->error_message);
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
