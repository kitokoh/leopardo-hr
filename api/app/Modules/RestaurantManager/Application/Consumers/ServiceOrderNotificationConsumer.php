<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Consumers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\RestaurantManager\Application\Observers\RestaurantOrderObserver;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;

/**
 * RESTO-808 (#6229) — Consommateur `restaurant.order.ready.v1` : notifie
 * l'équipe de service qu'une commande est prête à être servie.
 *
 * L'événement est publié par RestaurantOrderObserver quand une commande
 * passe à `ready` (émission découplée, aucun changement du flux POS).
 * Cible : les employés du tenant portant un rôle service/opérationnel
 * (principal, rh, manager, server). Passe par CommunicationService (BC-13).
 */
final class ServiceOrderNotificationConsumer implements RestaurantOutboxConsumer
{
    public function __construct(
        private readonly CommunicationService $communication,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === RestaurantOrderObserver::EVENT_ORDER_READY;
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

        $staff = Employee::query()
            ->where('company_id', $order->company_id)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh', 'manager', 'server'])
            ->get();

        $reference = (string) ($order->reference ?? "#{$order->id}");

        foreach ($staff as $employee) {
            $this->communication->notifyEmployee($employee, 'restaurant_order_ready', [
                'title' => 'Commande prête',
                'body' => sprintf('Commande %s prête à être servie (table %s).', $reference, (string) ($order->table_id ?? '—')),
                'category' => 'restaurant',
            ], ['app', 'push']);
        }
    }
}
