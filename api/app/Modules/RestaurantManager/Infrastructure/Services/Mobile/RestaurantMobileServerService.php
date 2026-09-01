<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\Mobile;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Actions\PayOrderAction;
use App\Modules\RestaurantManager\Application\Services\OrderStateMachine;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-801 (#6222) — App mobile serveur : prise de commande, service,
 * encaissement cash.
 *
 * Surface mobile qui réutilise les invariants du POS (AddOrderItemAction,
 * OrderStateMachine, PayOrderAction) : le serveur ne fait que piloter des
 * transitions déjà validées côté serveur. Tout est tenant-scope (le scope
 * global BelongsToCompany s'applique sur les requêtes de l'employé).
 */
final class RestaurantMobileServerService
{
    public function __construct(
        private readonly PayOrderAction $payAction,
        private readonly OrderStateMachine $stateMachine,
    ) {
    }

    /**
     * Commandes actives (ouvertes / en préparation / prêtes / servies) du
     * tenant, triées par antériorité — la file de service du serveur.
     *
     * @return list<array{id: int, reference: string, status: string, order_type: string, table_name: string|null, total_minor: int, currency: string, items_count: int}>
     */
    public function activeOrders(Employee $actor): array
    {
        $statuses = [
            OrderStatus::OPEN->value,
            OrderStatus::IN_PREPARATION->value,
            OrderStatus::READY->value,
            OrderStatus::SERVED->value,
        ];

        return RestaurantOrder::query()
            ->whereIn('status', $statuses)
            ->withCount('items')
            ->with(['table'])
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->map(fn (RestaurantOrder $order): array => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'order_type' => $order->order_type->value,
                'table_name' => $order->table?->label,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'items_count' => (int) $order->items_count,
            ])
            ->all();
    }

    /**
     * Tables actuellement occupées du tenant (plan de salle serveur).
     *
     * @return list<array{id: int, name: string, zone: string|null, status: string}>
     */
    public function openTables(Employee $actor): array
    {
        $sessions = RestaurantTableSession::query()
            ->with(['table.zone'])
            ->where('status', 'open')
            ->orderBy('opened_at')
            ->limit(200)
            ->get();

        return $sessions->map(fn (RestaurantTableSession $session): array => [
            'id' => $session->table_id,
            'name' => (string) $session->table?->label,
            'zone' => $session->table?->zone instanceof RestaurantZone ? (string) $session->table?->zone?->name : null,
            'status' => 'occupied',
        ])->all();
    }

    /**
     * Passe une commande à « servie » (transition validée par la machine à
     * états). Idempotente : déjà servie → état courant retourné.
     */
    public function serveOrder(Employee $actor, RestaurantOrder $order): RestaurantOrder
    {
        if ($order->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $order)) {
            abort(403);
        }

        if ($order->status === OrderStatus::SERVED) {
            return $order;
        }

        if (! $this->stateMachine->canTransition($order->status, OrderStatus::SERVED)) {
            abort(409, sprintf('Transition impossible depuis "%s".', $order->status->value));
        }

        DB::transaction(function () use ($order): void {
            $order->forceFill(['status' => OrderStatus::SERVED->value])->save();
        });

        return $order->refresh();
    }

    /**
     * Encaissement cash (montant + pourboire) — délègue à PayOrderAction
     * (invariants RESTO-407 : montant vérifié serveur, idempotence,
     * double paiement impossible).
     */
    public function payCash(Employee $actor, RestaurantOrder $order, int $amountMinor, ?int $tipMinor = null, ?string $idempotencyKey = null): RestaurantOrderPayment
    {
        if ($order->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', RestaurantOrderPayment::class)) {
            abort(403);
        }

        return $this->payAction->pay($actor, $order, [
            'provider_code' => 'cash',
            'amount_minor' => $amountMinor,
            'tip_minor' => $tipMinor,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
