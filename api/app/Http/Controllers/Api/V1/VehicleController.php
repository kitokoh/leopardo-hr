<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleAlertResource;
use App\Http\Resources\Api\V1\VehicleAssignmentResource;
use App\Http\Resources\Api\V1\VehicleMaintenanceResource;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Http\Resources\Api\V1\VehicleTripResource;
use App\Models\Employee;
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

        return VehicleResource::collection($vehicles)->response();
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

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        return (new VehicleResource($vehicle))->response();
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

        return (new VehicleResource($vehicle->fresh()))->response();
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

        return VehicleTripResource::collection($trips)->response();
    }

    public function vehicleAlerts(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $alerts = $vehicle->alerts()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return VehicleAlertResource::collection($alerts)->response();
    }

    public function maintenance(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $records = $vehicle->maintenances()
            ->orderByDesc('service_date')
            ->paginate($request->integer('per_page', 20));

        return VehicleMaintenanceResource::collection($records)->response();
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

        return VehicleAssignmentResource::collection($assignments)->response();
    }
}
