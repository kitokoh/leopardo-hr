<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\VehicleTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleTripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $query = VehicleTrip::where('company_id', $user->company_id);

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }
        if ($request->filled('from')) {
            $query->where('start_time', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('start_time', '<=', $request->input('to'));
        }

        $trips = $query->orderByDesc('start_time')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $trips->items(),
            'meta' => [
                'current_page' => $trips->currentPage(),
                'last_page' => $trips->lastPage(),
                'per_page' => $trips->perPage(),
                'total' => $trips->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $trip = VehicleTrip::where('company_id', $user->company_id)->findOrFail($id);

        return response()->json(['data' => $trip]);
    }
}
