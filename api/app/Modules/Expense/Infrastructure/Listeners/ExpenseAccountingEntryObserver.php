<?php

declare(strict_types=1);

namespace App\Modules\Expense\Infrastructure\Listeners;

use App\Modules\Expense\Application\Actions\GenerateExpenseAccountingEntries;
use App\Modules\Expense\Application\Actions\VoidExpenseAccountingEntries;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Support\Facades\Log;

/**
 * Issue #5235 — Phase C : déclencheur automatique des écritures des notes de
 * frais.
 *
 * L'approbation d'une `ExpenseClaim` passe par `ExpenseClaimController::approve()`
 * → `$expenseClaim->update([...])` : un event Eloquent `updated` d'instance
 * est bien émis (contrairement à la validation payroll, mass-update sans
 * event, cf. #5239). Cet observer écoute la transition de statut et génère
 * les écritures comptables.
 *
 * Garanties :
 * - un échec de génération est LOGGUÉ, jamais propagé (l'approbation ne
 *   doit pas casser) ;
 * - idempotence : `generateForClaim()` remplace les lignes de la note ;
 * - rejet d'une note approuvée → écritures supprimées (traçabilité) ;
 * - hors périmètre : aucune modification de `ExpenseClaimController` ni du
 *   moteur Expense existant.
 */
class ExpenseAccountingEntryObserver
{
    public function __construct(
        private readonly GenerateExpenseAccountingEntries $generateEntries,
        private readonly VoidExpenseAccountingEntries $voidEntries,
    ) {}

    public function updated(ExpenseClaim $claim): void
    {
        $previousStatus = $claim->getOriginal('status');
        $newStatus = $claim->status;

        // Transition vers `approved` → génération automatique.
        if ($newStatus === 'approved' && $previousStatus !== 'approved') {
            try {
                $result = $this->generateEntries->execute($claim);
                Log::info('expense.accounting_entries.generated_via_observer', [
                    'expense_claim_id' => $claim->id,
                    'lines' => $result['generated_lines'],
                ]);
            } catch (\Throwable $e) {
                // Ne casse jamais l'approbation : la régénération manuelle
                // reste possible via POST /expense-claims/{id}/accounting-entries/regenerate.
                Log::error('expense.accounting_entries.generation_failed', [
                    'expense_claim_id' => $claim->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        // Transition depuis `approved` (rejet) → annulation des écritures.
        if ($previousStatus === 'approved' && $newStatus !== 'approved') {
            $this->voidEntries->execute($claim);
        }
    }
}
