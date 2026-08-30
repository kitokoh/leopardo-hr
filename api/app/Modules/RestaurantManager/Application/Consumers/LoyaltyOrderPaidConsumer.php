<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Consumers;

use App\Modules\RestaurantManager\Application\Actions\CreditLoyaltyPointsAction;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;

/**
 * RESTO-606 (#6211) — Consommateur `restaurant.order.paid.v1` : fidélité.
 *
 * Spec §6.3 : l'événement `restaurant.order.paid.v1` est consommé par
 * Accounting, Fidélité et Reporting. Ce consommateur crédite les points du
 * client à la commande payée (une seule fois — CreditLoyaltyPointsAction
 * idempotent + contrainte unique en base). Le payload est redigé (aucune
 * PII) ; l'ordre est rechargé dans le contexte tenant du dispatcher.
 */
final class LoyaltyOrderPaidConsumer implements RestaurantOutboxConsumer
{
    public const EVENT_ORDER_PAID = 'restaurant.order.paid.v1';

    public function __construct(
        private readonly CreditLoyaltyPointsAction $creditLoyaltyPoints,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === self::EVENT_ORDER_PAID;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $orderId = (int) ($payload['order_id'] ?? 0);

        if ($orderId <= 0) {
            return;
        }

        $order = RestaurantOrder::query()->find($orderId);

        if (! $order instanceof RestaurantOrder) {
            return;
        }

        $this->creditLoyaltyPoints->creditForPaidOrder($order);
    }
}
