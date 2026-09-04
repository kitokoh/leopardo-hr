<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        return [new EnsureTenantContext];
    }

    public function handle(): void
    {
        Log::channel('structured')->info('payroll.batch.start', [
            'payroll_run_id' => $this->payrollRunId,
            'company_id' => $this->companyId,
        ]);

        // #6529 : transition conditionnelle atomique. Un retry après échec
        // (statut `error`) ou un worker mort en plein calcul (statut
        // `processing`) doit pouvoir REPRENDRE le run au lieu de le laisser
        // bloqué pour toujours ; `draft` reste l'état de départ normal.
        // La mise à jour conditionnelle garantit qu'un seul « claim » aboutit
        // (0 ligne affectée = run déjà calculé/validé/verrouillé/payé →
        // skip silencieux, la queue marque le job réussi sans effet de bord).
        $claimed = PayrollRun::query()
            ->where('id', $this->payrollRunId)
            ->where('company_id', $this->companyId)
            ->whereIn('status', [
                PayrollRun::STATUS_DRAFT,
                PayrollRun::STATUS_ERROR,
                PayrollRun::STATUS_PROCESSING,
            ])
            ->update(['status' => PayrollRun::STATUS_PROCESSING]);

        if ($claimed === 0) {
            Log::channel('structured')->warning('payroll.batch.skip', [
                'payroll_run_id' => $this->payrollRunId,
                'status' => PayrollRun::query()
                    ->where('id', $this->payrollRunId)
                    ->where('company_id', $this->companyId)
                    ->value('status'),
            ]);

            return;
        }

        $run = PayrollRun::where('id', $this->payrollRunId)
            ->where('company_id', $this->companyId)
            ->firstOrFail();

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

    /**
     * #4205 : épuisement des retries — log d'alerte, état failed_jobs visible
     * dans l'observabilité (pas de re-lancement silencieux).
     */
    public function failed(Throwable $e): void
    {
        Log::error('ProcessPayrollBatchJob.failed', [
            'payroll_run_id' => $this->payrollRunId,
            'company_id' => $this->companyId,
            'exception' => $e->getMessage(),
        ]);
    }

}
