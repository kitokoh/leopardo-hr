<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationFollowUp;

/**
 * RBAC de la file de relances (BC-29 COMMUNICATION, R4 #7689).
 *
 * Les echeances d'une boite ne sont visibles/annulables que par son
 * PROPRIETAIRE (meme principal/rh = 403) — pattern des policies R2/R3.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationFollowUpPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CommunicationFollowUp $followUp): bool
    {
        return $this->ownsMailbox($actor, $followUp);
    }

    /**
     * Annulation manuelle d'une echeance encore `pending`.
     */
    public function cancel(Employee $actor, CommunicationFollowUp $followUp): bool
    {
        return $this->ownsMailbox($actor, $followUp);
    }

    private function ownsMailbox(Employee $actor, CommunicationFollowUp $followUp): bool
    {
        $integration = $followUp->integration;

        return $integration !== null
            && $followUp->company_id === (string) $actor->company_id
            && $integration->employee_id === (int) $actor->id;
    }
}
