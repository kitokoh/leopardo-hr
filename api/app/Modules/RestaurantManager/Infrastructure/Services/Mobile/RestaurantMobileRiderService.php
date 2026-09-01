<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\Mobile;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-802 (#6223) — App mobile livreur : tournées, statuts, navigation.
 *
 * Le livreur est résolu par `employee_id` (référence RH par valeur,
 * RESTO-211/#6176) : il ne voit que les livraisons qui lui sont assignées.
 * Les transitions réutilisent la machine à états Delivery (assign →
 * out_for_delivery → delivered), chaque transition publie un événement
 * outbox (`restaurant.delivery.*.v1`) pour la traçabilité et les
 * notifications client.
 */
final class RestaurantMobileRiderService
{
    public function __construct(private readonly RestaurantOutboxPublisher $outbox)
    {
    }

    private function riderFor(Employee $actor): ?RestaurantDeliveryRider
    {
        return RestaurantDeliveryRider::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Livraisons assignées au livreur connecté.
     *
     * @return list<array{id: int, reference: string, status: string, customer_name: string|null, customer_phone: string|null, address: string|null, fee_minor: int|null, order_total_minor: int}>
     */
    public function myDeliveries(Employee $actor): array
    {
        $rider = $this->riderFor($actor);

        if (! $rider instanceof RestaurantDeliveryRider) {
            return [];
        }

        return RestaurantDelivery::query()
            ->where('rider_id', $rider->id)
            ->whereIn('status', [DeliveryStatus::ASSIGNED->value, DeliveryStatus::OUT_FOR_DELIVERY->value])
            ->with(['order'])
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (RestaurantDelivery $delivery): array => $this->present($delivery))
            ->all();
    }

    public function delivery(Employee $actor, RestaurantDelivery $delivery): ?array
    {
        $rider = $this->riderFor($actor);

        if (! $rider instanceof RestaurantDeliveryRider || $delivery->rider_id !== $rider->id) {
            abort(404);
        }

        return $this->present($delivery->load(['order', 'zone']));
    }

    public function outForDelivery(Employee $actor, RestaurantDelivery $delivery): RestaurantDelivery
    {
        $rider = $this->riderFor($actor);

        if (! $rider instanceof RestaurantDeliveryRider || $delivery->rider_id !== $rider->id) {
            abort(404);
        }

        if (! in_array($delivery->status->value, [DeliveryStatus::ASSIGNED->value], true)) {
            abort(409, sprintf('Transition impossible depuis "%s".', $delivery->status->value));
        }

        $this->transition($delivery, DeliveryStatus::OUT_FOR_DELIVERY);

        return $delivery->refresh();
    }

    public function deliver(Employee $actor, RestaurantDelivery $delivery): RestaurantDelivery
    {
        $rider = $this->riderFor($actor);

        if (! $rider instanceof RestaurantDeliveryRider || $delivery->rider_id !== $rider->id) {
            abort(404);
        }

        if (! in_array($delivery->status->value, [DeliveryStatus::OUT_FOR_DELIVERY->value], true)) {
            abort(409, sprintf('Transition impossible depuis "%s".', $delivery->status->value));
        }

        $this->transition($delivery, DeliveryStatus::DELIVERED);

        return $delivery->refresh();
    }

    private function transition(RestaurantDelivery $delivery, DeliveryStatus $status): void
    {
        DB::transaction(function () use ($delivery, $status): void {
            $delivery->forceFill([
                'status' => $status->value,
                'delivered_at' => $status === DeliveryStatus::DELIVERED ? now() : $delivery->delivered_at,
            ])->save();
        });

        $this->outbox->publish(
            companyId: (string) $delivery->company_id,
            eventType: 'restaurant.delivery.'.$status->value.'.v1',
            payload: [
                'company_id' => $delivery->company_id,
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'status' => $status->value,
            ],
            idempotencyKey: 'delivery-'.$delivery->id.'-'.$status->value,
        );
    }

    /**
     * @return array{id: int, reference: string, status: string, customer_name: string|null, customer_phone: string|null, address: string|null, fee_minor: int|null, order_total_minor: int}
     */
    private function present(RestaurantDelivery $delivery): array
    {
        $order = $delivery->order;

        return [
            'id' => $delivery->id,
            'reference' => $order?->reference ?? (string) $delivery->id,
            'status' => $delivery->status->value,
            'customer_name' => $delivery->delivered_to_contact,
            'customer_phone' => $order?->note_redacted,
            'address' => $delivery->zone?->name,
            'fee_minor' => $delivery->fee_minor,
            'order_total_minor' => (int) ($order?->total_minor ?? 0),
        ];
    }
}
