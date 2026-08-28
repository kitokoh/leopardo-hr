<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmOpportunity;

/**
 * Issue #5711 — Policy des opportunités CRM client (tenant).
 *
 * Même modèle que les leads : managers `principal`/`rh`/`marketing` en
 * gestion complète ; un employé non-manager peut uniquement VOIR une
 * opportunité dont il est l'owner (`owner_id`).
 */
class CrmOpportunityPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function view(Employee $actor, CrmOpportunity $opportunity): bool
    {
        return $actor->company_id === $opportunity->company_id
            && ($actor->hasManagerRole('principal', 'rh', 'marketing') || $opportunity->owner_id === $actor->id);
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function update(Employee $actor, CrmOpportunity $opportunity): bool
    {
        return $actor->company_id === $opportunity->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function delete(Employee $actor, CrmOpportunity $opportunity): bool
    {
        return $actor->company_id === $opportunity->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }
}
