<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Models\PayrollRun;
use App\Payroll\PayrollCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayrollBatchJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        private readonly int $payrollRunId,
        private readonly string $companyId,
    ) {
        $this->onQueue('payroll');
    }

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(): void
    {
        Log::channel('structured')->info('payroll.batch.start', [
            'payroll_run_id' => $this->payrollRunId,
            'company_id' => $this->companyId,
        ]);

        $run = PayrollRun::where('id', $this->payrollRunId)
            ->where('company_id', $this->companyId)
            ->firstOrFail();

        if ($run->status !== 'draft') {
            Log::channel('structured')->warning('payroll.batch.skip', [
                'payroll_run_id' => $this->payrollRunId,
                'status' => $run->status,
            ]);

            return;
        }

        $run->update(['status' => 'processing']);

        try {
            app(PayrollCalculator::class)->calculateRun($run);
            $run->update(['status' => 'calculated']);

            Log::channel('structured')->info('payroll.batch.complete', [
                'payroll_run_id' => $this->payrollRunId,
            ]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'error']);

            Log::channel('structured')->error('payroll.batch.failed', [
                'payroll_run_id' => $this->payrollRunId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return [
            "company:{$this->companyId}",
            "payroll_run:{$this->payrollRunId}",
        ];
    }
}
