<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\RedeemLoyaltyPointsAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\RedeemRestaurantLoyaltyCustomerRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantLoyaltyCustomerRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantLoyaltyProgramRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantLoyaltyProgramRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantLoyaltyCustomerResource;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantLoyaltyPointsMovementResource;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantLoyaltyProgramResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-606 (#6211) — Fidélité RestaurantManager.
 *
 * - `loyalty-programs` : CRUD du programme du tenant (un seul programme
 *   actif — la création/activation désactive le précédent) ;
 * - `loyalty-customers` : opt-in RGPD (compte par contact CRM), solde,
 *   journal des mouvements, échange de points (jamais négatif).
 *
 * Le crédit automatique à la commande payée passe par l'événement outbox
 * `restaurant.order.paid.v1` (consommateur LoyaltyOrderPaidConsumer).
 */
class RestaurantLoyaltyController extends Controller
{
    public function __construct(
        private readonly RedeemLoyaltyPointsAction $redeemLoyaltyPoints,
    ) {
    }

    // ── Programme ──────────────────────────────────────────────────────────

    public function indexPrograms(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantLoyaltyProgram::class)) {
            abort(403);
        }

        $programs = RestaurantLoyaltyProgram::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        return RestaurantLoyaltyProgramResource::collection($programs)->response();
    }

    public function storeProgram(StoreRestaurantLoyaltyProgramRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantLoyaltyProgram::class)) {
            abort(403);
        }

        $data = $request->validated();

        // Un seul programme actif par tenant (spec D8).
        if (($data['is_active'] ?? true) === true) {
            RestaurantLoyaltyProgram::query()
                ->where('company_id', $actor->company_id)
                ->update(['is_active' => false]);
        }

        $program = RestaurantLoyaltyProgram::query()->create($data + ['company_id' => $actor->company_id]);

        return (new RestaurantLoyaltyProgramResource($program))->response()->setStatusCode(201);
    }

    public function showProgram(Request $request, RestaurantLoyaltyProgram $restaurantLoyaltyProgram): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyProgram->company_id) {
            abort(404);
        }

        return (new RestaurantLoyaltyProgramResource($restaurantLoyaltyProgram))->response();
    }

    public function updateProgram(
        UpdateRestaurantLoyaltyProgramRequest $request,
        RestaurantLoyaltyProgram $restaurantLoyaltyProgram,
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyProgram->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantLoyaltyProgram)) {
            abort(403);
        }

        $data = $request->validated();

        if (($data['is_active'] ?? null) === true) {
            RestaurantLoyaltyProgram::query()
                ->where('company_id', $actor->company_id)
                ->where('id', '!=', $restaurantLoyaltyProgram->id)
                ->update(['is_active' => false]);
        }

        $restaurantLoyaltyProgram->update($data);

        return (new RestaurantLoyaltyProgramResource($restaurantLoyaltyProgram))->response();
    }

    // ── Clients (opt-in, solde, échange) ───────────────────────────────────

    public function indexCustomers(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantLoyaltyCustomer::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $customers = RestaurantLoyaltyCustomer::query()
            ->orderByDesc('points')
            ->paginate($perPage);

        return RestaurantLoyaltyCustomerResource::collection($customers)->response();
    }

    public function storeCustomer(StoreRestaurantLoyaltyCustomerRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantLoyaltyCustomer::class)) {
            abort(403);
        }

        $customer = RestaurantLoyaltyCustomer::query()->firstOrCreate(
            ['company_id' => $actor->company_id, 'customer_contact_id' => $request->validated('customer_contact_id')],
            $request->validated(),
        );

        return (new RestaurantLoyaltyCustomerResource($customer))->response()->setStatusCode(201);
    }

    public function showCustomer(Request $request, RestaurantLoyaltyCustomer $restaurantLoyaltyCustomer): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyCustomer->company_id) {
            abort(404);
        }

        return (new RestaurantLoyaltyCustomerResource($restaurantLoyaltyCustomer))->response();
    }

    public function customerMovements(Request $request, RestaurantLoyaltyCustomer $restaurantLoyaltyCustomer): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyCustomer->company_id) {
            abort(404);
        }

        $perPage = max(1, min(500, (int) $request->query('per_page', 50)));

        $movements = $restaurantLoyaltyCustomer->movements()
            ->orderByDesc('id')
            ->paginate($perPage);

        return RestaurantLoyaltyPointsMovementResource::collection($movements)->response();
    }

    public function redeem(
        RedeemRestaurantLoyaltyCustomerRequest $request,
        RestaurantLoyaltyCustomer $restaurantLoyaltyCustomer,
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantLoyaltyCustomer->company_id) {
            abort(404);
        }

        if ($actor->cannot('redeem', $restaurantLoyaltyCustomer)) {
            abort(403);
        }

        $this->redeemLoyaltyPoints->redeem($restaurantLoyaltyCustomer, (int) $request->validated('points'));
        $restaurantLoyaltyCustomer->refresh();

        return (new RestaurantLoyaltyCustomerResource($restaurantLoyaltyCustomer))->response();
    }
}
