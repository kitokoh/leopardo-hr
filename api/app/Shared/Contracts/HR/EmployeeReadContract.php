<?php

declare(strict_types=1);

namespace App\Shared\Contracts\HR;

use Illuminate\Support\Collection;

/**
 * Lecture seule des employes d'une entreprise (BC-01 HR).
 *
 * Permet aux modules consommateurs (Attendance, Planning, Payroll...) de
 * resoudre un perimetre d'employes SANS importer
 * `Modules\HR\Domain\Models\Employee` — regle d'isolation #5584, tranche 1 du
 * chantier #8061.
 *
 * **Perimetre volontairement etroit.** Ce contrat ne couvre que les deux
 * lectures dont les consommateurs hors-HR ont besoin aujourd'hui :
 *
 *  - `idsVisibleTo()` : le perimetre d'un acteur « scope equipe »
 *    (`manager_role` = `dept` ou `superviseur`), qui sert a filtrer des
 *    requetes sur des tables appartenant au module appelant ;
 *  - `find()` : resoudre un employe par id pour lire son identite.
 *
 * Il n'expose **ni ecriture, ni relation, ni query builder** : un contrat qui
 * rendrait `Employee::query()` ne decouplerait rien, il deplacerait l'import.
 * Toute nouvelle lecture doit etre ajoutee ici explicitement, avec sa
 * justification — c'est ce qui empeche le contrat de devenir un god-object a
 * son tour.
 *
 * Implemente par `HR\Infrastructure\Services\EmployeeReadAdapter`.
 */
interface EmployeeReadContract
{
    /**
     * Identifiants des employes visibles par un acteur « scope equipe ».
     *
     * Reproduit a l'identique `Employee::query()->where('company_id', ...)
     * ->visibleToManager($actor)->pluck('id')` :
     *  - `dept`        -> meme `department_id` que l'acteur ;
     *  - `superviseur` -> `manager_id = acteur` ou l'acteur lui-meme ;
     *  - tout autre role -> aucun filtrage (tout l'effectif de l'entreprise).
     *
     * @return Collection<int, int>
     */
    public function idsVisibleTo(string $companyId, int $actorId): Collection;

    /**
     * Identite minimale d'un employe, ou `null` s'il n'existe pas.
     *
     * @return array{id: int, company_id: string, first_name: string|null, last_name: string|null, matricule: string|null}|null
     */
    public function find(int $employeeId): ?array;
}
