<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeeType;

/**
 * #5832 (EDU-016) — frais scolaires : direction uniquement.
 *
 * La facturation et les encaissements manipulent des données financières et
 * des PII d'élèves : seuls les administrateurs scolaires (principal/rh/
 * manager propriétaire) peuvent créer les tarifs, facturer, encaisser,
 * abandonner ou consulter les écritures comptables. Un enseignant n'a aucun
 * accès aux frais (EDU_FEE_ADMIN_ONLY).
 */
class EduFeePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function view(Employee $actor, EduFeeType $feeType): bool
    {
        return $feeType->company_id === $actor->company_id && EduAccess::isAdmin($actor);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduFeeType $feeType): bool
    {
        return $this->view($actor, $feeType);
    }

    public function delete(Employee $actor, EduFeeType $feeType): bool
    {
        return $this->view($actor, $feeType);
    }

    public function viewAnyCharges(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function viewCharge(Employee $actor, EduFeeCharge $charge): bool
    {
        return $charge->company_id === $actor->company_id && EduAccess::isAdmin($actor);
    }

    public function createCharge(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function recordPayment(Employee $actor, EduFeeCharge $charge): bool
    {
        return $this->viewCharge($actor, $charge);
    }

    public function waive(Employee $actor, EduFeeCharge $charge): bool
    {
        return $this->viewCharge($actor, $charge);
    }

    public function cancel(Employee $actor, EduFeeCharge $charge): bool
    {
        return $this->viewCharge($actor, $charge);
    }

    /**
     * Écritures comptables : permissions comptables = direction scolaire.
     */
    public function viewEntries(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }
}
