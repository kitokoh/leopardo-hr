<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;

/**
 * TRAVEL-805 (#6096) — Policy des taux de conversion.
 *
 * Lecture ouverte au tenant ; écriture réservée à travel.manage.
 */
class TravelCurrencyRatePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelCurrencyRate $rate): bool
    {
        return $rate->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelCurrencyRate $rate): bool
    {
        return $this->create($actor) && $rate->company_id === $actor->company_id;
    }
}
