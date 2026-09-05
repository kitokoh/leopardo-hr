<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduFee;

/**
 * #5832 (EDU-016) — frais scolaires : direction uniquement.
 *
 * La facturation et les encaissements manipulent des données financières et
 * des PII d'élèves : seuls les administrateurs scolaires (principal/rh/
 * manager propriétaire) peuvent créer les frais, facturer, encaisser,
 * abandonner ou consulter les écritures comptables. Un enseignant n'a aucun
 * accès aux frais (EDU_FEE_ADMIN_ONLY).
 *
 * Fichier restauré lors de la consolidation CI 2026-09-04 : l'ancienne
 * version (fusion de branches divergentes) avait perdu l'en-tête PHP et
 * dupliquait des méthodes (update/delete sur EduFee et EduFeeType) →
 * classe invalide. Version canonique = périmètre EduAccess::isAdmin.
 */
class EduFeePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function view(Employee $actor, EduFee $fee): bool
    {
        return $fee->company_id === $actor->company_id && EduAccess::isAdmin($actor);
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduFee $fee): bool
    {
        return $this->view($actor, $fee);
    }

    public function delete(Employee $actor, EduFee $fee): bool
    {
        return $this->view($actor, $fee);
    }
}
