<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;

/**
 * RESTO-605 (#6210) — Machine à états des livraisons restaurant (spec §4.5).
 *
 * Workflow : pending → assigned → out_for_delivery → delivered | cancelled.
 * L'annulation est possible depuis pending/assigned/out_for_delivery ; la
 * livraison n'est « delivered » que depuis out_for_delivery. Toute transition
 * hors workflow est refusée (409) — l'appelant tranche l'erreur HTTP.
 */
final class DeliveryStateMachine
{
    /**
     * Transitions autorisées par statut source.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        DeliveryStatus::PENDING->value => [DeliveryStatus::ASSIGNED->value, DeliveryStatus::CANCELLED->value],
        DeliveryStatus::ASSIGNED->value => [DeliveryStatus::OUT_FOR_DELIVERY->value, DeliveryStatus::CANCELLED->value],
        DeliveryStatus::OUT_FOR_DELIVERY->value => [DeliveryStatus::DELIVERED->value, DeliveryStatus::CANCELLED->value],
        DeliveryStatus::DELIVERED->value => [],
        DeliveryStatus::CANCELLED->value => [],
    ];

    public function canTransition(DeliveryStatus $from, DeliveryStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Statuts pour lesquels une livraison peut encore être annulée.
     */
    public function isCancellable(DeliveryStatus $status): bool
    {
        return in_array($status, [DeliveryStatus::PENDING, DeliveryStatus::ASSIGNED, DeliveryStatus::OUT_FOR_DELIVERY], true);
    }
}
