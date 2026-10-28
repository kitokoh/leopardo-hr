<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\KioskLeaveBalanceReader;

/**
 * #7962 (suite de #8944) — cas d'usage : soldes de congés affichés au kiosque.
 *
 * Extrait de `KioskController::doLeaveBalance()`. Deux responsabilités
 * distinctes sont réunies ici au lieu d'être mélangées dans le contrôleur :
 *
 * 1. **résoudre l'employé** dans le périmètre tenant — délégué à
 *    `BuildKioskEmployeeInfo::resolveEmployee()` pour qu'il n'existe qu'une
 *    seule implémentation de cette résolution (identifiant e-mail / matricule /
 *    zkteco) entre la fiche employé et les soldes ;
 * 2. **lire les soldes** de l'année demandée.
 *
 * Le contrôleur, lui, décide du 404 et du code HTTP : une Action ne connaît pas
 * le statut de la réponse.
 */
class BuildKioskLeaveBalance
{
    public function __construct(
        private readonly BuildKioskEmployeeInfo $employeeInfo,
        private readonly KioskLeaveBalanceReader $balances,
    ) {}

    /**
     * @return array{employee_name: string, year: int, balances: list<array<string, mixed>>}|null
     *                                                                                                                                  `null` quand l'identifiant ne résout aucun employé du tenant.
     */
    public function execute(Company $company, string $identifier, int $year): ?array
    {
        $employee = $this->employeeInfo->resolveEmployee($company, $identifier);

        if ($employee === null) {
            return null;
        }

        return [
            'employee_name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'year' => $year,
            'balances' => $this->balances->balancesFor($company, $employee, $year),
        ];
    }
}
