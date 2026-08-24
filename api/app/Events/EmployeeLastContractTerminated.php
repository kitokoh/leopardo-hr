<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Issue #5327 (G4) — le DERNIER contrat actif/suspendu d'un employé vient
 * d'être terminé : l'employé n'a plus de contrat en cours.
 *
 * Hook pour le workflow de départ (#5324) : ce listener pourra enregistrer
 * le départ formel (`employee_departures` + statut `departed`) sans que
 * ContractLifecycleAction dépende de la table (pas encore mergée sur tous
 * les environnements). L'événement ne porte QUE les identifiants — aucun
 * calcul de paie ici (constitution §III).
 */
class EmployeeLastContractTerminated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly Contract $contract,
    ) {}
}
