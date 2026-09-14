<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Fleet\Infrastructure\Services\FleetTrackingSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Synchronisations Traccar déclenchées **manuellement** depuis l'API.
 *
 * #7401 : ces trois endpoints ne portent plus la logique de synchronisation —
 * ils délèguent à `FleetTrackingSyncService`, exactement le même code que la
 * commande planifiée `leopardo:fleet:sync`. Avant, `syncPositions()` se
 * contentait de **compter** les véhicules ayant une dernière position connue
 * sans jamais rien écrire (le nom de l'endpoint était trompeur) : il persiste
 * désormais les positions dans `vehicle_positions`, comme son nom l'annonce.
 *
 * Contrat inchangé pour les clients : routes, middleware `api.manager`,
 * isolation tenant (toutes les requêtes sont filtrées sur
 * `company_id = demandeur`) et clés de réponse existantes (`message`,
 * `traccar_devices`, `linked`, `total_tracked`, `updated`, `vehicles_checked`,
 * `new_trips`) ; `positions_written` est **additif**.
 */
class TrackingSyncController extends Controller
{
    public function syncDevices(Request $request, FleetTrackingSyncService $sync): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $result = $sync->syncDevices((string) $user->company_id, $sync->devices());

        return response()->json([
            'message' => "Synced {$result['linked']} devices.",
            'traccar_devices' => $result['devices'],
            'linked' => $result['linked'],
        ]);
    }

    public function syncPositions(Request $request, FleetTrackingSyncService $sync): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $result = $sync->syncLatestPositions((string) $user->company_id);

        return response()->json([
            'message' => "Updated positions for {$result['vehicles_with_position']} vehicles.",
            'total_tracked' => $result['vehicles'],
            'updated' => $result['vehicles_with_position'],
            'positions_written' => $result['written'],
        ]);
    }

    public function syncTrips(Request $request, FleetTrackingSyncService $sync): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        // #3369 : plage de dates bornée (max 90 jours, pas de futur, from <= to)
        $from = Carbon::parse($request->input('from', now()->startOfDay()));
        $to = Carbon::parse($request->input('to', now()));

        if ($to->lt($from)) {
            return response()->json(['error' => 'The to date must be after the from date.'], 422);
        }

        if ($to->gt(now()->addHour())) {
            return response()->json(['error' => 'The to date cannot be in the future.'], 422);
        }

        if ($from->diffInDays($to) > FleetTrackingSyncService::MAX_WINDOW_DAYS) {
            return response()->json(['error' => 'The date range cannot exceed 90 days.'], 422);
        }

        $result = $sync->syncTrips((string) $user->company_id, $from, $to);

        return response()->json([
            'message' => "Synced {$result['written']} new trips.",
            'vehicles_checked' => $result['vehicles'],
            'new_trips' => $result['written'],
        ]);
    }
}
