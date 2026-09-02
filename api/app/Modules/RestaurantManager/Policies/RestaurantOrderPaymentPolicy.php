<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;

/**
 * RESTO-407 (#6194) — Policy des paiements de commande.
 *
 * L'encaissement est une opération serveur/caissier (spec §1.2) ; la
 * consultation est ouverte à tout employé authentifié du tenant (404 sûr
 * cross-tenant au niveau contrôleur).
 */
class RestaurantOrderPaymentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantOrderPayment $payment): bool
    {
        return $payment->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'server');
    }
}
