<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Delivery\Application\Actions\SummarizeDeliveryCodSettlementsAction;
use App\Modules\Delivery\Application\Services\DeliveryCodSettlementService;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliverySettlementCollectRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryCodSettlementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Règlement COD & commissions (DELIVERY-205, issue #6289) — cycle de vie
 * `pending → collected → settled → reconciled`, chaque étape idempotente et
 * verrouillée `SELECT FOR UPDATE`. Posting BC-08 via contrat (seam).
 * La synthèse (report) vit dans SummarizeDeliveryCodSettlementsAction
 * (couche Application, #6898).
 */
final class DeliveryCodSettlementController
{
    public function __construct(
        private readonly DeliveryCodSettlementService $settlements,
        private readonly SummarizeDeliveryCodSettlementsAction $summary,
    ) {}

    public function store(Request $request, int $route): JsonResponse
    {
        $settlement = $this->settlements->createForRoute($route, $this->companyId($request));

        return (new DeliveryCodSettlementResource($settlement))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function collect(DeliverySettlementCollectRequest $request, int $settlement): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->settlements->collect(
            settlementId: $settlement,
            companyId: $this->companyId($request),
            collectedMinor: (int) $validated['collected_minor'],
            commissionMinor: isset($validated['commission_minor']) ? (int) $validated['commission_minor'] : null,
        );

        return (new DeliveryCodSettlementResource($updated))->response();
    }

    public function settle(Request $request, int $settlement): JsonResponse
    {
        $this->requireAdmin($request);

        $updated = $this->settlements->settle($settlement, $this->companyId($request));

        return (new DeliveryCodSettlementResource($updated))->response();
    }

    public function reconcile(Request $request, int $settlement): JsonResponse
    {
        $this->requireAdmin($request);

        $updated = $this->settlements->reconcile($settlement, $this->companyId($request));

        return (new DeliveryCodSettlementResource($updated))->response();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DeliveryCodSettlement::query()
            ->where('company_id', $this->companyId($request));

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', (int) $request->integer('driver_id'));
        }

        return DeliveryCodSettlementResource::collection(
            $query->orderByDesc('id')->paginate(min((int) $request->integer('per_page', 15), 100)),
        );
    }

    /**
     * Réconciliation : attendu vs collecté par statut (écarts signalés).
     */
    public function report(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->summary->execute($this->companyId($request)),
        ]);
    }

    /**
     * RBAC transitoire (BC-26-D05/#6312 portera la matrice delivery.role) :
     * settle/reconcile sont réservés à l'admin (manager principal).
     */
    private function requireAdmin(Request $request): void
    {
        $employee = $request->user();

        if (! $employee instanceof Employee || ! $employee->isManager() || ! $employee->hasManagerRole('principal')) {
            abort(403, 'DELIVERY_ROLE_REQUIRED');
        }
    }

    private function companyId(Request $request): string
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }
}
