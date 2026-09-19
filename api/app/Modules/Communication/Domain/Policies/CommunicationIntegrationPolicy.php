<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;

/**
 * RBAC des boites mail connectees (BC-29 COMMUNICATION, R1 #7686).
 *
 * Principe de l'issue : « aucun acces inter-boites sans assignation » — la
 * boite est PERSONNELLE. Chaque employe connecte, consulte et deconnecte SA
 * boite ; seuls `principal` et `rh` peuvent revoquer la boite d'un autre
 * (offboarding, incident de securite). Le partage/assignation fin arrive
 * avec le type de ressource `communication_mailbox` (RBAC R2-R4 de l'epique
 * #7597, fail-closed : le type est declare des R1 dans
 * `config/resource_types.php`, aucun acces ouvert par defaut).
 *
 * Defense en profondeur : le scope global `company_id` (BelongsToCompany)
 * renvoie deja 404 pour un autre tenant ; la policy re-verifie quand meme.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationIntegrationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        // La liste est bornee cote controleur a SES integrations : tout
        // employe du tenant peut lister les siennes.
        return true;
    }

    public function view(Employee $actor, CommunicationIntegration $integration): bool
    {
        return $integration->company_id === (string) $actor->company_id
            && ($integration->employee_id === (int) $actor->id
                || $actor->hasManagerRole('principal', 'rh'));
    }

    public function create(Employee $actor): bool
    {
        // Chacun connecte SA propre boite.
        return true;
    }

    public function delete(Employee $actor, CommunicationIntegration $integration): bool
    {
        // Le proprietaire deconnecte sa boite ; principal/rh peuvent revoquer
        // celle d'un collaborateur (offboarding, compromission).
        return $this->view($actor, $integration);
    }
}
