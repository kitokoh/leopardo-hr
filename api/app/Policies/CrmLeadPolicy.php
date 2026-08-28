<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmLead;

/**
 * Issue #5711 — Policy des leads CRM client (tenant).
 *
 * Les managers `principal`/`rh`/`marketing` gèrent tous les leads du
 * tenant ; un employé non-manager peut uniquement VOIR un lead dont il est
 * l'owner (`owner_id`). Aucune mutation n'est ouverte aux non-managers.
 */
class CrmLeadPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function view(Employee $actor, CrmLead $lead): bool
    {
        return $actor->company_id === $lead->company_id
            && ($actor->hasManagerRole('principal', 'rh', 'marketing') || $lead->owner_id === $actor->id);
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function update(Employee $actor, CrmLead $lead): bool
    {
        return $actor->company_id === $lead->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function delete(Employee $actor, CrmLead $lead): bool
    {
        return $actor->company_id === $lead->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }
}
