<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelQuote;

/**
 * TRAVEL-803 (#6094) — Policy des devis de groupe.
 *
 * Création/réservation réservées aux rôles de vente (travel.manage) ;
 * lecture ouverte au tenant.
 */
class TravelQuotePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelQuote $quote): bool
    {
        return $quote->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }
}
