<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationMessage;

/**
 * RBAC des messages Gmail synchronises (BC-29 COMMUNICATION, R3 #7688).
 *
 * Meme regle que `CommunicationThreadPolicy` (R2) : le contenu d'une boite
 * n'est visible/actionnable que par son PROPRIETAIRE — meme principal/rh
 * n'y accedent pas. La re-classification manuelle (`classify`) est donc
 * reservee au proprietaire de la boite dont provient le message.
 *
 * Defense en profondeur : le scope global `company_id` (BelongsToCompany)
 * renvoie deja 404 pour un autre tenant ; la policy re-verifie quand meme.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CommunicationMessagePolicy
{
    public function view(Employee $actor, CommunicationMessage $message): bool
    {
        return $this->ownsMailbox($actor, $message);
    }

    /**
     * Re-classification manuelle (R3 #7688) : proprietaire uniquement.
     */
    public function classify(Employee $actor, CommunicationMessage $message): bool
    {
        return $this->ownsMailbox($actor, $message);
    }

    private function ownsMailbox(Employee $actor, CommunicationMessage $message): bool
    {
        $integration = $message->integration;

        return $integration !== null
            && $message->company_id === (string) $actor->company_id
            && $integration->employee_id === (int) $actor->id;
    }
}
