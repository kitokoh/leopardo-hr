<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailBuyerAccountService;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Application\Services\RetailOnlineOrderService;
use App\Modules\Retail\Application\Services\RetailPaymentService;
use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Modules\Retail\Domain\Models\RetailOnlinePaymentIntent;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreMarketOrderRequest;
use App\Shared\Contracts\Delivery\PublicDeliveryStatusProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commandes PUBLIQUES de la marketplace Leopardo Marche
 * (BC-17 RETAIL, #7808).
 *
 * Routes isolees (`throttle:shop-public`, SANS auth — spec §3.2) :
 *   POST /public/market/orders             → checkout invite (COD v1)
 *   GET  /public/market/orders/{reference} → suivi public (?token= exige)
 *
 * Le tenant vendeur est resolu PAR SLUG dans le corps du checkout (vendeur
 * opt-in uniquement, 404 fail-closed) puis pose via
 * `TenantManager::withinTenant()` + marqueur `tenant_scope_required` :
 * toute ecriture atterrit chez le BON vendeur (pattern marketplace
 * TravelAgency #7737). Prix relus en base, totaux serveur, idempotence par
 * cle unique par tenant (rejeu → 200 meme payload). Le suivi exige le
 * jeton `tracking_token` (64 hex) : absent ou errone → 404, et le DTO
 * n'expose AUCUNE donnee interne (ni company_id, ni stock, ni marges).
 *
 * Paiement en ligne (#7812) : `payment_method = online` cree un intent de
 * paiement (RetailPaymentService, provider par config env — abstraction
 * locale en attendant BC-21/PR #7732) et la reponse embarque
 * `payment.{method,intent_reference,status,checkout_url}`. COD (`cash`)
 * reste le defaut. Le suivi public expose `payment.{method,status}`
 * UNIQUEMENT (fail-closed).
 */
class RetailMarketOrderPublicController extends Controller
{
    public function __construct(
        private readonly RetailMarketplaceService $marketplace,
        private readonly RetailOnlineOrderService $orders,
        private readonly RetailPaymentService $payments,
        private readonly TenantManager $tenants,
        private readonly PublicDeliveryStatusProvider $deliveryStatus,
        private readonly RetailBuyerAccountService $accounts,
    ) {}

    /**
     * POST /public/market/orders — checkout invite (spec §3.2).
     */
    public function store(StoreMarketOrderRequest $request): JsonResponse
    {
        $eligible = $this->marketplace->eligibleCompanyIds();

        $company = $this->marketplace->eligibleCompanyForSlug(
            $eligible,
            (string) $request->input('seller'),
        );

        if (! $company instanceof Company) {
            // Vendeur inconnu, boutique desactivee ou tenant non eligible :
            // fail-closed uniforme (pas de probing).
            abort(404);
        }

        /** @var list<array{product_id: int, quantity: int}> $items */
        $items = array_map(
            static fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ],
            (array) $request->input('items'),
        );

        // #7814 — rattachement OPTIONNEL a un compte acheteur : si la
        // requete porte un jeton buyer VALIDE, la commande est liee au
        // compte (historique cross-tenant + avis verifies). Jeton absent ou
        // invalide → checkout invite inchange (jamais bloquant).
        $buyer = $this->accounts->buyerForBearerToken($request->bearerToken());

        app()->instance('tenant_scope_required', true);

        $paymentMethod = (string) $request->input('payment_method');

        try {
            /** @var array{order: RetailOrder, created: bool, intent: RetailOnlinePaymentIntent|null} $result */
            $result = $this->tenants->withinTenant(
                $company,
                function () use ($request, $company, $items, $paymentMethod, $buyer): array {
                    $result = $this->orders->createGuestOrder(
                        companyId: (string) $company->id,
                        items: $items,
                        customer: [
                            'name' => (string) $request->input('customer.name'),
                            'phone' => (string) $request->input('customer.phone'),
                            'email' => $request->filled('customer.email') ? (string) $request->input('customer.email') : null,
                        ],
                        delivery: [
                            'address' => (string) $request->input('delivery.address'),
                            'city' => (string) $request->input('delivery.city'),
                            'notes' => $request->filled('delivery.notes') ? (string) $request->input('delivery.notes') : null,
                        ],
                        idempotencyKey: (string) $request->input('idempotency_key'),
                        buyerId: $buyer !== null ? (int) $buyer->id : null,
                        paymentMethod: $paymentMethod,
                    );

                    // Paiement en ligne : intent cree (ou retrouve, rejeu
                    // idempotent) DANS le contexte tenant du vendeur.
                    $intent = $paymentMethod === RetailPaymentMethod::Online->value
                        ? $this->payments->createIntentForOrder($result['order'])
                        : null;

                    return $result + ['intent' => $intent];
                },
            );
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }

        $order = $result['order'];
        $intent = $result['intent'];

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'tracking_token' => $order->tracking_token,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'seller' => (string) $company->slug,
                'payment' => [
                    'method' => $this->paymentMethodValue($order),
                    'intent_reference' => $intent?->intent_reference,
                    'status' => $intent?->status->value,
                    'checkout_url' => $intent?->checkout_url,
                ],
            ],
        ], $result['created'] ? 201 : 200);
    }

    /**
     * GET /public/market/orders/{reference}?token= — suivi public.
     * 404 fail-closed : jeton absent, errone ou reference inconnue.
     */
    public function track(Request $request, string $reference): JsonResponse
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === '' || strlen($token) > 64) {
            abort(404);
        }

        /** @var RetailOrder|null $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', $reference)
            ->where('tracking_token', $token)
            ->where('source', 'online')
            ->first();

        if (! $order instanceof RetailOrder) {
            abort(404);
        }

        $companyId = (string) $order->company_id;
        $settings = $this->marketplace->settingsByCompanyId([$companyId]);
        $companies = $this->marketplace->companiesById([$companyId]);
        $sellerSettings = $settings[$companyId] ?? null;
        $company = $companies[$companyId] ?? null;

        $items = RetailOrderItem::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('order_id', (int) $order->id)
            ->orderBy('line_index')
            ->get()
            ->map(static fn (RetailOrderItem $item): array => [
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
                'line_total_minor' => (int) $item->line_total_minor,
            ])
            ->values()
            ->all();

        // Handoff BC-26 (#7811) : si une livraison `retail_online` existe pour
        // cette commande, son état public (statut + horodatages, DTO
        // fail-closed) enrichit le suivi — lecture via le port Shared exposé
        // par le module Delivery, jamais de requête directe sur ses tables.
        // Optionnel et défensif : `delivery` est absent tant qu'aucune
        // livraison n'existe (vendeur sans module BC-26, commande pending).
        $delivery = $this->deliveryStatus->findBySourceReference(
            $companyId,
            'retail_online',
            (string) $order->reference,
        );

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'fulfillment_status' => $order->fulfillment_status?->value,
                'total_minor' => (int) $order->total_minor,
                'currency' => $order->currency,
                'seller' => [
                    'name' => $sellerSettings?->shop_name,
                    'slug' => $company !== null ? (string) $company->slug : null,
                    'city' => $sellerSettings?->city,
                ],
                'items' => $items,
                'payment' => [
                    // Fail-closed : moyen + statut de paiement UNIQUEMENT
                    // (jamais d'URL de checkout ni de reference d'intent).
                    'method' => $this->paymentMethodValue($order),
                    'status' => $order->payment_status ?? 'pending',
                ],
                'timeline' => [
                    'placed_at' => $order->created_at?->toIso8601String(),
                    'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                    'shipped_at' => $order->shipped_at?->toIso8601String(),
                    'delivered_at' => $order->delivered_at?->toIso8601String(),
                ],
                'delivery' => $delivery?->toArray(),
            ],
        ]);
    }

    /**
     * Moyen de paiement expose publiquement — lecture BRUTE de l'attribut
     * (les commandes anterieures a #7812 n'ont pas de `payment_method` :
     * fallback COD `cash`, defaut historique du checkout).
     */
    private function paymentMethodValue(RetailOrder $order): string
    {
        $raw = $order->getAttributes()['payment_method'] ?? null;

        return is_string($raw) && $raw !== '' ? $raw : RetailPaymentMethod::Cash->value;
    }
}
