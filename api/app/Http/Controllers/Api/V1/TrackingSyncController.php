<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\VehicleTrip;
use App\Services\Tracking\TraccarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TrackingSyncController extends Controller
{
    public function syncDevices(Request $request, TraccarService $traccar): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $devices = $traccar->getDevices();

        $synced = 0;
        foreach ($devices as $device) {
            $uniqueId = $device['uniqueId'] ?? '';
            if ($uniqueId === '') {
                continue;
            }

            $vehicle = Vehicle::where('company_id', $user->company_id)
                ->where('traccar_unique_id', $uniqueId)
                ->first();

            if ($vehicle) {
                $vehicle->update(['traccar_device_id' => $device['id'] ?? null]);
                $synced++;
            }
        }

        return response()->json([
            'message' => "Synced {$synced} devices.",
            'traccar_devices' => count($devices),
            'linked' => $synced,
        ]);
    }

    public function syncPositions(Request $request, TraccarService $traccar): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $vehicles = Vehicle::where('company_id', $user->company_id)
            ->whereNotNull('traccar_device_id')
            ->get();

        $updated = 0;
        foreach ($vehicles as $vehicle) {
            $pos = $traccar->getLastPosition($vehicle->traccar_device_id);
            if ($pos) {
                $updated++;
            }
        }

        return response()->json([
            'message' => "Updated positions for {$updated} vehicles.",
            'total_tracked' => $vehicles->count(),
            'updated' => $updated,
        ]);
    }

    public function syncTrips(Request $request, TraccarService $traccar): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $from = Carbon::parse($request->input('from', now()->startOfDay()));
        $to = Carbon::parse($request->input('to', now()));

        $vehicles = Vehicle::where('company_id', $user->company_id)
            ->whereNotNull('traccar_device_id')
            ->get();

        $totalTrips = 0;
        foreach ($vehicles as $vehicle) {
            $trips = $traccar->getTrips($vehicle->traccar_device_id, $from, $to);

            foreach ($trips as $trip) {
                $traccarTripId = $trip['id'] ?? null;
                if ($traccarTripId && VehicleTrip::where('traccar_trip_id', $traccarTripId)->exists()) {
                    continue;
                }

                VehicleTrip::create([
                    'vehicle_id' => $vehicle->id,
                    'company_id' => $user->company_id,
                    'driver_id' => $vehicle->assigned_driver_id,
                    'start_time' => $trip['startTime'] ?? now(),
                    'end_time' => $trip['endTime'] ?? null,
                    'start_lat' => $trip['startLat'] ?? null,
                    'start_lng' => $trip['startLon'] ?? null,
                    'start_address' => $trip['startAddress'] ?? null,
                    'end_lat' => $trip['endLat'] ?? null,
                    'end_lng' => $trip['endLon'] ?? null,
                    'end_address' => $trip['endAddress'] ?? null,
                    'distance_km' => ($trip['distance'] ?? 0) / 1000,
                    'duration_minutes' => ($trip['duration'] ?? 0) / 60000,
                    'max_speed_kmh' => ($trip['maxSpeed'] ?? 0) * 1.852,
                    'avg_speed_kmh' => ($trip['averageSpeed'] ?? 0) * 1.852,
                    'traccar_trip_id' => $traccarTripId,
                    'created_at' => now(),
                ]);
                $totalTrips++;
            }
        }

        return response()->json([
            'message' => "Synced {$totalTrips} new trips.",
            'vehicles_checked' => $vehicles->count(),
            'new_trips' => $totalTrips,
        ]);
    }
}
