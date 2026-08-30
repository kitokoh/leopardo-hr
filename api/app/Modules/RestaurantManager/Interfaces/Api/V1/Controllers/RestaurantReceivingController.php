<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use App\Modules\RestaurantManager\Infrastructure\Services\ReceivingService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantReceivingRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantReceivingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-503 (#6202) — Réceptions (entrées stock, coût moyen pondéré).
 *
 * `POST /restaurant/receivings` : entrées de stock atomiques (mouvements
 * `receiving` verrouillés) + recalcul du coût moyen pondéré par ingrédient.
 * Idempotence : une `reference` client unique par tenant — un rejeu avec la
 * même référence est refusé (409, déjà réceptionnée) ; sans référence, RCV-…
 * générée. 404 sûr cross-tenant.
 */
class RestaurantReceivingController extends Controller
{
    public function __construct(private readonly ReceivingService $receiving)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantReceiving::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $receivings = RestaurantReceiving::query()
            ->when($request->has('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->has('purchase_order_id'), fn ($query) => $query->where('purchase_order_id', (int) $request->query('purchase_order_id')))
            ->orderByDesc('received_at')
            ->paginate($perPage);

        return RestaurantReceivingResource::collection($receivings)->response();
    }

    public function store(StoreRestaurantReceivingRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantReceiving::class)) {
            abort(403);
        }

        $data = $request->validated();

        // Idempotence par référence client (unique par tenant).
        if (! empty($data['reference'])) {
            $exists = RestaurantReceiving::query()
                ->where('company_id', $actor->company_id)
                ->where('reference', $data['reference'])
                ->exists();

            if ($exists) {
                abort(409, 'A receiving with this reference already exists.');
            }
        }

        $receiving = $this->receiving->receive(
            companyId: $actor->company_id,
            branchId: (int) $data['branch_id'],
            lines: $data['lines'],
            purchaseOrderId: $data['purchase_order_id'] ?? null,
            supplierId: $data['supplier_id'] ?? null,
            note: $data['note_redacted'] ?? null,
            reference: $data['reference'] ?? null,
            userId: $actor->id,
        );

        return (new RestaurantReceivingResource($receiving))->response()->setStatusCode(201);
    }
}
