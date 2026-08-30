<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Accounting\Application\Actions\AccountingReportingSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * BC-22-D10 (issue #6243) — recompute tenant-scoped d'un snapshot de read model.
 *
 * - Retry borné (`tries 3`) ; timeout 120 s ;
 * - contexte tenant établi par `EnsureTenantContext` (search_path +
 *   `current_company`) — jamais de fuite cross-tenant ;
 * - observabilité : log structuré corrélé (`reporting.snapshot.recomputed`).
 *
 * Usage : `RecomputeAccountingReportingSnapshotJob::dispatch($companyId)`.
 */
class RecomputeAccountingReportingSnapshotJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $companyId,
        public readonly string $report = AccountingReportingSnapshotService::REPORT_ACCOUNTING_DASHBOARD,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
        $this->onQueue('default');
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
        app(AccountingReportingSnapshotService::class)
            ->recompute($this->companyId, $this->report, $this->from, $this->to);

        Log::channel('structured')->info('reporting.snapshot.recomputed', [
            'company_id' => $this->companyId,
            'report' => $this->report,
            'period_from' => $this->from,
            'period_to' => $this->to,
        ]);
    }
}
