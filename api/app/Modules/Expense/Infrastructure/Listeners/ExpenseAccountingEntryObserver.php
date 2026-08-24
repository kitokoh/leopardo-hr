<?php

declare(strict_types=1);

namespace App\Modules\Expense\Infrastructure\Listeners;

use App\Modules\Expense\Infrastructure\Services\ExpenseAccountingEntryService;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Support\Facades\Log;

/**
 * Issue #5235 — Phase C : déclencheur automatique des écritures de notes de
 * frais.
 *
 * `ExpenseClaimController::approve()` fait un `update()` d'INSTANCE
 * (events Eloquent déclenchés) → cet observer écoute `saved` et génère les
 * écritures quand le claim passe à `approved`.
 *
 * Garanties :
 * - un échec de génération est LOGGUÉ, jamais propagé (l'approbation ne
 *   doit pas casser) ;
 * - idempotence : `generateForClaim()` remplace les lignes du claim ;
 * - hors périmètre : aucune modification d'ExpenseClaimController ni du
 *   modèle ExpenseClaim (lecture seule).
 */
class ExpenseAccountingEntryObserver
{
    public function __construct(
        private readonly ExpenseAccountingEntryService $entries,
    ) {}

    public function saved(ExpenseClaim $claim): void
    {
        // Rejet après approbation (le workflow autorise approved → rejected) :
        // supprimer les écritures — une note rejetée ne doit pas rester au
        // passif comptable (M1 #5235).
        if ($claim->status !== 'approved') {
            if ($claim->getOriginal('status') === 'approved') {
                $this->entries->deleteForClaim($claim);
                Log::info('expense.accounting_entries.removed_on_rejection', [
                    'expense_claim_id' => $claim->id,
                ]);
            }

            return;
        }

        if ($claim->approved_at === null) {
            return;
        }

        try {
            $count = $this->entries->generateForClaim($claim);
            Log::info('expense.accounting_entries.generated_via_observer', [
                'expense_claim_id' => $claim->id,
                'lines' => $count,
            ]);
        } catch (\Throwable $e) {
            // Ne casse jamais l'approbation : la régénération manuelle reste
            // possible via POST /expense-claims/{claim}/accounting-entries/regenerate.
            Log::error('expense.accounting_entries.generation_failed', [
                'expense_claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
