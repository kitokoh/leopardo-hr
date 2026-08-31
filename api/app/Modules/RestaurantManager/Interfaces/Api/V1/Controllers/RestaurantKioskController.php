<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Services\RestaurantPublicOrderService;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * RESTO-807 (#6228) — Kiosque libre-service (commande + paiement).
 *
 * Implémentation v1 : surface web kiosque s'appuyant sur les contrats de la
 * boutique publique (jeton signé par tenant, RESTO-805/#6226) — menu,
 * commande et paiement SANS auth utilisateur, tenant résolu par le jeton,
 * scope BelongsToCompany → aucune fuite cross-tenant. L'étude (matériel,
 * offline, paiements) est consignée dans docs/restaurant/KIOSK_ETUDE.md ;
 * le ticket court (`ticket_number`) est généré côté serveur pour l'écran
 * kiosque.
 */
class RestaurantKioskController extends Controller
{
    public function __construct(private readonly RestaurantPublicOrderService $orders)
    {
    }

    public function menu(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));

        $products = RestaurantProduct::query()
            ->with('category')
            ->where('is_available', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'products' => collect($products->items())->map(fn (RestaurantProduct $product): array => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'price_minor' => $product->price_minor,
                    'currency' => $product->currency,
                    'category_id' => $product->category_id,
                ])->values(),
                'pagination' => [
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ],
        ]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['sometimes', 'integer'],
            'idempotency_key' => ['sometimes', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_code' => ['required', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $key = isset($data['idempotency_key'])
            ? (string) $data['idempotency_key']
            : (string) $request->header('X-Idempotency-Key', Str::uuid()->toString());

        $companyId = $this->currentCompanyId($request);

        $result = $this->orders->create(
            companyId: $companyId,
            source: OrderSource::WEB,
            items: $data['items'],
            idempotencyKey: $key,
            branchId: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
        );

        $order = $result['order'];

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'ticket_number' => (string) $order->id,
                'status' => $order->status->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'created' => $result['created'],
            ],
        ], $result['created'] ? 201 : 200);
    }

    public function track(Request $request, string $reference): JsonResponse
    {
        $order = RestaurantOrder::query()
            ->where('reference', $reference)
            ->first();

        if (! $order instanceof RestaurantOrder) {
            abort(404, 'Commande introuvable.');
        }

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'ticket_number' => (string) $order->id,
                'status' => $order->status->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'updated_at' => $order->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Résout le company_id du jeton courant (posé par le middleware
     * `restaurant.public.shop`).
     */
    private function currentCompanyId(Request $request): string
    {
        $token = (string) $request->header('X-Restaurant-Shop-Token', '');

        $shopToken = RestaurantPublicShopToken::query()
            ->where('token_hash', RestaurantPublicShopToken::hash($token))
            ->where('active', true)
            ->first();

        if (! $shopToken instanceof RestaurantPublicShopToken) {
            abort(401, 'Jeton boutique invalide.');
        }

        return (string) $shopToken->company_id;
    }
}
