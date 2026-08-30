<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantRefund;

/**
 * RESTO-408 (#6195) — Policy des remboursements.
 *
 * Rembourser est réservé au gérant / RH (`restaurant.manage`, critère
 * d'acceptation « réservé manage ») ; la lecture est ouverte à tout employé
 * authentifié du tenant (404 sûr cross-tenant au niveau contrôleur).
 */
class RestaurantRefundPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantRefund $refund): bool
    {
        return $refund->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
