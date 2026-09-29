<?php

declare(strict_types=1);

namespace App\Shared\Contracts\HR;

use Illuminate\Support\Collection;

/**
 * Annuaire partagé des départements (BC-01 HR).
 *
 * Permet aux modules consommateurs (Attendance — rapports de pointage…) de
 * lire les noms de départements SANS import croisé `Modules/X -> Modules/HR`
 * (règle d'isolation #5584, chantier BOS-023 #8211, cycle 3) — ils ne
 * dépendent que de ce contrat, implémenté par
 * `HR\Infrastructure\Services\DepartmentDirectoryAdapter`.
 *
 * La requête reprend à l'identique celle qu'`AttendanceReportService`
 * (Attendance) exécutait historiquement : mêmes filtre et projection —
 * comportement strictement préservé.
 */
interface DepartmentDirectory
{
    /**
     * Noms des départements d'une entreprise, indexés par id (id => name).
     * L'identifiant entreprise est l'UUID canonique (`Company::$id`).
     *
     * @return Collection<int, string>
     */
    public function namesByCompany(string $companyId): Collection;
}
