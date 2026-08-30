<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;

/**
 * RESTO-404 (#6191) — Machine à états des commandes restaurant (spec §4.5).
 *
 * Workflow : draft → open → in_preparation → ready → served → paid → closed,
 * avec annulation depuis draft/open, remboursement depuis paid et paiement
 * direct depuis les états de préparation (à emporter / livraison : le
 * paiement peut intervenir avant le service). Toute transition hors workflow
 * est refusée (409).
 */
final class OrderStateMachine
{
    /**
     * Transitions autorisées par statut source.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        OrderStatus::DRAFT->value => [OrderStatus::OPEN->value, OrderStatus::CANCELLED->value],
        OrderStatus::OPEN->value => [OrderStatus::IN_PREPARATION->value, OrderStatus::CANCELLED->value, OrderStatus::PAID->value],
        OrderStatus::IN_PREPARATION->value => [OrderStatus::READY->value, OrderStatus::PAID->value],
        OrderStatus::READY->value => [OrderStatus::SERVED->value, OrderStatus::PAID->value],
        OrderStatus::SERVED->value => [OrderStatus::PAID->value],
        OrderStatus::PAID->value => [OrderStatus::CLOSED->value, OrderStatus::REFUNDED->value],
        OrderStatus::CANCELLED->value => [],
        OrderStatus::REFUNDED->value => [],
        OrderStatus::CLOSED->value => [],
    ];

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Statuts pour lesquels une commande peut être réglée (payable).
     *
     * @return list<OrderStatus>
     */
    public function payableStatuses(): array
    {
        return [
            OrderStatus::OPEN,
            OrderStatus::IN_PREPARATION,
            OrderStatus::READY,
            OrderStatus::SERVED,
        ];
    }

    public function isPayable(OrderStatus $status): bool
    {
        return in_array($status, $this->payableStatuses(), true);
    }
}
