<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\Tracking\TraccarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $query = Vehicle::where('company_id', $user->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $vehicles = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $vehicles->items(),
            'meta' => [
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
                'per_page' => $vehicles->perPage(),
                'total' => $vehicles->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|max:20',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'type' => 'nullable|in:car,van,truck,motorcycle,bus',
            'vin' => 'nullable|string|max:17',
            'fuel_type' => 'nullable|in:diesel,gasoline,electric,hybrid,lpg',
            'status' => 'nullable|in:active,maintenance,decommissioned',
            'mileage' => 'nullable|integer|min:0',
            'insurance_expiry' => 'nullable|date',
            'technical_control_expiry' => 'nullable|date',
            'traccar_unique_id' => 'nullable|string|max:50',
            'assigned_driver_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        /** @var Employee $user */
        $user = $request->user();
        $validated['company_id'] = $user->company_id;

        $vehicle = Vehicle::create($validated);

        return response()->json(['data' => $vehicle], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        return response()->json(['data' => $vehicle]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'plate_number' => 'sometimes|string|max:20',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'type' => 'nullable|in:car,van,truck,motorcycle,bus',
            'vin' => 'nullable|string|max:17',
            'fuel_type' => 'nullable|in:diesel,gasoline,electric,hybrid,lpg',
            'status' => 'nullable|in:active,maintenance,decommissioned',
            'mileage' => 'nullable|integer|min:0',
            'insurance_expiry' => 'nullable|date',
            'technical_control_expiry' => 'nullable|date',
            'traccar_unique_id' => 'nullable|string|max:50',
            'assigned_driver_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        $vehicle->update($validated);

        return response()->json(['data' => $vehicle->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);
        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted.']);
    }

    public function position(Request $request, int $id, TraccarService $traccar): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        if (! $vehicle->traccar_device_id) {
            return response()->json(['message' => 'No tracker linked to this vehicle.'], 404);
        }

        $position = $traccar->getLastPosition($vehicle->traccar_device_id);

        return response()->json(['data' => $position]);
    }

    public function trips(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $trips = $vehicle->trips()
            ->orderByDesc('start_time')
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

    public function vehicleAlerts(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $alerts = $vehicle->alerts()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $alerts->items(),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    public function maintenance(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $records = $vehicle->maintenances()
            ->orderByDesc('service_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'start_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        $vehicle->assignments()->create([
            'employee_id' => $validated['employee_id'],
            'company_id' => $user->company_id,
            'start_date' => $validated['start_date'],
            'reason' => $validated['reason'] ?? null,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        $vehicle->update(['assigned_driver_id' => $validated['employee_id']]);

        return response()->json(['message' => 'Driver assigned.'], 201);
    }

    public function unassign(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $currentAssignment = $vehicle->assignments()
            ->whereNull('end_date')
            ->latest('start_date')
            ->first();

        if ($currentAssignment) {
            $currentAssignment->update(['end_date' => now()->toDateString()]);
        }

        $vehicle->update(['assigned_driver_id' => null]);

        return response()->json(['message' => 'Driver unassigned.']);
    }

    public function assignments(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $assignments = $vehicle->assignments()
            ->with('employee:id,first_name,last_name')
            ->orderByDesc('start_date')
            ->get();

        return response()->json(['data' => $assignments]);
    }
}
