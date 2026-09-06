<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Application\Actions\CreateDeliveryAction;
use App\Modules\Delivery\Domain\Contracts\DeliveryRepositoryInterface;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliveryStoreRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryResource;
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
 * La création (idempotence source, référence DLV, course 23505) vit dans
 * CreateDeliveryAction (couche Application, #6898).
 */
final class DeliveryController
{
    public function __construct(
        private readonly DeliveryRepositoryInterface $deliveries,
        private readonly CreateDeliveryAction $createDelivery,
    ) {}

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
        $delivery = $this->createDelivery->execute($this->companyId($request), $request->validated());

        // Rejeu (source contractuelle) → 200 ; création → 201.
        return (new DeliveryResource($delivery))
            ->response()
            ->setStatusCode($delivery->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
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
