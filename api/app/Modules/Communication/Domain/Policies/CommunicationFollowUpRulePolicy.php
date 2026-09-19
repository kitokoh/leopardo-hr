<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;

/**
 * RBAC des regles de relance (BC-29 COMMUNICATION, R4 #7689).
 *
 * Meme regle que `CommunicationThreadPolicy`/`CommunicationMessagePolicy` :
 * la regle appartient a la BOITE de son proprietaire — meme principal/rh
 * ne la voient ni ne la modifient (les relances partent du Gmail personnel
 * de l'utilisateur).
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationFollowUpRulePolicy
{
    /**
     * Lister SES regles (l'index est borne aux boites de l'appelant).
     */
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    /**
     * Creer une regle sur une de SES boites (l'appartenance de la boite est
     * verifiee par le controleur au moment de la validation).
     */
    public function create(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CommunicationFollowUpRule $rule): bool
    {
        return $this->ownsMailbox($actor, $rule);
    }

    public function update(Employee $actor, CommunicationFollowUpRule $rule): bool
    {
        return $this->ownsMailbox($actor, $rule);
    }

    public function delete(Employee $actor, CommunicationFollowUpRule $rule): bool
    {
        return $this->ownsMailbox($actor, $rule);
    }

    private function ownsMailbox(Employee $actor, CommunicationFollowUpRule $rule): bool
    {
        $integration = $rule->integration;

        return $integration !== null
            && $rule->company_id === (string) $actor->company_id
            && $integration->employee_id === (int) $actor->id;
    }
}
