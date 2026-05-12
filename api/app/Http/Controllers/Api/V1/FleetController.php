<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleAlert;
use App\Models\VehicleMaintenance;
use App\Models\VehicleTrip;
use App\Services\Tracking\TraccarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;

        $totalVehicles = Vehicle::where('company_id', $companyId)->count();
        $active = Vehicle::where('company_id', $companyId)->where('status', 'active')->count();
        $inMaintenance = Vehicle::where('company_id', $companyId)->where('status', 'maintenance')->count();
        $decommissioned = Vehicle::where('company_id', $companyId)->where('status', 'decommissioned')->count();
        $unacknowledgedAlerts = VehicleAlert::where('company_id', $companyId)->where('acknowledged', false)->count();

        return response()->json([
            'data' => [
                'total_vehicles' => $totalVehicles,
                'active' => $active,
                'in_maintenance' => $inMaintenance,
                'decommissioned' => $decommissioned,
                'unacknowledged_alerts' => $unacknowledgedAlerts,
            ],
        ]);
    }

    public function liveMap(Request $request, TraccarService $traccar): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $vehicles = Vehicle::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->whereNotNull('traccar_device_id')
            ->select(['id', 'plate_number', 'brand', 'model', 'type', 'traccar_device_id', 'assigned_driver_id'])
            ->get();

        $positions = [];
        foreach ($vehicles as $vehicle) {
            $pos = $traccar->getLastPosition($vehicle->traccar_device_id);
            $positions[] = [
                'vehicle_id' => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'type' => $vehicle->type,
                'position' => $pos,
            ];
        }

        return response()->json(['data' => $positions]);
    }

    public function fuelReport(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $trips = VehicleTrip::where('company_id', $user->company_id)
            ->whereBetween('start_time', [$from, $to])
            ->whereNotNull('fuel_consumed')
            ->selectRaw('vehicle_id, SUM(fuel_consumed) as total_fuel, SUM(distance_km) as total_distance')
            ->groupBy('vehicle_id')
            ->get();

        return response()->json(['data' => $trips]);
    }

    public function mileageReport(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $trips = VehicleTrip::where('company_id', $user->company_id)
            ->whereBetween('start_time', [$from, $to])
            ->selectRaw('vehicle_id, SUM(distance_km) as total_km, COUNT(*) as trip_count, AVG(avg_speed_kmh) as avg_speed')
            ->groupBy('vehicle_id')
            ->get();

        return response()->json(['data' => $trips]);
    }

    public function maintenanceDue(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $upcoming = VehicleMaintenance::where('company_id', $user->company_id)
            ->whereNotNull('next_service_date')
            ->where('next_service_date', '<=', now()->addDays(30)->toDateString())
            ->with('vehicle:id,plate_number,brand,model')
            ->orderBy('next_service_date')
            ->get();

        return response()->json(['data' => $upcoming]);
    }
}
