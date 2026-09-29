<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Fleet\Application\Actions\AggregateFleetOverviewAction;
use App\Modules\Fleet\Application\Actions\BuildFleetLiveMapAction;
use App\Modules\Fleet\Application\Actions\ListVehiclesDueForMaintenanceAction;
use App\Modules\Fleet\Application\Actions\ReportVehicleFuelConsumptionAction;
use App\Modules\Fleet\Application\Actions\ReportVehicleMileageAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        return response()->json([
            'data' => app(AggregateFleetOverviewAction::class)->execute((string) $user->company_id),
        ]);
    }

    public function liveMap(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        return response()->json([
            'data' => app(BuildFleetLiveMapAction::class)->execute((string) $user->company_id),
        ]);
    }

    public function fuelReport(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        // Fenêtre par défaut : mois courant (contrat d'entrée inchangé).
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return response()->json([
            'data' => app(ReportVehicleFuelConsumptionAction::class)->execute(
                (string) $user->company_id,
                (string) $from,
                (string) $to,
            ),
        ]);
    }

    public function mileageReport(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        // Fenêtre par défaut : mois courant (contrat d'entrée inchangé).
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return response()->json([
            'data' => app(ReportVehicleMileageAction::class)->execute(
                (string) $user->company_id,
                (string) $from,
                (string) $to,
            ),
        ]);
    }

    public function maintenanceDue(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        return response()->json([
            'data' => app(ListVehiclesDueForMaintenanceAction::class)->execute((string) $user->company_id),
        ]);
    }
}
