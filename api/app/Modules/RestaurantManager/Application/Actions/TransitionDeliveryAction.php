<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Services\DeliveryStateMachine;
use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-605 (#6210) — Transitions du cycle de livraison.
 *
 * Transitions validées par DeliveryStateMachine (spec §4.5) :
 * - assign : affecte un livreur actif du même tenant/branche ;
 * - out_for_delivery : départ en tournée (livreur affecté requis) ;
 * - deliver : livraison terminée (contact de remise), commande → served ;
 * - cancel : annulation (pending/assigned/out_for_delivery), la commande
 *   retourne à `ready` (critère d'acceptation RESTO-605).
 *
 * Chaque transition publie `restaurant.delivery.status.changed.v1` après
 * commit (consommateur Notifications, RESTO-605).
 */
final class TransitionDeliveryAction
{
    public const EVENT_DELIVERY_STATUS_CHANGED = CreateDeliveryAction::EVENT_DELIVERY_STATUS_CHANGED;

    public function __construct(
        private readonly DeliveryStateMachine $stateMachine,
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    /**
     * @param  array{rider_id?: int|null, delivered_to_contact?: string|null}  $data
     */
    public function transition(Employee $actor, RestaurantDelivery $delivery, DeliveryStatus $to, array $data = []): RestaurantDelivery
    {
        if ($delivery->company_id !== $actor->company_id) {
            throw new RuntimeException('Delivery does not belong to tenant.');
        }

        if (! $this->stateMachine->canTransition($delivery->status, $to)) {
            abort(409, sprintf('Delivery cannot transition from "%s" to "%s".', $delivery->status->value, $to->value));
        }

        $order = $delivery->order()->first();

        if (! $order instanceof RestaurantOrder) {
            throw new RuntimeException('Delivery order is missing.');
        }

        if ($order->company_id !== $actor->company_id) {
            throw new RuntimeException('Delivery order does not belong to tenant.');
        }

        $delivery = DB::transaction(function () use ($delivery, $order, $to, $data): RestaurantDelivery {
            if ($to === DeliveryStatus::ASSIGNED) {
                $riderId = $data['rider_id'] ?? null;

                if ($riderId === null) {
                    abort(422, 'A rider is required to assign a delivery.');
                }

                /** @var RestaurantDeliveryRider|null $rider */
                $rider = RestaurantDeliveryRider::query()
                    ->where('company_id', $delivery->company_id)
                    ->where('id', $riderId)
                    ->first();

                if (! $rider instanceof RestaurantDeliveryRider) {
                    abort(422, 'Unknown rider.');
                }

                if ($rider->branch_id !== $order->branch_id) {
                    abort(422, 'Rider does not belong to the order branch.');
                }

                if (! $rider->is_active) {
                    abort(422, 'Inactive rider cannot be assigned.');
                }

                $delivery->forceFill(['rider_id' => $rider->id])->save();
            }

            if ($to === DeliveryStatus::OUT_FOR_DELIVERY && $delivery->rider_id === null) {
                abort(422, 'A rider must be assigned before going out for delivery.');
            }

            if ($to === DeliveryStatus::DELIVERED) {
                $delivery->forceFill([
                    'status' => $to->value,
                    'delivered_at' => now(),
                    'delivered_to_contact' => $data['delivered_to_contact'] ?? null,
                ])->save();

                // La commande livrée passe à `served` (fin du cycle de service,
                // spec §4.2) — sauf si elle est déjà payée/close (paiement en
                // ligne ou à la commande) : on ne recule jamais un état terminal.
                if ($order->status === OrderStatus::READY) {
                    DB::table('restaurant_orders')
                        ->where('id', $order->id)
                        ->where('company_id', $order->company_id)
                        ->update([
                            'status' => OrderStatus::SERVED->value,
                            'version' => $order->version + 1,
                        ]);
                    $order->refresh();
                }
            } elseif ($to === DeliveryStatus::CANCELLED) {
                $delivery->forceFill([
                    'status' => $to->value,
                    'rider_id' => null,
                ])->save();

                // Critère d'acceptation RESTO-605 : « livraison annulée →
                // commande retourne à ready » (reprise par un autre livreur
                // ou retrait client). Les états terminaux (payé/close/annulé/
                // remboursé) ne sont jamais reculés.
                if (! in_array($order->status, [
                    OrderStatus::PAID,
                    OrderStatus::CLOSED,
                    OrderStatus::CANCELLED,
                    OrderStatus::REFUNDED,
                ], true)) {
                    DB::table('restaurant_orders')
                        ->where('id', $order->id)
                        ->where('company_id', $order->company_id)
                        ->update([
                            'status' => OrderStatus::READY->value,
                            'version' => $order->version + 1,
                        ]);
                    $order->refresh();
                }
            } else {
                $delivery->forceFill(['status' => $to->value])->save();
            }

            return $delivery->refresh();
        });

        $this->outbox->publish(
            $delivery->company_id,
            self::EVENT_DELIVERY_STATUS_CHANGED,
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'status' => $delivery->status,
                'branch_id' => $order->branch_id,
                'rider_id' => $delivery->rider_id,
            ],
        );

        return $delivery;
    }
}
