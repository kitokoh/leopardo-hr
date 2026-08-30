<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-905..908 (#6108..#6111) — Policy du périmètre « annonces payantes »
 * (types, positions, tarifs, annonces).
 *
 *  - lecture : tout employé authentifié du tenant ;
 *  - création/soumission : rôles opérationnels de l'agence (principal, rh,
 *    manager, agent) ;
 *  - validation/modération (`manage`) : principal/rh uniquement — une
 *    annonce n'est visible qu'une fois payée ET validée (#6110).
 */
final class TravelAdvertPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Model $resource): bool
    {
        return $this->belongsToTenant($resource, $actor);
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'agent');
    }

    public function update(Employee $actor, Model $resource): bool
    {
        return $this->create($actor) && $this->belongsToTenant($resource, $actor);
    }

    public function delete(Employee $actor, Model $resource): bool
    {
        return $this->update($actor, $resource);
    }

    /**
     * Validation / modération / renouvellement : réservé à la direction de
     * l'agence (permission `travel.manage`).
     */
    public function manage(Employee $actor, Model $resource): bool
    {
        return $actor->hasManagerRole('principal', 'rh') && $this->belongsToTenant($resource, $actor);
    }

    private function belongsToTenant(Model $resource, Employee $actor): bool
    {
        return (string) $resource->getAttribute('company_id') === (string) $actor->company_id;
    }
}
