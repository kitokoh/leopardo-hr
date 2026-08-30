<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Services\RestaurantPublicOrderService;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentRequest;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGatewayRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * RESTO-805 (#6226) — Boutique publique RestaurantManager (jeton signé par
 * tenant, pattern TRAVEL-1001/#6114).
 *
 * Menu public / commande en ligne / suivi / paiement exposés SANS auth
 * utilisateur : le tenant est résolu par le jeton (middleware
 * `restaurant.public.shop`), le scope BelongsToCompany s'applique → aucune
 * fuite cross-tenant (critère d'acceptation). Rate limiting renforcé
 * (`throttle:shop-public`) + hook anti-bot CAPTCHA configurable.
 * La gestion du jeton (lecture/rotation) est authentifiée (manager).
 */
class RestaurantPublicShopController extends Controller
{
    public function __construct(private readonly RestaurantPublicOrderService $orders)
    {
    }

    public function menu(Request $request): JsonResponse
    {
        $categories = RestaurantCategory::query()
            ->orderBy('name')
            ->get();

        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));

        $products = RestaurantProduct::query()
            ->with('category')
            ->where('is_available', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'categories' => $categories->map(fn (RestaurantCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->values(),
                'products' => collect($products->items())->map(fn (RestaurantProduct $product): array => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'description' => $product->description_redacted,
                    'price_minor' => $product->price_minor,
                    'currency' => $product->currency,
                    'category_id' => $product->category_id,
                    'available' => $product->is_available,
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
            'customer_phone' => ['sometimes', 'string', 'max:30'],
            'idempotency_key' => ['sometimes', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_code' => ['required', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.menu_id' => ['sometimes', 'integer'],
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
            customerPhone: isset($data['customer_phone']) ? (string) $data['customer_phone'] : null,
        );

        $order = $result['order'];

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'status' => $order->status->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'created' => $result['created'],
                'track_url' => '/api/v1/public/restaurant/shop/orders/'.$order->reference,
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
                'status' => $order->status->value,
                'subtotal_minor' => $order->subtotal_minor,
                'tax_minor' => $order->tax_minor,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'items' => $order->items->map(fn ($item): array => [
                    'product_code' => (string) ($item->product?->code ?? ''),
                    'name' => (string) ($item->product?->name ?? ''),
                    'quantity' => (float) $item->quantity,
                    'line_total_minor' => (int) $item->line_total_minor,
                ])->values(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function initiatePayment(Request $request, string $reference, PaymentGatewayRegistry $gateways): JsonResponse
    {
        $provider = (string) $request->validate(['provider_code' => ['required', 'string', 'in:cash,mobile_money']])['provider_code'];

        $order = RestaurantOrder::query()
            ->where('reference', $reference)
            ->first();

        if (! $order instanceof RestaurantOrder) {
            abort(404, 'Commande introuvable.');
        }

        if (! in_array($order->status->value, ['open', 'draft'], true)) {
            abort(409, 'Commande non payable (statut '.$order->status->value.').');
        }

        if ($order->total_minor <= 0) {
            abort(422, 'Montant nul.');
        }

        if ($provider === 'cash') {
            // Paiement à l'encaissement (retrait ou sur place) : pas de
            // paiement en ligne créé — la commande est réglée au POS.
            return response()->json([
                'data' => [
                    'provider_code' => 'cash',
                    'status' => PaymentStatus::PENDING->value,
                    'instruction' => 'pay_at_pickup',
                    'order_reference' => $order->reference,
                ],
            ]);
        }

        $gateway = $gateways->resolve($provider);

        $result = $gateway->initiate(new InitiatePaymentRequest(
            companyId: (string) $order->company_id,
            amountMinor: $order->total_minor,
            currency: $order->currency,
            reference: $order->reference,
            idempotencyKey: 'public-'.$order->id.'-'.now()->format('YmdHis'),
        ));

        $payment = RestaurantOrderPayment::query()->create([
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'pos_session_id' => $order->pos_session_id,
            'provider_code' => $provider,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'status' => $result->status->value,
            'provider_reference' => $result->providerReference,
            'idempotency_key' => 'public-'.$order->id.'-'.(string) $result->providerReference,
        ]);

        return response()->json([
            'data' => [
                'payment_id' => $payment->id,
                'provider_code' => $provider,
                'status' => $payment->status->value,
                'provider_reference' => $payment->provider_reference,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'callback_endpoint' => '/api/v1/restaurant/payments/'.$payment->id.'/callback',
            ],
        ], 201);
    }

    // ── Gestion du jeton (authentifié, manager) ─────────────────────────────

    public function token(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $token = RestaurantPublicShopToken::query()
            ->where('company_id', $actor->company_id)
            ->first();

        if (! $token instanceof RestaurantPublicShopToken) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'id' => $token->id,
            'name' => $token->name,
            'active' => $token->active,
            'token_prefix' => substr((string) $token->token_hash, 0, 8).'…',
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
        ]]);
    }

    /**
     * (Re)génère le jeton : l'ancien est invalidé immédiatement.
     */
    public function rotateToken(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $plain = 'rshop_'.Str::random(48);

        $token = RestaurantPublicShopToken::query()->updateOrCreate(
            ['company_id' => $actor->company_id],
            [
                'token_hash' => RestaurantPublicShopToken::hash($plain),
                'name' => 'Public shop',
                'active' => true,
            ],
        );

        // Le jeton en clair n'est retourné QU'à la rotation (jamais relu).
        return response()->json(['data' => [
            'id' => $token->id,
            'token' => $plain,
            'active' => true,
        ]]);
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
