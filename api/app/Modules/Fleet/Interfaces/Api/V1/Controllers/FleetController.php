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

    /**
     * feat(fleet): API statistiques consolidées de flotte (#9542).
     *
     * Agrège les KPIs de la flotte sur une période :
     * - État de la flotte (actifs, maintenance, hors service)
     * - Kilométrage total et moyen
     * - Coûts de maintenance
     * - Alertes actives
     * - Taux d'utilisation
     *
     * RBAC : managers uniquement.
     */
    public function stats(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        abort_unless($user->isManager(), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $query = \App\Modules\Fleet\Domain\Models\Vehicle::query()
            ->where('company_id', $user->company_id);

        if (isset($validated['vehicle_id'])) {
            $query->where('id', $validated['vehicle_id']);
        }

        $vehicles = $query->get();
        $totalVehicles = $vehicles->count();
        $activeVehicles = $vehicles->where('status', 'active')->count();
        $inMaintenance = $vehicles->where('status', 'maintenance')->count();
        $outOfService = $vehicles->where('status', 'out_of_service')->count();

        // Kilométrage sur la période
        $tripsQuery = \App\Modules\Fleet\Domain\Models\VehicleTrip::query()
            ->where('company_id', $user->company_id)
            ->whereBetween('start_time', [$from.' 00:00:00', $to.' 23:59:59']);

        if (isset($validated['vehicle_id'])) {
            $tripsQuery->where('vehicle_id', $validated['vehicle_id']);
        }

        $trips = $tripsQuery->get();
        $totalKm = $trips->sum('distance_km');
        $avgKmPerVehicle = $totalVehicles > 0 ? round($totalKm / $totalVehicles, 1) : 0;

        // Coûts de maintenance sur la période
        $maintenanceQuery = \App\Modules\Fleet\Domain\Models\VehicleMaintenance::query()
            ->where('company_id', $user->company_id)
            ->whereBetween('performed_at', [$from, $to]);

        if (isset($validated['vehicle_id'])) {
            $maintenanceQuery->where('vehicle_id', $validated['vehicle_id']);
        }

        $maintenances = $maintenanceQuery->get();
        $totalMaintenanceCost = $maintenances->sum('cost');
        $maintenanceCount = $maintenances->count();
        $avgCostPerVehicle = $totalVehicles > 0 ? round($totalMaintenanceCost / $totalVehicles, 2) : 0;
        $costPerKm = $totalKm > 0 ? round($totalMaintenanceCost / $totalKm, 2) : 0;

        // Alertes actives
        $alertsQuery = \App\Modules\Fleet\Domain\Models\VehicleAlert::query()
            ->where('company_id', $user->company_id)
            ->where('is_resolved', false);

        if (isset($validated['vehicle_id'])) {
            $alertsQuery->where('vehicle_id', $validated['vehicle_id']);
        }

        $activeAlerts = $alertsQuery->count();
        $alertsByType = $alertsQuery->get()->groupBy('type')->map->count()->toArray();

        // Top véhicules par utilisation
        $topVehicles = $trips->groupBy('vehicle_id')
            ->map(function ($group) use ($vehicles) {
                $vehicle = $vehicles->firstWhere('id', $group->first()->vehicle_id);

                return [
                    'vehicle_id' => $group->first()->vehicle_id,
                    'registration' => $vehicle->registration_number ?? 'Unknown',
                    'total_km' => round($group->sum('distance_km'), 1),
                    'trips_count' => $group->count(),
                ];
            })
            ->sortByDesc('total_km')
            ->take(10)
            ->values()
            ->toArray();

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from,
                    'to' => $to,
                ],
                'fleet_overview' => [
                    'total_vehicles' => $totalVehicles,
                    'active' => $activeVehicles,
                    'in_maintenance' => $inMaintenance,
                    'out_of_service' => $outOfService,
                ],
                'mileage' => [
                    'total_km' => round($totalKm, 1),
                    'avg_per_vehicle' => $avgKmPerVehicle,
                    'total_trips' => $trips->count(),
                ],
                'maintenance' => [
                    'total_cost' => round($totalMaintenanceCost, 2),
                    'count' => $maintenanceCount,
                    'avg_cost_per_vehicle' => $avgCostPerVehicle,
                    'cost_per_km' => $costPerKm,
                ],
                'alerts' => [
                    'active_count' => $activeAlerts,
                    'by_type' => $alertsByType,
                ],
                'top_vehicles' => $topVehicles,
            ],
        ]);
    }
}
