<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Listeners;

use App\Events\PayrollRunValidated;
use App\Modules\Accounting\Infrastructure\Services\PayrollJournalEntryService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issue #5239 — persistance idempotente des écritures salariales à la
 * validation d'un run de paie (événement additif PayrollRunValidated).
 *
 * Additif et non bloquant : la validation du run ne dépend JAMAIS du succès
 * de l'écriture comptable (aucune modification du moteur Payroll — FOCUS
 * intact). En cas d'échec (ex. plan comptable pays indisponible), on loggue
 * et le rattrapage est possible via la commande
 * `accounting:generate-payroll-entries --run={id}`.
 */
class GeneratePayrollJournalEntries
{
    public function __construct(
        private readonly PayrollJournalEntryService $entries,
    ) {}

    public function handle(PayrollRunValidated $event): void
    {
        try {
            $result = $this->entries->generateForRun($event->payrollRun, $event->actorId);

            Log::info('accounting.journal_entries_generated', [
                'run_id' => $result['run_id'],
                'status' => $result['status'],
                'generated' => $result['generated'],
            ]);
        } catch (Throwable $exception) {
            Log::warning('accounting.journal_entries_generation_failed', [
                'run_id' => $event->payrollRun->id,
                'company_id' => $event->payrollRun->company_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
