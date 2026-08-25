<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Listeners;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollAccountingEntryService;
use Illuminate\Support\Facades\Log;

/**
 * Issue #5239 — Phase C : déclencheur automatique des écritures salariales.
 *
 * `PayrollClosingService::validateRh()` écrit un `AuditLog` d'action
 * `payroll_run_validated` (comportement stable, mergé — le run est validé
 * par mass-update, donc AUCUN event Eloquent d'instance n'est émis ; l'audit
 * est le seul signal fiable de la transition). Cet observer écoute la
 * création de cet audit et génère les écritures comptables.
 *
 * Garanties :
 * - un échec de génération est LOGGUÉ, jamais propagé (la validation RH ne
 *   doit pas casser) ;
 * - idempotence : `generateForRun()` remplace les lignes du run ;
 * - hors périmètre : aucune modification de PayrollClosingService /
 *   PayrollRunController (PRs #5358/#5339 en cours).
 */
class PayrollAccountingEntryObserver
{
    public function __construct(
        private readonly PayrollAccountingEntryService $entries,
    ) {}

    public function created(AuditLog $auditLog): void
    {
        if ($auditLog->action !== 'payroll_run_validated') {
            return;
        }

        if ($auditLog->auditable_type !== PayrollRun::class) {
            return;
        }

        $run = PayrollRun::query()->find($auditLog->auditable_id);
        if (! $run instanceof PayrollRun) {
            Log::warning('payroll.accounting_entries.skipped_run_not_found', [
                'audit_log_id' => $auditLog->id,
                'auditable_id' => $auditLog->auditable_id,
            ]);

            return;
        }

        try {
            $count = $this->entries->generateForRun($run);
            Log::info('payroll.accounting_entries.generated_via_observer', [
                'payroll_run_id' => $run->id,
                'lines' => $count,
                'audit_log_id' => $auditLog->id,
            ]);
        } catch (\Throwable $e) {
            // Ne casse jamais la validation : la régénération manuelle reste
            // possible via POST /payroll-runs/{run}/accounting-entries/regenerate.
            Log::error('payroll.accounting_entries.generation_failed', [
                'payroll_run_id' => $run->id,
                'error' => $e->getMessage(),
                'audit_log_id' => $auditLog->id,
            ]);
        }
    }
}
