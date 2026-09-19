<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationThread;

/**
 * RBAC des fils Gmail synchronises (BC-29 COMMUNICATION, R2 #7687).
 *
 * Plus STRICT que la policy des integrations (R1) : le CONTENU d'une boite
 * (fils, messages, corps) n'est visible que par son PROPRIETAIRE — meme
 * principal/rh n'y accedent pas (ils peuvent revoquer la boite, pas la
 * lire). « Aucun acces inter-boites sans assignation » : le partage fin
 * arrive avec le RBAC ressource-scope (`communication_mailbox`,
 * config/resource_types.php, fail-closed — aucune assignation en R2).
 *
 * Defense en profondeur : le scope global `company_id` (BelongsToCompany)
 * renvoie deja 404 pour un autre tenant ; la policy re-verifie quand meme.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationThreadPolicy
{
    public function viewAny(Employee $actor): bool
    {
        // La liste est bornee cote controleur aux fils de SES boites.
        return true;
    }

    public function view(Employee $actor, CommunicationThread $thread): bool
    {
        $integration = $thread->integration;

        return $integration !== null
            && $thread->company_id === (string) $actor->company_id
            && $integration->employee_id === (int) $actor->id;
    }
}
