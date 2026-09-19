<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;

/**
 * RBAC de la file Pending des reponses assistees (BC-29 COMMUNICATION,
 * R5 #7690).
 *
 * SEUL LE PROPRIETAIRE de la boite valide (exigence issue) : approbation,
 * rejet et edition d'une proposition sont reserves a l'employe dont la
 * boite Gmail enverrait la reponse — meme principal/rh = 403 (pattern des
 * policies R2/R3/R4, la boite est personnelle).
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationPendingReplyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CommunicationPendingReply $reply): bool
    {
        return $this->ownsMailbox($actor, $reply);
    }

    /**
     * Edition du brouillon (sujet/corps) avant approbation.
     */
    public function update(Employee $actor, CommunicationPendingReply $reply): bool
    {
        return $this->ownsMailbox($actor, $reply);
    }

    /**
     * Validation humaine (approve = ENVOI reel depuis la boite / reject).
     */
    public function decide(Employee $actor, CommunicationPendingReply $reply): bool
    {
        return $this->ownsMailbox($actor, $reply);
    }

    private function ownsMailbox(Employee $actor, CommunicationPendingReply $reply): bool
    {
        $integration = $reply->integration;

        return $integration !== null
            && $reply->company_id === (string) $actor->company_id
            && $integration->employee_id === (int) $actor->id;
    }
}
