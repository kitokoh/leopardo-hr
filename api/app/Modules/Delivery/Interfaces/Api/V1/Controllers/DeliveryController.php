<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Domain\Contracts\DeliveryRepositoryInterface;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliveryStoreRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryResource;
use App\Modules\Delivery\Domain\ValueObjects\DeliveryReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD des livraisons (DELIVERY-201, issue #6285) — RBAC manager
 * (`api.manager`) ; la matrice fine livreur/dispatcher/admin est le scope de
 * BC-26-D05.
 *
 * Isolation tenant : la company est résolue depuis l'employé authentifié,
 * jamais par un id d'URL (fail-closed #3727) ; le repository est scopé.
 */
final class DeliveryController
{
    public function __construct(private readonly DeliveryRepositoryInterface $deliveries) {}

    /**
     * Liste des livraisons du tenant (filtres statut/source/date, pagination).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Delivery::query()
            ->where('company_id', $this->companyId($request));

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('source')) {
            $query->where('source', (string) $request->string('source'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->string('to'));
        }

        return DeliveryResource::collection(
            $query->orderByDesc('created_at')->paginate(min((int) $request->integer('per_page', 15), 100)),
        );
    }

    public function store(DeliveryStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $companyId = $this->companyId($request);

        // Référence DLV-YYYY-NNNNNN : séquence du jour par tenant (borne
        // d'unicité) — l'index unique (company_id, reference) protège la course.
        $sequence = (int) Delivery::query()
            ->where('company_id', $companyId)
            ->whereYear('created_at', now()->year)
            ->max('id') + 1;

        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create([
            'company_id' => $companyId,
            'reference' => DeliveryReference::fromSequence(now()->year, $sequence)->toString(),
            'source' => $validated['source'],
            'source_reference' => $validated['source_reference'] ?? null,
            'type' => $validated['type'],
            'status' => 'created',
            'weight_grams' => $validated['weight_grams'] ?? null,
            'volume_cm3' => $validated['volume_cm3'] ?? null,
            'declared_value_minor' => $validated['declared_value_minor'] ?? 0,
            'cod_amount_minor' => $validated['cod_amount_minor'] ?? null,
            'pickup_contact' => $validated['pickup_contact'] ?? null,
            'pickup_address' => $validated['pickup_address'] ?? null,
            'dropoff_contact' => $validated['dropoff_contact'],
            'dropoff_phone' => $validated['dropoff_phone'] ?? null,
            'dropoff_address' => $validated['dropoff_address'],
            'window_from' => $validated['window_from'] ?? null,
            'window_to' => $validated['window_to'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
        ]);

        return (new DeliveryResource($delivery))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $delivery): JsonResponse
    {
        $found = $this->deliveries->findForCompany($delivery, $this->companyId($request));

        if ($found === null) {
            abort(404, 'DELIVERY_NOT_FOUND');
        }

        return (new DeliveryResource($found))->response();
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
