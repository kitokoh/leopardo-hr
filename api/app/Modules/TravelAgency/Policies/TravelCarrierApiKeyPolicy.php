<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelCarrierApiKey;

/**
 * TRAVEL-807 (#6086) — Policy des clés API transporteurs.
 *
 * Gestion réservée à travel.manage (le token donne accès à l'API entrante du
 * tenant — privilège fort).
 */
class TravelCarrierApiKeyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function delete(Employee $actor, TravelCarrierApiKey $apiKey): bool
    {
        return $this->create($actor) && $apiKey->company_id === $actor->company_id;
    }
}
