<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;

/**
 * TRAVEL-411 (#6063) — Policy des paiements TravelAgency.
 *
 * Le remboursement est réservé à `travel.manage` ; la lecture est ouverte
 * à tout employé du tenant.
 */
class TravelPaymentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelPayment $payment): bool
    {
        return $payment->company_id === $actor->company_id;
    }

    public function update(Employee $actor, TravelPayment $payment): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager')
            && $payment->company_id === $actor->company_id;
    }
}
