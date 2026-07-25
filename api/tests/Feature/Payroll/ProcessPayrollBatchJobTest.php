<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ProcessPayrollBatchJob;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Mockery;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-JOB-004 — Traitements paie asynchrones.
 *
 * `ProcessPayrollBatchJob` is dispatched from `payroll:precalculate`
 * (PA2-PAY-012) so that heavy payroll recalculation runs on a queue worker
 * instead of blocking the manager's UI. Every existing test that touched
 * this job used `Queue::fake()`, which never actually calls `handle()` and
 * therefore never executes its `use App\Payroll\PayrollCalculator;` import
 * — a class that does not exist anywhere in the codebase (the real class
 * is `App\Modules\Payroll\Infrastructure\Services\PayrollCalculator`).
 * Every real run of this job crashed with "Class App\Payroll\PayrollCalculator
 * not found" the moment a queue worker picked it up.
 *
 * This test executes `handle()` for real (no `Queue::fake()`) to catch that
 * class of regression.
 */
class ProcessPayrollBatchJobTest extends TestCase
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
        Mockery::close();
    }

    public function test_handle_calculates_a_draft_run_without_a_missing_class_error(): void
    {
        $company = Company::factory()->create(['status' => 'active']);

        $run = PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'status' => 'draft',
        ]);

        $calculator = Mockery::mock(PayrollCalculator::class);
        $calculator->shouldReceive('calculateRun')
            ->once()
            ->andReturnUsing(function (PayrollRun $arg) {
                return $arg;
            });
        $this->app->instance(PayrollCalculator::class, $calculator);

        $job = new ProcessPayrollBatchJob($run->id, (string) $company->id);
        $job->handle();

        $this->assertSame('calculated', $run->refresh()->status);
    }

    public function test_handle_marks_run_as_error_when_calculation_throws(): void
    {
        $company = Company::factory()->create(['status' => 'active']);

        $run = PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'status' => 'draft',
        ]);

        $calculator = Mockery::mock(PayrollCalculator::class);
        $calculator->shouldReceive('calculateRun')
            ->once()
            ->andThrow(new \RuntimeException('boom'));
        $this->app->instance(PayrollCalculator::class, $calculator);

        $job = new ProcessPayrollBatchJob($run->id, (string) $company->id);

        try {
            $job->handle();
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('error', $run->refresh()->status);
    }

    public function test_handle_skips_a_run_that_is_no_longer_draft(): void
    {
        $company = Company::factory()->create(['status' => 'active']);

        $run = PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'status' => 'validated',
        ]);

        $calculator = Mockery::mock(PayrollCalculator::class);
        $calculator->shouldNotReceive('calculateRun');
        $this->app->instance(PayrollCalculator::class, $calculator);

        $job = new ProcessPayrollBatchJob($run->id, (string) $company->id);
        $job->handle();

        $this->assertSame('validated', $run->refresh()->status);
    }
}
