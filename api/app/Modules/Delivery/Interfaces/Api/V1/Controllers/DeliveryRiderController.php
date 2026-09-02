<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Application\Services\DeliveryEventService;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use App\Modules\Delivery\Domain\Support\DeliveryRoleResolver;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliveryStopStatusRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryRouteResource;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryStopResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API mobile livreur (DELIVERY-203, issue #6287) — partie serveur.
 *
 * - `GET routes/today` : tournées du jour du livreur authentifié (scope par
 *   PROPRIÉTÉ : driver_id = employé connecté — jamais par rôle seul).
 * - `POST stops/{stop}/status` : changement de statut d'un arrêt — idempotent
 *   (rejeu du même statut → même arrêt, aucun doublon), POD obligatoire pour
 *   `delivered` (BC-20 par valeur), transitions via la machine à états.
 *
 * Le client Flutter (offline replay, états UI) est livré séparément ; le
 * contrat d'API ci-dessus est le socle serveur.
 */
final class DeliveryRiderController
{
    public function __construct(
        private readonly DeliveryEventService $events,
        private readonly DeliveryRoleResolver $roles,
    ) {}

    public function today(Request $request): JsonResponse
    {
        $employee = $request->user();
        $companyId = $this->companyId($request);
        $today = now()->toDateString();

        $query = DeliveryRoute::query()
            ->where('company_id', $companyId)
            ->where('route_date', $today);

        // Scope par propriété : un rider ne voit QUE ses tournées.
        if (! $this->roles->hasAnyRole($employee, ['dispatcher', 'manager', 'admin'])) {
            $query->where('driver_id', $employee->id);
        }

        $routes = $query->with('stops')->orderBy('id')->get();

        return response()->json([
            'data' => DeliveryRouteResource::collection($routes),
        ]);
    }

    public function status(DeliveryStopStatusRequest $request, int $stop): JsonResponse
    {
        $validated = $request->validated();
        $employee = $request->user();
        $companyId = $this->companyId($request);

        $updated = DB::transaction(function () use ($stop, $companyId, $validated, $employee): DeliveryStop {
            /** @var DeliveryStop|null $found */
            $found = DeliveryStop::query()
                ->where('company_id', $companyId)
                ->whereKey($stop)
                ->lockForUpdate()
                ->first();

            if ($found === null) {
                abort(404);
            }

            $route = $found->route()->where('company_id', $companyId)->first();

            if ($route === null || ! $this->canOperate($employee, $route)) {
                abort(403, 'ROUTE_NOT_ASSIGNED_TO_RIDER');
            }

            // Idempotence : même statut rejoué → même arrêt, aucun effet.
            if ($found->status === $validated['status']) {
                return $found;
            }

            $status = (string) $validated['status'];

            // Chaque statut métier déclenche l'événement de tracking correspondant
            // (idempotent + POD + machine à états — DeliveryEventService).
            $eventType = match ($status) {
                'en_route' => 'out_for_delivery',
                'arrived' => 'arrived',
                'delivered' => 'delivered',
                'failed' => 'failed',
                default => null, // skipped : pas d'événement de livraison
            };

            $proofId = $validated['proof_document_id'] ?? null;

            if ($eventType !== null) {
                $this->events->record(
                    companyId: $companyId,
                    deliveryId: (int) $found->delivery_id,
                    type: $eventType,
                    eventAt: now(),
                    latitude: null,
                    longitude: null,
                    origin: 'mobile',
                    idempotencyKey: null,
                    proofDocumentId: $eventType === 'delivered' ? $proofId : null,
                );
            }

            $now = now();

            $found->forceFill([
                'status' => $status,
                'arrived_at' => in_array($status, ['arrived', 'delivered'], true) ? $now : $found->arrived_at,
                'delivered_at' => $status === 'delivered' ? $now : null,
                'proof_id' => $status === 'delivered' ? $proofId : $found->proof_id,
            ])->save();

            return $found->fresh();
        });

        return (new DeliveryStopResource($updated))->response();
    }

    private function canOperate(Request $request, DeliveryRoute $route): bool
    {
        $employee = $request->user();

        if ($this->roles->hasAnyRole($employee, ['dispatcher', 'manager', 'admin'])) {
            return true;
        }

        return $route->driver_id !== null && (int) $route->driver_id === (int) $employee->id;
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
