<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ProcessBulkPaymentJob;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-PAY-005 — "Selection multiple batch async recap erreurs partielles":
 * a manager must be able to select a specific subset of pay slips to pay
 * in a batch instead of always paying the whole run, with the batch still
 * processed asynchronously.
 */
class BulkPaymentControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_can_bulk_pay_a_selected_subset_of_pay_slips(): void
    {
        Bus::fake();

        [$company, $manager, $run, $slipA, $slipB] = $this->fixture();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay", [
            'pay_slip_ids' => [$slipA->id],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('payroll_run_id', $run->id)
            ->assertJsonPath('selected_pay_slip_count', 1);

        Bus::assertDispatched(
            ProcessBulkPaymentJob::class,
            fn (ProcessBulkPaymentJob $job): bool => $job->payrollRunId === $run->id
                && $job->triggeredById === $manager->id
                && $job->paySlipIds === [$slipA->id]
        );
    }

    public function test_manager_can_bulk_pay_the_whole_run_when_no_selection_is_given(): void
    {
        Bus::fake();

        [$company, $manager, $run] = $this->fixture();
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay");

        $response->assertAccepted()
            ->assertJsonPath('selected_pay_slip_count', null);

        Bus::assertDispatched(
            ProcessBulkPaymentJob::class,
            fn (ProcessBulkPaymentJob $job): bool => $job->payrollRunId === $run->id
                && $job->paySlipIds === null
        );
    }

    /**
     * @return array{0: Company, 1: Employee, 2: PayrollRun, 3: PaySlip, 4: PaySlip}
     */
    private function fixture(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'validated',
            'employee_count' => 2,
            'total_gross' => 240000,
            'total_deductions' => 44000,
            'total_net' => 196000,
        ]);

        $employeeA = Employee::factory()->create(['company_id' => $company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id]);

        $slipA = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
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

        $slipB = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
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

        return [$company, $manager, $run, $slipA, $slipB];
    }
}
