<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\CreateOnlineOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicBranchResolver;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicOrderService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPublicSlugOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-902 (#7747) — Commande en ligne PUBLIQUE depuis la page par slug.
 *
 * Routes publiques SANS auth (`throttle:shop-public`, groupe RESTO-901) :
 *   POST /public/restaurants/{slug}/orders            → création (panier)
 *   GET  /public/restaurants/{slug}/orders/{ref}      → suivi (statut, items, total)
 *   POST /public/restaurants/{slug}/orders/{ref}/pay  → paiement cash / mobile_money
 *
 * Réutilise le pipeline RESTO-805 — AUCUN second pipeline de commande :
 *  - création via `CreateOnlineOrderAction` (prix + TVA serveur, idempotence
 *    par `idempotency_key`, source `online`, événement outbox
 *    `restaurant.order.created.v1`) ;
 *  - paiement via `RestaurantPublicOrderService::pay()` (contrat
 *    PaymentGatewayInterface). Seuls `cash` (alias public
 *    `cash_on_delivery`) et `mobile_money` sont ouverts ici — la passerelle
 *    carte (PSP) reste volontairement fermée (spec #5272 en attente).
 *
 * Garde-fous fail-closed (preset multitenancy) :
 *  - branche résolue par `RestaurantPublicBranchResolver` (is_public + active
 *    + société ni suspendue/expirée + flag verticale) → 404 uniforme sinon ;
 *  - produits identifiés par leur `code` métier (l'identifiant exposé par le
 *    menu public RESTO-901/805 — jamais d'ID interne) et acceptés UNIQUEMENT
 *    si `is_published_online` ET `is_available` ET actifs, servis par la
 *    branche (branch_id égal ou company-wide) → 422 sinon ;
 *  - suivi par référence `RST-…` non énumérable, borné à LA branche du slug,
 *    réponse sans PII (le `note_redacted` qui porte le contact client n'est
 *    jamais renvoyé).
 */
class RestaurantPublicSlugOrderController extends Controller
{
    public function __construct(
        private readonly RestaurantPublicBranchResolver $resolver,
        private readonly CreateOnlineOrderAction $createOnlineOrder,
        private readonly RestaurantPublicOrderService $publicOrders,
    ) {}

    public function store(StoreRestaurantPublicSlugOrderRequest $request, string $slug): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->resolver->within($slug, function (RestaurantBranch $branch) use ($validated): JsonResponse {
            /** @var array<int, array<string, mixed>> $lines */
            $lines = is_array($validated['items'] ?? null) ? $validated['items'] : [];

            $items = [];
            $itemNotes = [];

            foreach ($lines as $line) {
                $code = isset($line['product_code']) && is_string($line['product_code']) ? $line['product_code'] : '';
                $product = $this->publishedProduct($branch, $code);

                $items[] = [
                    'product_id' => (int) $product->getAttribute('id'),
                    'quantity' => is_numeric($line['quantity'] ?? null) ? (float) $line['quantity'] : 0.0,
                ];

                if (isset($line['note']) && is_string($line['note']) && trim($line['note']) !== '') {
                    $itemNotes[] = $product->name.': '.mb_substr(trim($line['note']), 0, 200);
                }
            }

            $orderType = isset($validated['order_type']) && is_string($validated['order_type'])
                ? $validated['order_type']
                : 'pickup';

            $idempotencyKey = isset($validated['idempotency_key']) && is_string($validated['idempotency_key']) && $validated['idempotency_key'] !== ''
                ? $validated['idempotency_key']
                : null;

            $order = $this->createOnlineOrder->create([
                'branch_id' => (int) $branch->getAttribute('id'),
                // Alias public `pickup` → `takeaway` interne (enum OrderType).
                'order_type' => $orderType === 'pickup' ? 'takeaway' : $orderType,
                'items' => $items,
                'note_redacted' => $this->buildNote($validated, $itemNotes),
                'idempotency_key' => $idempotencyKey,
            ]);

            $created = $order->wasRecentlyCreated;

            return response()->json([
                'data' => [
                    'reference' => $order->reference,
                    'status' => $order->status->value,
                    'order_type' => $order->order_type->value,
                    'subtotal_minor' => (int) $order->subtotal_minor,
                    'tax_minor' => (int) $order->tax_minor,
                    'total_minor' => (int) $order->total_minor,
                    'currency' => $order->currency,
                    'items_count' => $order->items()->count(),
                    'created' => $created,
                ],
            ], $created ? 201 : 200);
        });
    }

    public function track(string $slug, string $ref): JsonResponse
    {
        return $this->resolver->within($slug, function (RestaurantBranch $branch) use ($ref): JsonResponse {
            $order = $this->branchOrder($branch, $ref);

            return response()->json([
                'data' => [
                    'reference' => $order->reference,
                    'status' => $order->status->value,
                    'order_type' => $order->order_type->value,
                    'subtotal_minor' => (int) $order->subtotal_minor,
                    'tax_minor' => (int) $order->tax_minor,
                    'total_minor' => (int) $order->total_minor,
                    'currency' => $order->currency,
                    'items' => $order->items
                        ->map(fn (RestaurantOrderItem $item): array => [
                            'name' => (string) ($item->product->name ?? ''),
                            'quantity' => (float) $item->quantity,
                            'unit_price_minor' => (int) $item->unit_price_minor,
                            'line_total_minor' => (int) $item->line_total_minor,
                        ])
                        ->values()
                        ->all(),
                    'updated_at' => $order->updated_at?->toIso8601String(),
                ],
            ]);
        });
    }

    public function pay(Request $request, string $slug, string $ref): JsonResponse
    {
        /** @var array{provider_code?: string|null, idempotency_key?: string|null} $validated */
        $validated = $request->validate([
            // #7728 — encaissement réel via les profils de paiement du tenant :
            // provider optionnel (défaut = premier provider EN LIGNE configuré,
            // carte prioritaire), le service fail-closed refuse cash/terminal ici.
            'provider_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        return $this->resolver->within($slug, function (RestaurantBranch $branch) use ($ref, $validated): JsonResponse {
            $order = $this->branchOrder($branch, $ref);

            $result = $this->publicOrders->pay((string) $order->company_id, $order, [
                'provider_code' => $validated['provider_code'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
            ]);

            $payment = $result['payment'];

            return response()->json([
                'data' => [
                    'order_reference' => $order->reference,
                    'provider_code' => $payment->provider_code,
                    'provider_reference' => $payment->provider_reference,
                    'status' => $payment->status->value,
                    'amount_minor' => (int) $payment->amount_minor,
                    'currency' => $payment->currency,
                    // #7728 — URL du checkout hébergé quand la passerelle en
                    // fournit une ; null pour les flux confirmés par callback.
                    'checkout_url' => $result['checkout_url'],
                ],
            ], 201);
        });
    }

    /**
     * Commande de LA branche du slug, par référence non énumérable — 404
     * uniforme si la référence est inconnue ou appartient à une autre
     * branche/tenant (le scope BelongsToCompany est déjà posé par le
     * resolver).
     */
    private function branchOrder(RestaurantBranch $branch, string $ref): RestaurantOrder
    {
        $order = RestaurantOrder::query()
            ->with('items.product')
            ->where('branch_id', $branch->getAttribute('id'))
            ->where('reference', $ref)
            ->first();

        if (! $order instanceof RestaurantOrder) {
            abort(404);
        }

        return $order;
    }

    /**
     * Produit commandable depuis la page publique : publié en ligne
     * (`is_published_online`), disponible, actif, servi par la branche
     * (ou company-wide) — exactement le périmètre du menu public RESTO-901.
     */
    private function publishedProduct(RestaurantBranch $branch, string $code): RestaurantProduct
    {
        $product = RestaurantProduct::query()
            ->where('code', $code)
            ->where('is_published_online', true)
            ->where('is_available', true)
            ->where('status', RestaurantRecordStatus::ACTIVE)
            ->where(function ($query) use ($branch): void {
                $query->where('branch_id', $branch->getAttribute('id'))->orWhereNull('branch_id');
            })
            ->first();

        if (! $product instanceof RestaurantProduct) {
            abort(422, __('restaurant.public_shop.product_unavailable'));
        }

        return $product;
    }

    /**
     * Note interne de la commande : contact client (pattern RESTO-805,
     * champ `note_redacted` jamais renvoyé publiquement) + note globale +
     * notes par article (les lignes de commande n'ont pas de colonne note).
     *
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $itemNotes
     */
    private function buildNote(array $validated, array $itemNotes): string
    {
        $name = isset($validated['customer_name']) && is_string($validated['customer_name']) ? $validated['customer_name'] : '';
        $phone = isset($validated['customer_phone']) && is_string($validated['customer_phone']) ? $validated['customer_phone'] : '';

        $parts = ['Client: '.mb_substr(trim($name.' '.$phone), 0, 120)];

        if (isset($validated['note']) && is_string($validated['note']) && trim($validated['note']) !== '') {
            $parts[] = mb_substr(trim($validated['note']), 0, 500);
        }

        return mb_substr(implode(' | ', array_merge($parts, $itemNotes)), 0, 2000);
    }
}
