<?php

declare(strict_types=1);

namespace App\Modules\CRM\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmLead;

/**
 * #5717 — Policy des leads CRM client.
 *
 * Mêmes rôles de gestion que l'import CSV (#5714) : principal / rh /
 * manager. Le périmètre est toujours borné au tenant de l'acteur.
 */
class CrmLeadPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function view(Employee $actor, CrmLead $lead): bool
    {
        return $this->viewAny($actor) && $lead->company_id === $actor->company_id;
    }

    public function convert(Employee $actor, CrmLead $lead): bool
    {
        return $this->view($actor, $lead);
    }
}
