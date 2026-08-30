<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPromotionService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPromotionRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantPromotionRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPromotionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * RESTO-607 (#6212) — Promotions (types, bornes, cumul, codes).
 *
 * CRUD + `validate` : calcul serveur de la remise (bornes de période, minimum
 * de commande, plafond d'utilisation, cumul contrôlé — une promo par addition).
 */
class RestaurantPromotionController extends Controller
{
    public function __construct(
        private readonly RestaurantPromotionService $promotions,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantPromotion::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        return RestaurantPromotionResource::collection(
            RestaurantPromotion::query()
                ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
                ->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', $request->query('is_active') === 'true'))
                ->orderBy('code')
                ->paginate($perPage)
        )->response();
    }

    public function store(StoreRestaurantPromotionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantPromotion::class)) {
            abort(403);
        }

        $promo = RestaurantPromotion::query()->create($request->validated());

        return (new RestaurantPromotionResource($promo))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantPromotion $restaurantPromotion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPromotion->company_id) {
            abort(404);
        }

        return (new RestaurantPromotionResource($restaurantPromotion))->response();
    }

    public function update(UpdateRestaurantPromotionRequest $request, RestaurantPromotion $restaurantPromotion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPromotion->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantPromotion)) {
            abort(403);
        }

        $restaurantPromotion->update($request->validated());

        return (new RestaurantPromotionResource($restaurantPromotion))->response();
    }

    public function destroy(Request $request, RestaurantPromotion $restaurantPromotion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPromotion->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantPromotion)) {
            abort(403);
        }

        $restaurantPromotion->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * Validation serveur d'un code promo sur un montant de commande.
     */
    public function validate(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'order_total_minor' => ['required', 'integer', 'min:0'],
        ]);

        /** @var RestaurantPromotion|null $promo */
        $promo = RestaurantPromotion::query()
            ->where('company_id', $actor->company_id)
            ->where('code', (string) $request->input('code'))
            ->first();

        if ($promo === null) {
            return response()->json(['message' => 'Code promo inconnu.'], 404);
        }

        try {
            $result = $this->promotions->validateAndCompute($promo, (int) $request->input('order_total_minor'), $actor->company_id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'code' => $promo->code,
                'title' => $promo->title,
                'discount_type' => $promo->discount_type,
                'discount_minor' => $result['discount_minor'],
                'valid' => $result['valid'],
            ],
        ]);
    }
}
