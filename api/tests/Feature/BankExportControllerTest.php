<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\GenerateBankExportJob;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-PAY-014 — bank export generation must never block the HTTP request:
 * generate() only creates a `pending` BankExport row and dispatches
 * GenerateBankExportJob; the file itself is produced asynchronously.
 */
class BankExportControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_generate_creates_pending_export_and_dispatches_job_without_building_file(): void
    {
        Queue::fake();

        [$company, $manager, $employee] = $this->actors();
        [$run] = $this->payrollSlip($company, $employee);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/bank-export", [
            'format' => 'sepa_xml',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', BankExport::STATUS_PENDING)
            ->assertJsonPath('data.file_path', null);

        $export = BankExport::query()->where('payroll_run_id', $run->id)->first();

        $this->assertNotNull($export);
        $this->assertSame(BankExport::STATUS_PENDING, $export->status);
        $this->assertNull($export->file_path);

        Queue::assertPushed(GenerateBankExportJob::class, fn (GenerateBankExportJob $job): bool => $job->bankExportId === $export->id);
    }

    public function test_generate_requires_manager_role(): void
    {
        Queue::fake();

        [$company, , $employee] = $this->actors();
        [$run] = $this->payrollSlip($company, $employee);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/bank-export", [
            'format' => 'csv_generic',
        ])->assertForbidden();
    }

    public function test_generate_rejects_payroll_run_from_another_company(): void
    {
        Queue::fake();

        [$company, $manager, $employee] = $this->actors();
        [, $slip] = $this->payrollSlip($company, $employee);

        [$foreignCompany, , $foreignEmployee] = $this->actors();
        [$foreignRun] = $this->payrollSlip($foreignCompany, $foreignEmployee);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$foreignRun->id}/bank-export", [
            'format' => 'csv_generic',
        ])->assertNotFound();
    }

    public function test_generate_rejects_payroll_run_not_yet_validated(): void
    {
        Queue::fake();

        [$company, $manager, $employee] = $this->actors();
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'draft',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/bank-export", [
            'format' => 'csv_generic',
        ])->assertStatus(422);

        Queue::assertNotPushed(GenerateBankExportJob::class);
    }

    public function test_show_returns_export_status_and_error_message(): void
    {
        [$company, $manager, $employee] = $this->actors();
        [$run] = $this->payrollSlip($company, $employee);

        $export = BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'sepa_xml',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_FAILED,
            'error_message' => 'Bank export generator crashed.',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/bank-exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data.status', BankExport::STATUS_FAILED)
            ->assertJsonPath('data.error_message', 'Bank export generator crashed.');
    }

    public function test_download_while_generating_returns_conflict(): void
    {
        [$company, $manager, $employee] = $this->actors();
        [$run] = $this->payrollSlip($company, $employee);

        $export = BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'sepa_xml',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_GENERATING,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/bank-exports/{$export->id}/download")
            ->assertStatus(409)
            ->assertJsonPath('status', BankExport::STATUS_GENERATING);
    }

    public function test_download_after_failure_returns_conflict_with_error_message(): void
    {
        [$company, $manager, $employee] = $this->actors();
        [$run] = $this->payrollSlip($company, $employee);

        $export = BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'sepa_xml',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_FAILED,
            'error_message' => 'Disk full.',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/bank-exports/{$export->id}/download")
            ->assertStatus(409)
            ->assertJsonPath('status', BankExport::STATUS_FAILED);
    }

    public function test_download_generated_export_streams_the_file(): void
    {
        Storage::fake('local');

        [$company, $manager, $employee] = $this->actors();
        [$run] = $this->payrollSlip($company, $employee);

        $path = 'bank_exports/'.$company->id.'_2026_05_sepa_xml.xml';
        Storage::disk('local')->put($path, '<Document></Document>');

        $export = BankExport::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'format' => 'sepa_xml',
            'file_path' => $path,
            'total_amount' => 98000,
            'transfer_count' => 1,
            'status' => BankExport::STATUS_GENERATED,
            'generated_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $this->get("/api/v1/bank-exports/{$export->id}/download")
            ->assertOk();
    }

    public function test_show_rejects_bank_export_from_another_company(): void
    {
        [$company, $manager, $employee] = $this->actors();
        [$run] = $this->payrollSlip($company, $employee);

        [$foreignCompany, , $foreignEmployee] = $this->actors();
        [$foreignRun] = $this->payrollSlip($foreignCompany, $foreignEmployee);

        $foreignExport = BankExport::query()->create([
            'payroll_run_id' => $foreignRun->id,
            'company_id' => $foreignCompany->id,
            'format' => 'sepa_xml',
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/bank-exports/{$foreignExport->id}")->assertNotFound();
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function actors(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [$company, $manager, $employee];
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
