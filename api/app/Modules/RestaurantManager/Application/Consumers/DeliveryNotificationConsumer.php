<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Consumers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\RestaurantManager\Application\Actions\CreateDeliveryAction;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;

/**
 * RESTO-605 (#6210) — Consommateur `restaurant.delivery.status.changed.v1`.
 *
 * Notifie le livreur affecté (employé HR référencé par `employee_id`) à
 * chaque étape du cycle (affectation, départ en tournée, livraison).
 * Passe par CommunicationService (BC-13) : préférences, heures calmes,
 * quotas et journal d'audit respectés. Aucun livreur (ou employé inconnu) →
 * aucune notification (silencieux, sans dead-letter).
 */
final class DeliveryNotificationConsumer implements RestaurantOutboxConsumer
{
    public function __construct(
        private readonly CommunicationService $communication,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === CreateDeliveryAction::EVENT_DELIVERY_STATUS_CHANGED;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $deliveryId = (int) ($payload['delivery_id'] ?? 0);

        if ($deliveryId <= 0) {
            return;
        }

        $delivery = RestaurantDelivery::query()->find($deliveryId);

        if (! $delivery instanceof RestaurantDelivery) {
            return;
        }

        if ($delivery->rider_id === null) {
            return;
        }

        $rider = $delivery->rider()->first();

        if ($rider === null || $rider->employee_id === null) {
            return;
        }

        $employee = Employee::query()
            ->where('company_id', $delivery->company_id)
            ->find($rider->employee_id);

        if (! $employee instanceof Employee) {
            return;
        }

        $statusLabel = $delivery->status->label();

        $this->communication->notifyEmployee($employee, 'restaurant_delivery_status_changed', [
            'title' => 'Livraison : '.$statusLabel,
            'body' => sprintf('La livraison #%d (commande #%d) est maintenant « %s ».', $delivery->id, $delivery->order_id, $statusLabel),
            'category' => 'restaurant',
        ], ['app', 'push']);
    }
}
