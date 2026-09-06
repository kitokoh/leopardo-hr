<?php

declare(strict_types=1);

namespace App\Modules\Expense\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Expense\Infrastructure\Services\ExpenseAccountingEntryService;
use App\Modules\Planning\Domain\Models\ExpenseClaim;

/**
 * Cas d'usage : annulation des écritures comptables d'une note de frais
 * (rejet d'une note précédemment approuvée — traçabilité issue #5235).
 *
 * Déclenché par l'observer Eloquent
 * (`ExpenseAccountingEntryObserver::updated()`, transition `approved` → autre
 * statut). L'API n'expose pas d'annulation manuelle : le rejet d'une note
 * approuvée passe par le workflow `ExpenseClaimController::reject()`.
 *
 * @return int nombre de lignes supprimées (0 si la note n'avait pas d'écritures)
 */
class VoidExpenseAccountingEntries
{
    public function __construct(
        private readonly ExpenseAccountingEntryService $entries,
    ) {}

    public function execute(ExpenseClaim $claim, ?Employee $actor = null): int
    {
        return $this->entries->voidForClaim($claim, $actor);
    }
}
