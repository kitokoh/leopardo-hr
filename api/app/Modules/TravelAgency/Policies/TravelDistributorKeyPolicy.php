<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelDistributorKey;

/**
 * TRAVEL-DISTRIBUTION (#7641) — Policy des clés API distributeurs.
 *
 * Gestion réservée aux gérants (principal/rh) : une clé donne un accès de
 * lecture externe au catalogue et aux réservations du tenant — même
 * privilège fort que TravelCarrierApiKeyPolicy.
 */
class TravelDistributorKeyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, TravelDistributorKey $key): bool
    {
        return $this->create($actor) && $key->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelDistributorKey $key): bool
    {
        return $this->create($actor) && $key->company_id === $actor->company_id;
    }
}
