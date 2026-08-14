<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PaySlipCabinetArchiver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Programme FOCUS — F-09/#1548 (issue #1817) : archivage automatique des
 * bulletins PDF dans le Cabinet employé.
 *
 * Dispatched par `PayrollClosingService::lock()` après verrouillage du run
 * (jamais bloquant pour la clôture : échec de dispatch → warning logué).
 *
 * Implémente `TenantScopedJob` (comme `GeneratePaySlipPdfJob` /
 * `PublishScheduledPostJob`) pour établir le bon `search_path` PostgreSQL
 * avant exécution, indispensable en mode tenancy "schema".
 *
 * File d'attente : `documents` (déjà consommée par le worker Render
 * `--queue=documents,pdf,payroll,notifications,webhooks,default`, cf.
 * AGENTS.md v4.16.212). L'archivage est idempotent (PaySlipCabinetArchiver) :
 * un retry ne crée jamais de doublon.
 */
class ArchivePaySlipsToCabinetJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 180;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $payrollRunId)
    {
        $this->onQueue('documents');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);

        return $this->resolvedCompanyId = $run?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(PaySlipCabinetArchiver $archiver): void
    {
        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);

        if ($run === null) {
            Log::warning("ArchivePaySlipsToCabinetJob: PayrollRun #{$this->payrollRunId} not found.");

            return;
        }

        $result = $archiver->archiveRun($run);

        Log::info(
            "ArchivePaySlipsToCabinetJob: run #{$run->id} — archived={$result['archived']} skipped={$result['skipped']}"
        );
    }
}
