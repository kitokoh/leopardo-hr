<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationReplyPolicy;

/**
 * RBAC des politiques de reponse assistee (BC-29 COMMUNICATION, R5 #7690).
 *
 * La politique d'une boite n'est visible/modifiable que par son
 * PROPRIETAIRE (meme principal/rh = 403) — la boite est personnelle,
 * pattern des policies R2/R3/R4.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationReplyPolicyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CommunicationReplyPolicy $policy): bool
    {
        return $this->ownsMailbox($actor, $policy);
    }

    public function update(Employee $actor, CommunicationReplyPolicy $policy): bool
    {
        return $this->ownsMailbox($actor, $policy);
    }

    public function delete(Employee $actor, CommunicationReplyPolicy $policy): bool
    {
        return $this->ownsMailbox($actor, $policy);
    }

    private function ownsMailbox(Employee $actor, CommunicationReplyPolicy $policy): bool
    {
        $integration = $policy->integration;

        return $integration !== null
            && $policy->company_id === (string) $actor->company_id
            && $integration->employee_id === (int) $actor->id;
    }
}
