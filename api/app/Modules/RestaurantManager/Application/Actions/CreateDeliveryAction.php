<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-605 (#6210) — Création d'une livraison pour une commande à livrer.
 *
 * Invariants :
 * - la commande doit être de type `delivery` (spec §3.5 : une commande n'a
 *   qu'une livraison) ;
 * - le frais est recalculé serveur depuis la zone (jamais accepté du client) ;
 * - idempotence : l'index unique (company_id, order_id) déduplique le rejeu —
 *   la livraison existante est retournée telle quelle ;
 * - la zone doit appartenir au même tenant et à la même branche (mismatch → 422).
 */
final class CreateDeliveryAction
{
    public const EVENT_DELIVERY_STATUS_CHANGED = 'restaurant.delivery.status.changed.v1';

    public function __construct(
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    /**
     * @param  array{zone_id?: int|null}  $data
     */
    public function create(Employee $actor, RestaurantOrder $order, array $data): RestaurantDelivery
    {
        if ($order->company_id !== $actor->company_id) {
            throw new RuntimeException('Order does not belong to tenant.');
        }

        if ($order->order_type !== OrderType::DELIVERY) {
            abort(422, 'Delivery can only be created for a delivery order.');
        }

        $existing = RestaurantDelivery::query()
            ->where('company_id', $order->company_id)
            ->where('order_id', $order->id)
            ->first();

        if ($existing instanceof RestaurantDelivery) {
            return $existing;
        }

        $feeMinor = 0;
        if (($data['zone_id'] ?? null) !== null) {
            /** @var RestaurantDeliveryZone|null $zone */
            $zone = RestaurantDeliveryZone::query()
                ->where('company_id', $order->company_id)
                ->where('id', $data['zone_id'])
                ->first();

            if (! $zone instanceof RestaurantDeliveryZone) {
                abort(422, 'Unknown delivery zone.');
            }

            if ($zone->branch_id !== $order->branch_id) {
                abort(422, 'Delivery zone does not belong to the order branch.');
            }

            $feeMinor = (int) $zone->fee_minor;
        }

        try {
            $delivery = DB::transaction(fn (): RestaurantDelivery => RestaurantDelivery::query()->create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'zone_id' => $data['zone_id'] ?? null,
                'status' => 'pending',
                'fee_minor' => $feeMinor,
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var RestaurantDelivery $existing */
            $existing = RestaurantDelivery::query()
                ->where('company_id', $order->company_id)
                ->where('order_id', $order->id)
                ->firstOrFail();

            return $existing;
        }

        $this->outbox->publish(
            $order->company_id,
            self::EVENT_DELIVERY_STATUS_CHANGED,
            [
                'delivery_id' => $delivery->id,
                'order_id' => $order->id,
                'status' => $delivery->status,
                'branch_id' => $order->branch_id,
            ],
        );

        return $delivery;
    }
}
