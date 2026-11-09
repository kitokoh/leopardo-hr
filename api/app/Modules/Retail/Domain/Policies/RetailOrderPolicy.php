<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;

/**
 * RBAC des commandes de vente POS du module Retail (BC-17 RETAIL, #7674).
 *
 * #9207 — permission `retail.orders` reellement appliquee : creer, encaisser,
 * annuler et rembourser une commande est un geste de VENDEUR (direction, ou
 * niveau `operate` sur la boutique de la commande). Avant ce correctif, seul
 * `principal`/`rh` pouvait encaisser.
 *
 * Lecture (liste, ticket) ouverte aux membres du tenant (scope `company_id`
 * verifie). deny-by-default : aucun role = refus (fail-closed).
 */
class RetailOrderPolicy
{
    public function __construct(private readonly RetailPermissions $permissions = new RetailPermissions) {}

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
        return $this->permissions->canOperateOrders($actor);
    }

    /**
     * Encaisser un paiement sur la commande.
     */
    public function pay(Employee $actor, RetailOrder $order): bool
    {
        return $this->canOperateOrder($actor, $order);
    }

    public function cancel(Employee $actor, RetailOrder $order): bool
    {
        return $this->canOperateOrder($actor, $order);
    }

    /**
     * Rembourser une commande encaissee (#9208).
     */
    public function refund(Employee $actor, RetailOrder $order): bool
    {
        return $this->canOperateOrder($actor, $order);
    }

    /**
     * Piloter la logistique d'une commande en ligne Leopardo Marche
     * (confirm/ready/ship/deliver — #7808) : meme portee que les autres
     * ecritures POS, bornee a la boutique de la commande.
     */
    public function fulfill(Employee $actor, RetailOrder $order): bool
    {
        return $this->canOperateOrder($actor, $order);
    }

    /**
     * Plan commun des ecritures : tenant proprietaire + niveau `operate` sur
     * la boutique de la commande.
     */
    private function canOperateOrder(Employee $actor, RetailOrder $order): bool
    {
        return $order->company_id === (string) $actor->company_id
            && $this->permissions->canOnLocation(
                $actor,
                (int) $order->location_id,
                EmployeeResourceAssignment::LEVEL_OPERATE,
            );
    }
}
