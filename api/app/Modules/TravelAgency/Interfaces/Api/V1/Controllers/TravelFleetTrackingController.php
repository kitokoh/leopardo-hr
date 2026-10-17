<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\BuildTravelFleetTrackingAction;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #8548 (TRAVEL-OS) — tableau de bord de suivi de la flotte.
 *
 * Lecture seule. Réutilise la donnée de position déjà livrée par #8540 et la
 * Policy `TravelVehiclePolicy` (isolation par entreprise + rôles) : aucun
 * nouveau système de localisation, aucun appel à un fournisseur externe.
 *
 * Honnêteté de la donnée : chaque véhicule expose sa `freshness` — une
 * position ancienne est signalée `stale`, une absence de donnée
 * `unavailable`. Aucune position n'est inventée ni estimée ici.
 */
class TravelFleetTrackingController extends Controller
{
    public function index(Request $request, BuildTravelFleetTrackingAction $action): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelVehicle::class)) {
            abort(403);
        }

        $vehicleId = $request->query('vehicle_id');
        $resolvedVehicleId = is_numeric($vehicleId) ? (int) $vehicleId : null;

        // Un véhicule ciblé hors du tenant courant est introuvable (404 sûr,
        // jamais 403 sur la ressource) — même convention que le CRUD.
        if ($resolvedVehicleId !== null) {
            $exists = TravelVehicle::query()->whereKey($resolvedVehicleId)->exists();

            if (! $exists) {
                abort(404);
            }
        }

        return new JsonResponse([
            'data' => $action->execute($resolvedVehicleId),
        ]);
    }
}
