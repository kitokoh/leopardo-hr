<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Actions\CreateOnlineOrderAction;
use App\Modules\RestaurantManager\Application\Actions\PayOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\PayRestaurantOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreOnlineOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantOrderPaymentResource;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantOrderResource;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPublicMenuResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * RESTO-805 (#6226) — Boutique en ligne publique (jeton signé par tenant).
 *
 * Menu, commande et paiement exposés SANS auth utilisateur : le tenant est
 * résolu par le jeton (middleware `restaurant.public.shop`), le scope
 * BelongsToCompany s'applique → aucune donnée cross-tenant (critère
 * d'acceptation). Throttling renforcé (`throttle:restaurant-shop-public`).
 */
class RestaurantPublicShopController extends Controller
{
    /**
     * Menu public du tenant (catégories + produits actifs/disponibles).
     */
    public function menu(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') !== null
            ? (int) $request->query('branch_id')
            : null;

        $categories = RestaurantCategory::query()
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->with(['products' => fn ($query) => $query
                ->where('status', RestaurantRecordStatus::ACTIVE->value)
                ->where('is_available', true)
                ->when($branchId !== null, fn ($q) => $q->where(function ($sub) use ($branchId): void {
                    $sub->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }))
                ->orderBy('name'),
            ])
            ->when($branchId !== null, fn ($query) => $query->where(function ($sub) use ($branchId): void {
                $sub->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return RestaurantPublicMenuResource::collection($categories)->response();
    }

    /**
     * Branches actives du tenant (sélecteur du kiosque / boutiques).
     */
    public function branches(): JsonResponse
    {
        $branches = RestaurantBranch::query()
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json(['data' => $branches]);
    }

    /**
     * Création d'une commande en ligne (source online, idempotente).
     */
    public function storeOrder(StoreOnlineOrderRequest $request): JsonResponse
    {
        $order = app(CreateOnlineOrderAction::class)->create($request->validated());

        return (new RestaurantOrderResource($order->load(['items', 'payments'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Suivi public d'une commande par référence (statut + totaux uniquement).
     */
    public function show(string $reference): JsonResponse
    {
        $order = RestaurantOrder::query()
            ->where('reference', $reference)
            ->with('items')
            ->first();

        if (! $order instanceof RestaurantOrder) {
            abort(404);
        }

        // Le scope BelongsToCompany borne déjà à la company courante ;
        // contrôle explicite de sécurité (bindings hors scope).
        if ($order->company_id !== currentCompany()->id) {
            abort(404);
        }

        return (new RestaurantOrderResource($order))->response();
    }

    /**
     * Paiement en ligne d'une commande (cash → confirmé immédiat ;
     * mobile_money → pending, confirmé par callback signé RESTO-407).
     */
    public function pay(string $reference, PayRestaurantOrderRequest $request): JsonResponse
    {
        $order = RestaurantOrder::query()
            ->where('reference', $reference)
            ->first();

        if (! $order instanceof RestaurantOrder) {
            abort(404);
        }

        if ($order->company_id !== currentCompany()->id) {
            abort(404);
        }

        $payment = app(PayOrderAction::class)->payForCompany(
            companyId: currentCompany()->id,
            order: $order,
            data: $request->validated(),
        );

        return (new RestaurantOrderPaymentResource($payment))->response()->setStatusCode(201);
    }

    /**
     * État du jeton de boutique publique (jamais le jeton en clair).
     */
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
     * (Re)génère le jeton de boutique publique : l'ancien est invalidé
     * immédiatement. Le jeton en clair n'est retourné QU'à la rotation.
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

        return response()->json(['data' => [
            'id' => $token->id,
            'token' => $plain,
            'active' => true,
        ]]);
    }
}
