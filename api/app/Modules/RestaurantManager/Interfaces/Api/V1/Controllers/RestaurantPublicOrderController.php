<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantDeliveryWebhookService;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicOrderService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPublicOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-805 (#6226) — endpoints PUBLICS de la commande en ligne.
 *
 * Toutes ces routes sont protégées par le middleware `signed` (token signé
 * expirable généré par RestaurantPublicMenuLinkController) : le `company_id`
 * est un paramètre signé de l'URL — forger un lien pour un AUTRE tenant est
 * impossible (critère « aucun accès inter-tenant via le menu public »).
 * Le `company_id` résolu est borné à la requête courante (jamais stocké).
 */
class RestaurantPublicOrderController extends Controller
{
    public function __construct(
        private readonly RestaurantPublicOrderService $publicOrders,
        private readonly RestaurantDeliveryWebhookService $webhooks,
    ) {
    }

    public function menu(Request $request): JsonResponse
    {
        $companyId = (string) $request->query('company');

        return response()->json(['data' => $this->publicOrders->menu($companyId)])
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function store(StoreRestaurantPublicOrderRequest $request): JsonResponse
    {
        $companyId = (string) $request->query('company');
        $order = $this->publicOrders->createOrder($companyId, $request->validated());

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'status' => $order->status->value,
                'order_type' => $order->order_type->value,
                'total_minor' => (int) $order->total_minor,
                'currency' => $order->currency,
                'items_count' => $order->items()->count(),
            ],
        ], 201);
    }

    public function pay(Request $request, RestaurantOrder $order): JsonResponse
    {
        $companyId = (string) $request->query('company');
        $payment = $this->publicOrders->pay($companyId, $order, $request->validate([
            'provider_code' => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]));

        return response()->json([
            'data' => [
                'payment_id' => (int) $payment->getAttribute('id'),
                'order_reference' => $order->reference,
                'provider_code' => $payment->provider_code,
                'provider_reference' => $payment->provider_reference,
                'status' => $payment->status->value,
                'amount_minor' => (int) $payment->amount_minor,
                'currency' => $payment->currency,
            ],
        ], 201);
    }

    /**
     * Webhook entrant d'une app de livraison (RESTO-806 #6227) — public,
     * signé HMAC (le secret par tenant EST la credential).
     */
    public function deliveryWebhook(Request $request, string $provider): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $signature = (string) $request->header('X-Signature', '');
        $payload = $request->json()->all();

        $order = $this->webhooks->handle($provider, $rawBody, $signature, $payload);

        return response()->json([
            'data' => [
                'status' => 'received',
                'order_reference' => $order->reference,
                'idempotency_key' => $order->idempotency_key,
            ],
        ], 202);
    }
}
