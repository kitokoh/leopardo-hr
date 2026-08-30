<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\CreateMarketplaceOrderAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMarketplaceEvent;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\DeliveryAppRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-806 (#6227) — Webhooks entrants des apps de livraison.
 *
 * Public (sans auth utilisateur) : le tenant est résolu par le jeton de
 * boutique (`?token=` / `X-Restaurant-Shop-Token`, middleware
 * `restaurant.public.shop`) ; la confiance est portée par la signature HMAC
 * provider (fail-closed) et l'idempotence par `event_id` provider
 * (UNIQUE company_id + provider + event_id) — rejeu → même réponse, aucune
 * commande dupliquée.
 */
class RestaurantMarketplaceWebhookController extends Controller
{
    public function __construct(
        private readonly DeliveryAppRegistry $registry,
        private readonly CreateMarketplaceOrderAction $createOrder,
    ) {
    }

    public function handle(string $provider, Request $request): JsonResponse
    {
        if (! $this->registry->has($provider)) {
            abort(404, __('restaurant.marketplace.unknown_provider'));
        }

        $adapter = $this->registry->resolve($provider);

        $rawBody = $request->getContent();
        $signature = (string) $request->header($adapter->inboundSignatureHeader(), '');
        $secret = (string) config("restaurantmanager.marketplace.{$provider}.webhook_secret", '');

        if (! $adapter->verifySignature($rawBody, $signature, $secret !== '' ? $secret : null)) {
            abort(401, __('restaurant.marketplace.invalid_signature'));
        }

        $companyId = currentCompany()->id;

        try {
            $inbound = $adapter->parseInboundOrder($rawBody);
        } catch (\Throwable $exception) {
            $this->recordEvent($companyId, $provider, 'malformed', 'failed', null, null, $exception->getMessage());

            throw $exception;
        }

        // Idempotence : un événement provider déjà traité ne crée pas de doublon.
        $existing = RestaurantMarketplaceEvent::query()
            ->where('company_id', $companyId)
            ->where('provider', $provider)
            ->where('event_id', $inbound->eventId)
            ->first();

        if ($existing instanceof RestaurantMarketplaceEvent) {
            return response()->json([
                'data' => [
                    'status' => 'replayed',
                    'order_reference' => $existing->order_reference,
                    'event_id' => $existing->event_id,
                ],
            ]);
        }

        try {
            $order = $this->createOrder->create($companyId, $inbound);

            $this->recordEvent(
                companyId: $companyId,
                provider: $provider,
                eventId: $inbound->eventId,
                status: RestaurantMarketplaceEvent::STATUS_PROCESSED,
                orderReference: $order->reference,
                payload: $this->redact($inbound),
            );

            return response()->json([
                'data' => [
                    'status' => 'processed',
                    'order_reference' => $order->reference,
                    'event_id' => $inbound->eventId,
                ],
            ], 201);
        } catch (\Throwable $exception) {
            $this->recordEvent(
                companyId: $companyId,
                provider: $provider,
                eventId: $inbound->eventId,
                status: RestaurantMarketplaceEvent::STATUS_FAILED,
                orderReference: null,
                payload: $this->redact($inbound),
                error: $exception->getMessage(),
            );

            throw $exception;
        }
    }

    private function recordEvent(
        string $companyId,
        string $provider,
        string $eventId,
        string $status,
        ?string $orderReference,
        ?array $payload,
        ?string $error = null,
    ): void {
        try {
            RestaurantMarketplaceEvent::query()->create([
                'company_id' => $companyId,
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => 'order.created',
                'idempotency_key' => $provider.':'.$eventId,
                'status' => $status,
                'payload_redacted' => $payload,
                'last_error' => $error !== null ? mb_substr($error, 0, 500) : null,
                'order_reference' => $orderReference,
                'processed_at' => $status === RestaurantMarketplaceEvent::STATUS_PROCESSED ? now() : null,
            ]);
        } catch (\Throwable) {
            // Le journal d'audit ne doit jamais faire échouer le webhook.
        }
    }

    /**
     * Redaction : uniquement les champs métier nécessaires (audit RGPD —
     * jamais de données personnelles superflues, cf. RESTO-904).
     */
    private function redact(object $inbound): array
    {
        return [
            'provider' => $inbound->provider,
            'items_count' => count($inbound->items),
            'currency' => $inbound->currency,
            'branch_code' => $inbound->branchCode,
        ];
    }
}
