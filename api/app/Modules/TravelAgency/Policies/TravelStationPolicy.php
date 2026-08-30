<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelStation;

/**
 * TRAVEL-302 (#6032) — Policy des gares/terminaux TravelAgency.
 *
 * `travel.manage` (création/modification/suppression) : principal/rh/manager.
 * `travel.agent` (lecture) : tout employé authentifié du tenant — le
 * périmètre reste borné par le scope `BelongsToCompany` + le contrôleur
 * (404 sûr cross-tenant, jamais un 403 qui révèlerait l'existence de la
 * ressource sur un autre tenant).
 */
class TravelStationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelStation $station): bool
    {
        return $station->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelStation $station): bool
    {
        return $this->create($actor) && $station->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelStation $station): bool
    {
        return $this->update($actor, $station);
    }
}
