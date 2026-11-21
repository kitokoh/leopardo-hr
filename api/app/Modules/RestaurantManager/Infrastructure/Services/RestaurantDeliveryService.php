<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use RuntimeException;

/**
 * RESTO-605 (#6210) — Cycle de vie d'une livraison.
 *
 * pending → assigned (livreur actif exigé) → out_for_delivery → delivered |
 * cancelled. Toutes les transitions sont validées ; une livraison annulée
 * fait revenir la commande à `ready` (critère d'acceptation), une livraison
 * livrée clôt la commande (status `closed`) — le cycle reste tracé par
 * l'horodatage `delivered_at` et le statut.
 */
final class RestaurantDeliveryService
{
    /**
     * @return array{delivery: RestaurantDelivery, order: RestaurantOrder|null}
     */
    public function create(Employee $actor, int $orderId, ?int $zoneId, int $feeMinor): array
    {
        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($orderId);

        if ($order->order_type !== OrderType::DELIVERY) {
            throw new RuntimeException('La commande n\'est pas de type livraison.');
        }

        // Idempotence : une commande n'a qu'UNE livraison. Un rejeu (double
        // tap sur « envoyer en livraison », retry réseau, file offline
        // RESTO-804) doit renvoyer la livraison existante, pas en créer une
        // seconde — sinon le livreur voit deux courses pour la même commande
        // et les frais sont facturés deux fois.
        /** @var RestaurantDelivery|null $existing */
        $existing = RestaurantDelivery::query()
            ->where('company_id', $actor->company_id)
            ->where('order_id', $order->id)
            ->first();

        if ($existing !== null) {
            return ['delivery' => $existing, 'order' => $order];
        }

        /** @var RestaurantDelivery $delivery */
        $delivery = RestaurantDelivery::query()->create([
            'company_id' => $actor->company_id,
            'order_id' => $order->id,
            'zone_id' => $zoneId,
            'status' => DeliveryStatus::PENDING,
            'fee_minor' => $feeMinor,
        ]);

        return ['delivery' => $delivery, 'order' => $order];
    }

    public function assign(RestaurantDelivery $delivery, RestaurantDeliveryRider $rider): RestaurantDelivery
    {
        if ($delivery->status !== DeliveryStatus::PENDING) {
            throw new RuntimeException('Seule une livraison en attente peut être assignée.');
        }

        if (! $rider->is_active) {
            throw new RuntimeException('Un livreur inactif ne peut pas être assigné.');
        }

        $delivery->rider_id = $rider->id;
        $delivery->status = DeliveryStatus::ASSIGNED;
        $delivery->save();

        return $delivery;
    }

    public function outForDelivery(RestaurantDelivery $delivery): RestaurantDelivery
    {
        if ($delivery->status !== DeliveryStatus::ASSIGNED) {
            throw new RuntimeException('Seule une livraison assignée peut partir en tournée.');
        }

        $delivery->status = DeliveryStatus::OUT_FOR_DELIVERY;
        $delivery->save();

        return $delivery;
    }

    public function deliver(RestaurantDelivery $delivery, ?string $deliveredToContact = null): RestaurantDelivery
    {
        if ($delivery->status !== DeliveryStatus::OUT_FOR_DELIVERY) {
            throw new RuntimeException('Seule une livraison en tournée peut être marquée livrée.');
        }

        $delivery->status = DeliveryStatus::DELIVERED;
        $delivery->delivered_at = now();
        $delivery->delivered_to_contact = $deliveredToContact;
        $delivery->save();

        // La commande livrée passe à `served` — PAS `closed`.
        // Cycle documenté (§98, §421 de SOLUTION_RESTAURANT_MANAGER) :
        //   draft → open → in_preparation → ready → served → paid → closed
        // et critère de recette UAT R3 : « Commande `served` après livraison ».
        // Sauter à `closed` court-circuitait l'encaissement : la commande
        // n'était plus facturable et sortait des rapports de CA.
        $order = $delivery->order;
        if ($order !== null && in_array($order->status->value, ['ready', 'paid'], true)) {
            $order->status = OrderStatus::SERVED;
            $order->save();
        }

        return $delivery;
    }

    public function cancel(RestaurantDelivery $delivery, ?string $reason = null): RestaurantDelivery
    {
        if (in_array($delivery->status->value, [DeliveryStatus::DELIVERED->value, DeliveryStatus::CANCELLED->value], true)) {
            throw new RuntimeException('Cette livraison ne peut plus être annulée.');
        }

        $delivery->status = DeliveryStatus::CANCELLED;
        $delivery->save();

        // Livraison annulée → la commande retourne à `ready` (critère d'acceptation).
        $order = $delivery->order;
        if ($order !== null && in_array($order->status->value, ['in_preparation', 'ready', 'open'], true)) {
            $order->status = OrderStatus::READY;
            $order->save();
        }

        return $delivery;
    }
}
