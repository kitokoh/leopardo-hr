<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantLoyaltyService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantLoyaltyCustomerRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantLoyaltyProgramRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantLoyaltyProgramRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantLoyaltyCustomerResource;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantLoyaltyProgramResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * RESTO-606 (#6211) — Programme fidélité : programme, clients, points.
 */
class RestaurantLoyaltyController extends Controller
{
    public function __construct(
        private readonly RestaurantLoyaltyService $loyalty,
    ) {
    }

    // ── Programme ────────────────────────────────────────────────────────────

    public function indexProgram(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantLoyaltyProgram::class)) {
            abort(403);
        }

        return RestaurantLoyaltyProgramResource::collection(
            RestaurantLoyaltyProgram::query()->orderBy('id')->get()
        )->response();
    }

    public function storeProgram(StoreRestaurantLoyaltyProgramRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantLoyaltyProgram::class)) {
            abort(403);
        }

        $program = RestaurantLoyaltyProgram::query()->create($request->validated());

        return (new RestaurantLoyaltyProgramResource($program))->response()->setStatusCode(201);
    }

    public function updateProgram(UpdateRestaurantLoyaltyProgramRequest $request, RestaurantLoyaltyProgram $restaurantLoyaltyProgram): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyProgram->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantLoyaltyProgram)) {
            abort(403);
        }

        $restaurantLoyaltyProgram->update($request->validated());

        return (new RestaurantLoyaltyProgramResource($restaurantLoyaltyProgram))->response();
    }

    // ── Clients fidélité ──────────────────────────────────────────────────────

    public function indexCustomers(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantLoyaltyCustomer::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        return RestaurantLoyaltyCustomerResource::collection(
            RestaurantLoyaltyCustomer::query()->orderByDesc('points')->paginate($perPage)
        )->response();
    }

    public function storeCustomer(StoreRestaurantLoyaltyCustomerRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantLoyaltyCustomer::class)) {
            abort(403);
        }

        $data = $request->validated();

        // Opt-in RGPD : exigé à l'activation (critère d'acceptation).
        if (! ($data['opt_in'] ?? false)) {
            return response()->json(['message' => 'L\'opt-in RGPD est requis pour activer la fidélité.'], 422);
        }

        unset($data['opt_in']);
        $data['opted_in_at'] = now();

        $customer = RestaurantLoyaltyCustomer::query()->create($data);

        return (new RestaurantLoyaltyCustomerResource($customer))->response()->setStatusCode(201);
    }

    public function creditCustomer(Request $request, RestaurantLoyaltyCustomer $restaurantLoyaltyCustomer): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyCustomer->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantLoyaltyCustomer)) {
            abort(403);
        }

        $request->validate(['order_id' => ['required', 'integer']]);

        /** @var RestaurantOrder|null $order */
        $order = RestaurantOrder::query()
            ->where('company_id', $actor->company_id)
            ->find($request->input('order_id'));

        if ($order === null) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        try {
            $result = $this->loyalty->creditForPaidOrder($restaurantLoyaltyCustomer, $order);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => new RestaurantLoyaltyCustomerResource($result['customer']),
            'credited' => $result['credited'],
            'already_credited' => $result['already_credited'],
        ]);
    }

    public function redeemCustomer(Request $request, RestaurantLoyaltyCustomer $restaurantLoyaltyCustomer): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyCustomer->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantLoyaltyCustomer)) {
            abort(403);
        }

        $request->validate(['points' => ['required', 'integer', 'min:1']]);

        try {
            $customer = $this->loyalty->redeem($restaurantLoyaltyCustomer, (int) $request->input('points'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantLoyaltyCustomerResource($customer))->response();
    }
}
