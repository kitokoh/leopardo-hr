<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;

/**
 * FUEL-004 — corrections et revues réservées aux managers principal/rh
 * du TENANT (jamais de garde inline).
 */
class FuelMeterReadingPolicy
{
    public function correct(Employee $actor, FuelMeterReading $reading): bool
    {
        return (string) $reading->getAttribute('company_id') === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }

    public function review(Employee $actor, FuelMeterInterval $interval): bool
    {
        return (string) $interval->getAttribute('company_id') === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }
}
