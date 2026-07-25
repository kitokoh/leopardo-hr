<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\GeneratePaymentDocumentJob;
use App\Jobs\GeneratePaySlipPdfJob;
use App\Jobs\ProcessBulkPaymentJob;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-PAY-013 — "Batch resultats partiels notification audit": a bulk
 * payment run must process every pay slip independently (one employee's
 * failure must never abort the others), persist an audit trail of the
 * batch results, and notify the manager who triggered it with the
 * succeeded/failed counts — even when the batch only partially succeeds.
 */
class ProcessBulkPaymentJobTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_bulk_payment_processes_all_slips_marks_run_paid_and_notifies_trigger_on_full_success(): void
    {
        Queue::fake();

        [$company, $manager] = $this->companyAndManager();
        $run = $this->payrollRun($company);
        $employeeA = Employee::factory()->create(['company_id' => $company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id]);
        $this->paySlip($run, $employeeA);
        $this->paySlip($run, $employeeB);

        (new ProcessBulkPaymentJob($run->id, $manager->id))->handle();

        $run->refresh();
        $this->assertSame('paid', $run->status);
        $this->assertNotNull($run->paid_at);

        Queue::assertPushed(GeneratePaySlipPdfJob::class, 2);
        Queue::assertPushed(GeneratePaymentDocumentJob::class, 2);

        $audit = AuditLog::query()
            ->where('auditable_type', PayrollRun::class)
            ->where('auditable_id', $run->id)
            ->where('action', 'bulk_payment_processed')
            ->first();

        $this->assertNotNull($audit, 'Expected an audit_logs entry for the bulk payment batch.');
        $this->assertSame(2, $audit->new_values['succeeded']);
        $this->assertSame(0, $audit->new_values['failed']);
        $this->assertSame('completed', $audit->new_values['status']);

        $notification = Notification::query()
            ->where('employee_id', $manager->id)
            ->where('type', 'payroll')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification, 'Expected the triggering manager to be notified of batch completion.');
    }

    public function test_bulk_payment_continues_processing_after_one_slip_fails_and_reports_partial_results(): void
    {
        Queue::fake();

        [$company, $manager] = $this->companyAndManager();
        $run = $this->payrollRun($company);
        $goodEmployee = Employee::factory()->create(['company_id' => $company->id]);
        $badEmployee = Employee::factory()->create(['company_id' => $company->id]);

        $goodSlip = $this->paySlip($run, $goodEmployee);
        $badSlip = $this->paySlip($run, $badEmployee);

        // Force a real failure for exactly one slip via a thin test double
        // that throws only for $badSlip's id, so we exercise the actual
        // per-slip try/catch + reporting contract without touching
        // database constraints or mocking framework internals.
        $job = new class($run->id, $manager->id, $badSlip->id) extends ProcessBulkPaymentJob
        {
            public function __construct(int $payrollRunId, int $triggeredById, private readonly int $failingSlipId)
            {
                parent::__construct($payrollRunId, $triggeredById);
            }

            protected function processSlip(PayrollRun $run, PaySlip $slip): void
            {
                if ($slip->id === $this->failingSlipId) {
                    throw new RuntimeException('Simulated failure for slip #'.$slip->id);
                }

                parent::processSlip($run, $slip);
            }
        };

        $job->handle();

        $run->refresh();
        // Even with a partial failure, slips that succeeded were genuinely
        // paid and the run must not be left dangling in a non-terminal state.
        $this->assertSame('paid', $run->status);

        $audit = AuditLog::query()
            ->where('auditable_type', PayrollRun::class)
            ->where('auditable_id', $run->id)
            ->where('action', 'bulk_payment_processed')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(2, $audit->new_values['total_slips']);
        $this->assertSame(1, $audit->new_values['succeeded']);
        $this->assertSame(1, $audit->new_values['failed']);
        $this->assertSame('completed_with_errors', $audit->new_values['status']);
        $this->assertCount(1, $audit->metadata['failures']);
        $this->assertSame($badSlip->id, $audit->metadata['failures'][0]['pay_slip_id']);

        // The good slip must always have been processed regardless of the
        // other slip's outcome — this is the "no full-batch abort" contract.
        Queue::assertPushed(
            GeneratePaymentDocumentJob::class,
            fn (GeneratePaymentDocumentJob $job): bool => $job->paymentDocumentId !== null
        );

        $notification = Notification::query()
            ->where('employee_id', $manager->id)
            ->where('type', 'payroll')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification, 'Manager must still be notified even when the batch has partial failures.');
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function companyAndManager(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $manager];
    }

    private function payrollRun(Company $company): PayrollRun
    {
        return PayrollRun::query()->create([
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
    }

    private function paySlip(PayrollRun $run, Employee $employee): PaySlip
    {
        return PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
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
    }
}
