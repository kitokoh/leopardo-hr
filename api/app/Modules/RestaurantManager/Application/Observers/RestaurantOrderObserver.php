<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Observers;

use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;

/**
 * RESTO-808 (#6229) — Observateur de commande : émet l'événement
 * `restaurant.order.ready.v1` quand une commande passe à `ready`.
 *
 * L'émission est découplée du flux POS (aucun changement des contrôleurs) :
 * l'observateur détecte la transition de statut sur le modèle et publie dans
 * l'outbox après commit — le consommateur ServiceOrderNotificationConsumer
 * notifie l'équipe de service. Idempotence : l'observateur ne publie que sur
 * `wasChanged('status')` et la clé outbox dérivée déduplique les rejets.
 */
final class RestaurantOrderObserver
{
    public const EVENT_ORDER_READY = 'restaurant.order.ready.v1';

    public function __construct(
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    public function updated(RestaurantOrder $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        if ($order->status !== OrderStatus::READY) {
            return;
        }

        $this->outbox->publish(
            $order->company_id,
            self::EVENT_ORDER_READY,
            [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'branch_id' => $order->branch_id,
                'order_type' => $order->order_type->value,
                'table_id' => $order->table_id,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
            ],
        );
    }
}
