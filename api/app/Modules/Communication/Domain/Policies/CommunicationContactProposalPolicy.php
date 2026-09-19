<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationContactProposal;

/**
 * RBAC des propositions de contact CRM (BC-29 COMMUNICATION, R3 #7688).
 *
 * Une proposition provient des messages d'une boite PERSONNELLE : seule la
 * personne qui possede la boite voit et decide (accepter/ecarter) ses
 * propositions — meme principal/rh n'y accedent pas (memes regles que le
 * contenu des fils, R2). La creation du contact CRM a l'acceptation passe
 * par le contrat partage `App\Shared\Contracts\Crm\EmailContactDirectory`.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationContactProposalPolicy
{
    public function viewAny(Employee $actor): bool
    {
        // La liste est bornee cote controleur aux boites de l'appelant.
        return true;
    }

    public function view(Employee $actor, CommunicationContactProposal $proposal): bool
    {
        return $this->ownsMailbox($actor, $proposal);
    }

    /**
     * Accepter (creation CRM explicite) ou ecarter la proposition.
     */
    public function decide(Employee $actor, CommunicationContactProposal $proposal): bool
    {
        return $this->ownsMailbox($actor, $proposal);
    }

    private function ownsMailbox(Employee $actor, CommunicationContactProposal $proposal): bool
    {
        $integration = $proposal->integration;

        return $integration !== null
            && $proposal->company_id === (string) $actor->company_id
            && $integration->employee_id === (int) $actor->id;
    }
}
