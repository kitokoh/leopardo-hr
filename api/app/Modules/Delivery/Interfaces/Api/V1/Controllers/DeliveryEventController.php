<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Application\Services\DeliveryEventService;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryTrackingShare;
use App\Modules\Delivery\Infrastructure\Services\DeliveryTrackingShareService;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliveryEventStoreRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryEventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Événements de tracking (DELIVERY-204, issue #6288) — RBAC manager
 * (`api.manager`) ; l'écriture livreur (scope par employé) est DELIVERY-203.
 *
 * Idempotence (rejeu mobile/edge → jamais de doublon) et POD obligatoire
 * pour `delivered` vivent dans le service transactionnel.
 */
final class DeliveryEventController
{
    public function __construct(
        private readonly DeliveryEventService $events,
        private readonly DeliveryTrackingShareService $shares,
    ) {}

    public function store(DeliveryEventStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $companyId = $this->companyId($request);

        $event = $this->events->record(
            companyId: $companyId,
            deliveryId: (int) $validated['delivery_id'],
            type: (string) $validated['type'],
            eventAt: isset($validated['event_at'])
                ? \Illuminate\Support\Carbon::parse($validated['event_at'])
                : now(),
            latitude: isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            longitude: isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            origin: (string) ($validated['origin'] ?? 'mobile'),
            idempotencyKey: $validated['idempotency_key'] ?? null,
            proofDocumentId: $validated['proof_document_id'] ?? null,
        );

        return (new DeliveryEventResource($event))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Génère un lien de suivi public borné (expiration courte, anti-énumération).
     */
    public function link(Request $request, int $delivery): JsonResponse
    {
        $found = $this->findDelivery($delivery, $this->companyId($request));

        $share = $this->shares->createShare($found);

        return response()->json([
            'data' => [
                'tracking_url' => $this->shares->trackingUrl($share),
                'expires_at' => $share->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Ligne du temps interne (RBAC manager) — la même que le lien public,
     * sans exposer le token.
     */
    public function timeline(Request $request, int $delivery): AnonymousResourceCollection
    {
        $found = $this->findDelivery($delivery, $this->companyId($request));

        return DeliveryEventResource::collection(
            $found->events()->orderByDesc('event_at')->get(),
        );
    }

    private function findDelivery(int $deliveryId, string $companyId): Delivery
    {
        $delivery = Delivery::query()
            ->where('company_id', $companyId)
            ->whereKey($deliveryId)
            ->first();

        if ($delivery === null) {
            abort(404);
        }

        return $delivery;
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
