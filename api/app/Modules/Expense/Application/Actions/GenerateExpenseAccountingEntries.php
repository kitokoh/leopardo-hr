<?php

declare(strict_types=1);

namespace App\Modules\Expense\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Expense\Domain\Exceptions\UnbalancedExpenseEntriesException;
use App\Modules\Expense\Infrastructure\Services\ExpenseAccountingEntryService;
use App\Modules\Planning\Domain\Models\ExpenseClaim;

/**
 * Cas d'usage : génération (ou régénération) des écritures comptables d'une
 * note de frais approuvée (issue #5235, Phase C — notes de frais → écritures
 * comptables, partie double débit charge / crédit personnel).
 *
 * Orchestration pure et nommable, réutilisée par deux déclencheurs :
 * - l'API comptable (`ExpenseAccountingController::regenerate()`, régénération
 *   manuelle idempotente) ;
 * - l'observer Eloquent (`ExpenseAccountingEntryObserver::updated()`,
 *   génération automatique à l'approbation).
 *
 * La persistance et le calcul du journal restent dans
 * `ExpenseAccountingEntryService` (Infrastructure) — cette Action ne fait que
 * porter le cas d'usage et son résultat agrégé (nombre de lignes + solde),
 * sans dupliquer la politique métier.
 *
 * @throws \RuntimeException si la note n'est pas approuvée (statut ≠ approved)
 * @throws UnbalancedExpenseEntriesException si le journal est déséquilibré
 *                                           (défense en profondeur — l'équilibre est garanti par construction)
 */
class GenerateExpenseAccountingEntries
{
    public function __construct(
        private readonly ExpenseAccountingEntryService $entries,
    ) {}

    /**
     * @return array{generated_lines: int, balance: float}
     */
    public function execute(ExpenseClaim $claim, ?Employee $actor = null): array
    {
        $generatedLines = $this->entries->generateForClaim($claim, $actor);

        return [
            'generated_lines' => $generatedLines,
            'balance' => $this->entries->balanceForClaim($claim),
        ];
    }
}
