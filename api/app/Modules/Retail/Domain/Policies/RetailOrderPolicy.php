<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailOrder;

/**
 * RBAC des commandes de vente POS du module Retail (BC-17 RETAIL, #7674).
 *
 * Lecture (liste, ticket) ouverte aux membres du tenant (scope `company_id`
 * verifie) ; creation de commande, encaissement et annulation reserves au
 * responsable du tenant (sous-roles `principal`/`rh`) — miroir du
 * comportement historique du POS RestaurantManager (#7599 : operate =
 * principal/rh tant qu'aucune assignation de succursale n'existe) et des
 * ecritures Retail existantes (#7672/#7673).
 * deny-by-default : aucun role = refus (fail-closed, pattern Catalog #6880).
 */
class RetailOrderPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailOrder $order): bool
    {
        return $order->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    /**
     * Encaisser un paiement sur la commande.
     */
    public function pay(Employee $actor, RetailOrder $order): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $order->company_id === (string) $actor->company_id;
    }

    public function cancel(Employee $actor, RetailOrder $order): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $order->company_id === (string) $actor->company_id;
    }

    /**
     * Transitions du cycle de vie des commandes EN LIGNE (#7808 :
     * confirm/ready/ship/deliver/cancel) — meme portee que l'encaissement.
     */
    public function fulfill(Employee $actor, RetailOrder $order): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $order->company_id === (string) $actor->company_id;
    }
}
