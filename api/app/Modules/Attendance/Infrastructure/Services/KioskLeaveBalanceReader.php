<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * #7962 (suite de #8944) — lecture des soldes de congés d'un employé.
 *
 * Extrait de `KioskController::doLeaveBalance()`. La requête vit dans
 * Infrastructure (facade `DB` interdite en Application par `check-layer-purity`
 * #6568).
 *
 * ## Pourquoi cette classe ne sérialise pas elle-même
 *
 * `KioskDirectoryReader::serializeLeaveBalance()` existe déjà et sert la fiche
 * employé kiosque. Réimplémenter la même projection ici produirait deux formats
 * de solde différents entre deux écrans qui affichent la même chose — le genre
 * d'écart qui ne se voit qu'en production, quand quelqu'un compare les deux.
 * On délègue donc à la projection existante.
 *
 * ## Pourquoi l'année est un paramètre et non `now()->year`
 *
 * L'écrire en dur rendrait impossible de tester le passage d'année sans voyager
 * dans le temps. L'appelant décide de l'année, la requête ne fait que la filtrer.
 */
final class KioskLeaveBalanceReader
{
    public function __construct(private readonly KioskDirectoryReader $directory) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function balancesFor(Company $company, Employee $employee, int $year): array
    {
        $rows = DB::table('leave_balances')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $balances[] = $this->directory->serializeLeaveBalance($row);
        }

        return $balances;
    }
}
