<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Services\RestaurantPublicOrderService;
use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\DeliveryAppAdapterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * RESTO-806 (#6227) — Webhooks entrants des apps de livraison
 * (marketplaces Uber Eats / Glovo).
 *
 * Route PUBLIQUE (hors auth Sanctum) : la confiance est portée par la
 * signature HMAC-SHA256 fail-closed de l'adaptateur (secret par tenant,
 * spec §6.2). Le tenant est résolu depuis le payload signé puis posé via
 * TenantManager::withinTenant (pattern #5272). La commande marketplace est
 * créée via RestaurantPublicOrderService (même workflow interne, prix du
 * référentiel, jamais ceux de la marketplace) avec une clé d'idempotence
 * dérivée du webhook (`delivery-app:<provider>:<external_id>`) → rejeu sans
 * doublon (critère d'acceptation).
 */
class RestaurantDeliveryAppWebhookController extends Controller
{
    public function __construct(
        private readonly DeliveryAppAdapterRegistry $registry,
        private readonly RestaurantPublicOrderService $orders,
        private readonly TenantManager $tenants,
    ) {
    }

    public function handle(string $provider, Request $request): JsonResponse
    {
        $adapter = $this->registry->resolve($provider);

        if (! $adapter instanceof DeliveryAppAdapter) {
            return new JsonResponse(['error' => 'provider_inconnu'], 422);
        }

        $payload = $request->getContent();
        $signature = (string) $request->header('X-Leopardo-Delivery-Signature', '');

        $data = json_decode($payload, true);
        $companyId = is_array($data) && isset($data['company_id']) && is_string($data['company_id'])
            ? $data['company_id']
            : null;

        if ($companyId === null) {
            return new JsonResponse(['error' => 'invalid_payload'], 422);
        }

        if (! $adapter->verifySignature($payload, $signature, $companyId)) {
            Log::warning('Restaurant delivery-app webhook: invalid HMAC signature', [
                'provider' => $provider,
                'company_id' => $companyId,
            ]);

            return new JsonResponse(['error' => 'invalid_signature'], 401);
        }

        /** @var Company|null $company */
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return new JsonResponse(['error' => 'company_not_found'], 404);
        }

        $externalId = is_array($data) && isset($data['order']['external_id']) && is_string($data['order']['external_id'])
            ? $data['order']['external_id']
            : null;

        if ($externalId === null || $externalId === '') {
            return new JsonResponse(['error' => 'missing_external_id'], 422);
        }

        $items = is_array($data['order']['items'] ?? null) ? $data['order']['items'] : [];
        $normalized = $adapter->normalizeItems($items);

        if ($normalized === []) {
            return new JsonResponse(['error' => 'no_valid_items'], 422);
        }

        // Clé d'idempotence dérivée du webhook, bornée à 64 chars
        // (contrainte `restaurant_orders.idempotency_key`) : hash SHA-256
        // tronqué — rejeu du même external_id → même commande, jamais de
        // doublon, quelle que soit la longueur de l'identifiant marketplace.
        $idempotencyKey = 'da-'.substr(hash('sha256', $provider.'|'.$externalId), 0, 60);

        $result = $this->tenants->withinTenant($company, fn (): array => $this->orders->create(
            companyId: (string) $company->id,
            source: OrderSource::DELIVERY_APP,
            items: $normalized,
            idempotencyKey: $idempotencyKey,
            customerPhone: isset($data['order']['customer']['phone']) && is_string($data['order']['customer']['phone'])
                ? $data['order']['customer']['phone']
                : null,
        ));

        $order = $result['order'];

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'status' => $order->status->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'created' => $result['created'],
            ],
        ], $result['created'] ? 201 : 200);
    }
}
