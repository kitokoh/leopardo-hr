<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ProcessPayrollBatchJob;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PA2-PAY-012 — nightly progressive payroll pre-calculation.
 */
class PrecalculatePayrollRunsCommandTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;

    public function test_dispatches_batch_job_for_a_draft_run_approaching_its_pay_day(): void
    {
        Queue::fake();

        $this->travelTo(Carbon::parse('2026-01-28'));

        $company = Company::factory()->create([
            'status' => 'active',
            'timezone' => 'UTC',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly', 'pay_day' => 30]],
        ]);

        $run = PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => Carbon::parse('2026-01-01'),
            'period_end' => Carbon::parse('2026-01-31'),
            'status' => 'draft',
        ]);

        $cmd = $this->artisan('payroll:precalculate');
        $cmd->assertSuccessful();
        $cmd->run(); // exécution immédiate avant assertions d'état (PendingCommand lazy — convention A-1)

        Queue::assertPushed(ProcessPayrollBatchJob::class, function ($job) use ($run, $company) {
            return $this->jobTargets($job, $run->id, (string) $company->id);
        });
    }

    public function test_skips_run_when_pay_day_is_not_within_the_window(): void
    {
        Queue::fake();

        $this->travelTo(Carbon::parse('2026-01-05'));

        $company = Company::factory()->create([
            'status' => 'active',
            'timezone' => 'UTC',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly', 'pay_day' => 30]],
        ]);

        PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => Carbon::parse('2026-01-01'),
            'period_end' => Carbon::parse('2026-01-31'),
            'status' => 'draft',
        ]);

        $cmd = $this->artisan('payroll:precalculate');
        $cmd->assertSuccessful();
        $cmd->run(); // exécution immédiate avant assertions d'état (PendingCommand lazy — convention A-1)

        Queue::assertNotPushed(ProcessPayrollBatchJob::class);
    }

    public function test_skips_run_belonging_to_a_suspended_company(): void
    {
        Queue::fake();

        $this->travelTo(Carbon::parse('2026-01-28'));

        $company = Company::factory()->create([
            'status' => 'suspended',
            'timezone' => 'UTC',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly', 'pay_day' => 30]],
        ]);

        PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => Carbon::parse('2026-01-01'),
            'period_end' => Carbon::parse('2026-01-31'),
            'status' => 'draft',
        ]);

        $cmd = $this->artisan('payroll:precalculate');
        $cmd->assertSuccessful();
        $cmd->run(); // exécution immédiate avant assertions d'état (PendingCommand lazy — convention A-1)

        Queue::assertNotPushed(ProcessPayrollBatchJob::class);
    }

    public function test_ignores_already_validated_or_cancelled_runs(): void
    {
        Queue::fake();

        $this->travelTo(Carbon::parse('2026-01-28'));

        $company = Company::factory()->create([
            'status' => 'active',
            'timezone' => 'UTC',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly', 'pay_day' => 30]],
        ]);

        PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => Carbon::parse('2026-01-01'),
            'period_end' => Carbon::parse('2026-01-31'),
            'status' => 'validated',
        ]);

        PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => Carbon::parse('2026-01-01'),
            'period_end' => Carbon::parse('2026-01-31'),
            'status' => 'cancelled',
        ]);

        $cmd = $this->artisan('payroll:precalculate');
        $cmd->assertSuccessful();
        $cmd->run(); // exécution immédiate avant assertions d'état (PendingCommand lazy — convention A-1)

        Queue::assertNotPushed(ProcessPayrollBatchJob::class);
    }

    public function test_recalculates_a_previously_calculated_run_again_while_still_due(): void
    {
        Queue::fake();

        $this->travelTo(Carbon::parse('2026-01-29'));

        $company = Company::factory()->create([
            'status' => 'active',
            'timezone' => 'UTC',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly', 'pay_day' => 30]],
        ]);

        $run = PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => Carbon::parse('2026-01-01'),
            'period_end' => Carbon::parse('2026-01-31'),
            'status' => 'calculated',
            'calculated_at' => Carbon::parse('2026-01-27'),
        ]);

        $cmd = $this->artisan('payroll:precalculate');
        $cmd->assertSuccessful();
        $cmd->run(); // exécution immédiate avant assertions d'état (PendingCommand lazy — convention A-1)

        Queue::assertPushed(ProcessPayrollBatchJob::class, function ($job) use ($run, $company) {
            return $this->jobTargets($job, $run->id, (string) $company->id);
        });
    }

    public function test_dry_run_does_not_dispatch_any_job(): void
    {
        Queue::fake();

        $this->travelTo(Carbon::parse('2026-01-28'));

        $company = Company::factory()->create([
            'status' => 'active',
            'timezone' => 'UTC',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly', 'pay_day' => 30]],
        ]);

        PayrollRun::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => Carbon::parse('2026-01-01'),
            'period_end' => Carbon::parse('2026-01-31'),
            'status' => 'draft',
        ]);

        $cmd = $this->artisan('payroll:precalculate', ['--dry-run' => true]);
        $cmd->assertSuccessful();
        $cmd->run(); // exécution immédiate avant assertions d'état (PendingCommand lazy — convention A-1)

        Queue::assertNotPushed(ProcessPayrollBatchJob::class);
    }

    /**
     * `ProcessPayrollBatchJob`'s constructor args are `private readonly`, so
     * assert against it via reflection instead of relying on public getters
     * that don't exist on this job.
     */
    private function jobTargets(ProcessPayrollBatchJob $job, int $payrollRunId, string $companyId): bool
    {
        $ref = new \ReflectionObject($job);

        $actualRunId = $ref->getProperty('payrollRunId');
        $actualRunId->setAccessible(true);

        $actualCompanyId = $ref->getProperty('companyId');
        $actualCompanyId->setAccessible(true);

        return $actualRunId->getValue($job) === $payrollRunId
            && $actualCompanyId->getValue($job) === $companyId;
    }
}
