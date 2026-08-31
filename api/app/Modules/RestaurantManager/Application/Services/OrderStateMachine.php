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
        'draft' => ['open', 'cancelled'],
        'open' => ['in_preparation', 'cancelled', 'paid'],
        'in_preparation' => ['ready', 'paid'],
        'ready' => ['served', 'paid'],
        'served' => ['paid'],
        'paid' => ['closed', 'refunded'],
        'cancelled' => [],
        'refunded' => [],
        'closed' => [],
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
