<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\Mobile;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Actions\AddOrderItemAction;
use App\Modules\RestaurantManager\Application\Actions\CreateOrderAction;
use App\Modules\RestaurantManager\Application\Actions\PayOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use RuntimeException;
use Throwable;

/**
 * RESTO-804 (#6225) — Synchronisation offline mobile (file idempotente).
 *
 * L'app serveur envoie ses opérations effectuées HORS LIGNE ; le serveur les
 * rejoue IDEMPOTEMENT (clés client conservées → un rejeu ne crée jamais de
 * doublon). Réponse par opération : `created` | `duplicate` | `error`
 * (pattern TravelMobileSyncService, TRAVEL-704/#6091). Borné à 50 opérations
 * par appel ; les opérations inconnues échouent sans effet de bord.
 */
final class RestaurantMobileSyncService
{
    public function __construct(
        private readonly CreateOrderAction $createOrder,
        private readonly AddOrderItemAction $addItem,
        private readonly PayOrderAction $payOrder,
    ) {
    }

    /**
     * @param  list<array{type: string, payload: array<string, mixed>, idempotency_key: string}>  $operations
     * @return list<array{type: string, status: string, reference?: string, error?: string}>
     */
    public function sync(Employee $actor, array $operations): array
    {
        $results = [];

        foreach (array_slice($operations, 0, 50) as $operation) {
            $type = (string) $operation['type'];
            $key = (string) $operation['idempotency_key'];
            $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];

            if ($key === '') {
                $results[] = ['type' => $type, 'status' => 'error', 'error' => 'clé idempotence manquante'];

                continue;
            }

            try {
                $results[] = match ($type) {
                    'order.create' => $this->syncOrderCreate($actor, $payload, $key),
                    'order.add_item' => $this->syncAddItem($actor, $payload, $key),
                    'order.pay' => $this->syncOrderPay($actor, $payload, $key),
                    default => ['type' => $type, 'status' => 'error', 'error' => 'type d\'opération inconnu'],
                };
            } catch (Throwable $e) {
                $message = $e instanceof RuntimeException ? $e->getMessage() : 'erreur serveur';

                $results[] = ['type' => $type, 'status' => 'error', 'error' => $message];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, status: string, reference?: string, error?: string}
     */
    private function syncOrderCreate(Employee $actor, array $payload, string $key): array
    {
        $data = [
            'branch_id' => isset($payload['branch_id']) ? (int) $payload['branch_id'] : 0,
            'order_type' => isset($payload['order_type']) ? (string) $payload['order_type'] : 'takeaway',
            'source' => OrderSource::POS->value,
            'idempotency_key' => $key,
        ];

        $result = $this->createOrder->create($actor, $data);

        $order = $result['order'];

        return [
            'type' => 'order.create',
            'status' => $result['created'] ? 'created' : 'duplicate',
            'reference' => $order->reference,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, status: string, reference?: string, error?: string}
     */
    private function syncAddItem(Employee $actor, array $payload, string $key): array
    {
        $orderId = isset($payload['order_id']) ? (int) $payload['order_id'] : 0;
        $order = RestaurantOrder::query()->find($orderId);

        if (! $order instanceof RestaurantOrder || $order->company_id !== $actor->company_id) {
            return ['type' => 'order.add_item', 'status' => 'error', 'error' => 'commande introuvable'];
        }

        $this->addItem->add($actor, $order, [
            'product_id' => isset($payload['product_id']) ? (int) $payload['product_id'] : 0,
            'quantity' => $payload['quantity'] ?? 1,
            'menu_id' => isset($payload['menu_id']) ? (int) $payload['menu_id'] : null,
        ]);

        return ['type' => 'order.add_item', 'status' => 'created', 'reference' => $order->reference];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, status: string, reference?: string, error?: string}
     */
    private function syncOrderPay(Employee $actor, array $payload, string $key): array
    {
        $orderId = isset($payload['order_id']) ? (int) $payload['order_id'] : 0;
        $order = RestaurantOrder::query()->find($orderId);

        if (! $order instanceof RestaurantOrder || $order->company_id !== $actor->company_id) {
            return ['type' => 'order.pay', 'status' => 'error', 'error' => 'commande introuvable'];
        }

        $payment = $this->payOrder->pay($actor, $order, [
            'provider_code' => 'cash',
            'amount_minor' => isset($payload['amount_minor']) ? (int) $payload['amount_minor'] : $order->total_minor,
            'tip_minor' => isset($payload['tip_minor']) ? (int) $payload['tip_minor'] : null,
            'idempotency_key' => $key,
        ]);

        return [
            'type' => 'order.pay',
            'status' => $payment->status->value === 'confirmed' ? 'created' : 'duplicate',
            'reference' => $order->reference,
        ];
    }
}
