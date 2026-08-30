<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;

/**
 * TRAVEL-907/908 (#6110/#6111) — Policy des annonces payantes.
 * Lecture publique tenant (annonces visibles uniquement) ; soumission :
 * rôles opérationnels ; paiement/renouvellement : créateur ou gestion ;
 * modération (validation/rejet) : principal/rh/manager.
 */
class TravelAdvertPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelAdvert $advert): bool
    {
        return $advert->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'agent');
    }

    public function pay(Employee $actor, TravelAdvert $advert): bool
    {
        return $advert->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh', 'manager', 'agent');
    }

    public function moderate(Employee $actor, TravelAdvert $advert): bool
    {
        return $advert->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function renew(Employee $actor, TravelAdvert $advert): bool
    {
        return $this->pay($actor, $advert);
    }
}
