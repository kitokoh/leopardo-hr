<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityLease;
use App\Modules\HospitalityManager\Domain\Policies\Concerns\ChecksHospitalityPropertyAccess;

/**
 * HOSP-005 (#7947, BC-32) — Policy des baux locatifs.
 *
 * Comme pour les unités, l'autorisation est portée par l'établissement
 * parent (`property_id`) : lecture = `view`, gestion = `manage`.
 * Cross-tenant → refus (fail-closed).
 *
 * Un bail étant le contrat qui engage un logement, `delete` n'existe pas :
 * on ne supprime pas un bail, on le résilie (`terminate`) — l'historique
 * locatif et les échéances déjà générées doivent rester lisibles.
 */
class HospitalityLeasePolicy
{
    use ChecksHospitalityPropertyAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canViewPropertyResource($actor, null);
    }

    public function view(Employee $actor, HospitalityLease $lease): bool
    {
        return $lease->company_id === $actor->company_id
            && $this->canViewPropertyResource($actor, $lease->getAttribute('property_id'));
    }

    /**
     * Création sous un établissement donné (route imbriquée) : gestion de
     * CET établissement requise.
     */
    public function create(Employee $actor, int $propertyId): bool
    {
        return $this->canManagePropertyResource($actor, $propertyId);
    }

    public function update(Employee $actor, HospitalityLease $lease): bool
    {
        return $lease->company_id === $actor->company_id
            && $this->canManagePropertyResource($actor, $lease->getAttribute('property_id'));
    }

    /** Résiliation / clôture d'un bail. */
    public function terminate(Employee $actor, HospitalityLease $lease): bool
    {
        return $this->update($actor, $lease);
    }
}
