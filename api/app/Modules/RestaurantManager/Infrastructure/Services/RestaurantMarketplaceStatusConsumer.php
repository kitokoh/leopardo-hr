<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMarketplaceEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\DeliveryAppRegistry;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * RESTO-806 (#6227) — Consommateur outbox : notification des statuts aux
 * apps de livraison (sortants).
 *
 * Pour une commande `delivery_app`, les événements `restaurant.order.created.v1`
 * et `restaurant.order.paid.v1` déclenchent un appel webhook signé HMAC vers
 * l'URL de notification du provider (config). Idempotent : le statut est
 * renvoyé à chaque rejeu sans effet de bord (même payload signé). Sans URL
 * configurée → skip silencieux (aucune sortie réseau en sandbox).
 */
final class RestaurantMarketplaceStatusConsumer implements RestaurantOutboxConsumer
{
    public function __construct(
        private readonly DeliveryAppRegistry $registry,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return in_array($eventType, [
            'restaurant.order.created.v1',
            'restaurant.order.paid.v1',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $orderId = $payload['order_id'] ?? null;

        if ($orderId === null) {
            return;
        }

        /** @var RestaurantOrder|null $order */
        $order = RestaurantOrder::query()->find($orderId);

        if (! $order instanceof RestaurantOrder) {
            return; // commande inconnue : rien à notifier
        }

        if ($order->source !== OrderSource::DELIVERY_APP) {
            return; // seules les commandes marketplace sont notifiées
        }

        $provider = $this->providerForOrder($order);

        if ($provider === null || ! $this->registry->has($provider)) {
            return;
        }

        $outboundUrl = (string) config("restaurantmanager.marketplace.{$provider}.outbound_url", '');

        if ($outboundUrl === '') {
            return; // aucune URL de notification configurée → skip (sandbox)
        }

        $adapter = $this->registry->resolve($provider);
        $payloadOut = $adapter->outboundStatusPayload($order);
        $secret = (string) config("restaurantmanager.marketplace.{$provider}.webhook_secret", '');
        $body = (string) json_encode($payloadOut, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $secret);

        $response = Http::timeout(5)
            ->withHeaders(['X-Leopardo-Signature' => $signature])
            ->post($outboundUrl, $payloadOut);

        if ($response->failed()) {
            // Transitoire : le dispatcher relancera avec backoff.
            throw new RuntimeException(sprintf(
                'Outbound status notification to %s failed with status %d.',
                $provider,
                $response->status(),
            ));
        }
    }

    /**
     * Le provider est retrouvé via le journal des événements marketplace
     * (une commande delivery_app a forcément un événement entrant associé).
     */
    private function providerForOrder(RestaurantOrder $order): ?string
    {
        /** @var RestaurantMarketplaceEvent|null $event */
        $event = RestaurantMarketplaceEvent::query()
            ->where('company_id', $order->company_id)
            ->where('order_reference', $order->reference)
            ->where('status', RestaurantMarketplaceEvent::STATUS_PROCESSED)
            ->first();

        return $event?->provider;
    }
}
