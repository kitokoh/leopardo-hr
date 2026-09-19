<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\TravelAgency\Domain\Models\TravelStaffAssignment;

/**
 * #7638 (TRAVEL-STAFF) — Policy des affectations d'équipage.
 *
 * Affecter/révoquer un employé sur un bureau ou un voyage est un geste de
 * GESTION : historiquement réservé à principal/rh, composé avec le RBAC
 * ressource-scopé #7600 — un responsable porteur de `manage` sur le bureau
 * concerné (`travel_office`) gère son équipage sans être principal.
 * Lecture ouverte à tout employé du tenant (même schéma que TravelOffice).
 */
class TravelStaffAssignmentPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelStaffAssignment $assignment): bool
    {
        return $assignment->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        // Sans identifiant de bureau : décision company-wide (cf. TravelOfficePolicy::create).
        return $this->canManageScopedResource($actor, 'travel_office', null, $actor->hasManagerRole('principal', 'rh'));
    }

    public function update(Employee $actor, TravelStaffAssignment $assignment): bool
    {
        return $assignment->company_id === $actor->company_id
            && $this->canManageScopedResource($actor, 'travel_office', $assignment->office_id, $actor->hasManagerRole('principal', 'rh'));
    }

    public function revoke(Employee $actor, TravelStaffAssignment $assignment): bool
    {
        return $this->update($actor, $assignment);
    }

    public function delete(Employee $actor, TravelStaffAssignment $assignment): bool
    {
        return $this->update($actor, $assignment);
    }
}
