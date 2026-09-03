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

        $run = PayrollRun::query()
            ->where('id', $this->payrollRunId)
            ->where('company_id', $this->companyId)
            ->firstOrFail();

        // #6529 (audit fiabilité 2026-08-31) — claim conditionnel ATOMIQUE :
        // la transition est portée par l'UPDATE lui-même (`whereIn(status)`
        // dans le WHERE de l'update, lignes affectées contrôlées). Avant ce
        // correctif, le job skipait si le statut n'était plus `draft` APRÈS
        // l'avoir passé en `processing`/`error` dans le catch : un échec
        // transitoire laissait le run en `error`/`processing` pour toujours,
        // le retry se sabordait silencieusement (retour normal = réussi) et
        // aucun chemin API de recalcul n'existait.
        //
        // Statuts rejouables :
        //   - draft       : exécution normale ;
        //   - error       : retry après un échec transitoire (catch ci-dessous) ;
        //   - processing  : run orphelin laissé par un worker mort entre le
        //     claim et le calcul (le redélivrage queue n'intervient qu'après
        //     retry_after ≥ timeout, cf. #6535 — pas de double exécution).
        // Tout autre statut (calculated/validated/paid/locked/cancelled) est
        // terminal : 0 ligne affectée → skip silencieux conservé.
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
                'status' => $run->status,
            ]);

            return;
        }

        try {
            // Relecture après claim : le run frais porte `processing` (et les
            // totaux éventuellement partiels d'une tentative précédente).
            $freshRun = PayrollRun::query()
                ->where('id', $this->payrollRunId)
                ->where('company_id', $this->companyId)
                ->firstOrFail();

            app(PayrollCalculator::class)->calculateRun($freshRun);
            $freshRun->update(['status' => PayrollRun::STATUS_CALCULATED]);

            Log::channel('structured')->info('payroll.batch.complete', [
                'payroll_run_id' => $this->payrollRunId,
            ]);
        } catch (Throwable $e) {
            // #6529 — état `error` VISIBLE et RECALCULABLE (le contrôleur
            // accepte désormais draft|calculated|error|processing) : le retry
            // queue ou l'API peuvent reprendre le run.
            PayrollRun::query()
                ->where('id', $this->payrollRunId)
                ->where('company_id', $this->companyId)
                ->update(['status' => PayrollRun::STATUS_ERROR]);

            Log::channel('structured')->error('payroll.batch.failed', [
                'payroll_run_id' => $this->payrollRunId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<int, string>
     */
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
