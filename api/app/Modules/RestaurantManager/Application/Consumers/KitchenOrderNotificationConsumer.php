<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Consumers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\RestaurantManager\Application\Actions\TransitionOrderAction;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;

/**
 * RESTO-808 (#6229) — Consommateur `restaurant.order.created.v1` : notifie
 * l'équipe cuisine qu'une nouvelle commande est arrivée.
 *
 * Cible : les employés du tenant portant un rôle cuisine/opérationnel
 * (principal, rh, manager, kitchen — même périmètre que l'écran cuisine
 * `isKitchenRole`). Passe par CommunicationService (BC-13) : préférences,
 * heures calmes, quotas et journal d'audit respectés. Payload redigé
 * (aucune PII) ; la commande est rechargée dans le contexte tenant.
 */
final class KitchenOrderNotificationConsumer implements RestaurantOutboxConsumer
{
    public function __construct(
        private readonly CommunicationService $communication,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === TransitionOrderAction::EVENT_ORDER_CREATED;
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
            ->whereIn('manager_role', ['principal', 'rh', 'manager', 'kitchen'])
            ->get();

        $reference = (string) ($order->reference ?? "#{$order->id}");

        foreach ($staff as $employee) {
            $this->communication->notifyEmployee($employee, 'restaurant_new_order', [
                'title' => 'Nouvelle commande',
                'body' => sprintf('Commande %s — %d couvert(s).', $reference, (int) ($order->covers ?? 0)),
                'category' => 'restaurant',
            ], ['app', 'push']);
        }
    }
}
