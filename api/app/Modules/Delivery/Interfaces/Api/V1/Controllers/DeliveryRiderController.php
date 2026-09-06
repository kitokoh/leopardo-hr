<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Delivery\Application\Actions\UpdateDeliveryStopStatusAction;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Support\DeliveryRoleResolver;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliveryStopStatusRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryRouteResource;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryRouteStopResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mobile livreur (DELIVERY-203, issue #6287) — partie serveur.
 *
 * - `GET routes/today` : tournées du jour du livreur authentifié (scope par
 *   PROPRIÉTÉ : driver_id = employé connecté — jamais par rôle seul).
 * - `POST stops/{stop}/status` : changement de statut d'un arrêt — idempotent
 *   (rejeu du même statut → même arrêt, aucun doublon), POD obligatoire pour
 *   `delivered` (BC-20 par valeur), transitions via la machine à états
 *   (UpdateDeliveryStopStatusAction, couche Application #6898).
 *
 * Le client Flutter (offline replay, états UI) est livré séparément ; le
 * contrat d'API ci-dessus est le socle serveur.
 */
final class DeliveryRiderController
{
    public function __construct(
        private readonly DeliveryRoleResolver $roles,
        private readonly UpdateDeliveryStopStatusAction $updateStopStatus,
    ) {}

    public function today(Request $request): JsonResponse
    {
        $employee = $request->user();
        if (! $employee instanceof Employee) {
            abort(401, 'AUTH_EMPLOYEE_REQUIRED');
        }

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
        $employee = $request->user();
        if (! $employee instanceof Employee) {
            abort(401, 'AUTH_EMPLOYEE_REQUIRED');
        }

        $validated = $request->validated();

        $updated = $this->updateStopStatus->execute(
            stopId: $stop,
            companyId: $this->companyId($request),
            employee: $employee,
            status: (string) $validated['status'],
            proofDocumentId: $validated['proof_document_id'] ?? null,
        );

        return (new DeliveryRouteStopResource($updated))->response();
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
