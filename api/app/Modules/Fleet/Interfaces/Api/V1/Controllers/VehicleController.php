<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleAlertResource;
use App\Http\Resources\Api\V1\VehicleAssignmentResource;
use App\Http\Resources\Api\V1\VehicleMaintenanceResource;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Http\Resources\Api\V1\VehicleTripResource;
use App\Modules\Attendance\Infrastructure\Services\TraccarService;
use App\Modules\Fleet\Application\Actions\AssignDriverToVehicleAction;
use App\Modules\Fleet\Application\Actions\DeleteVehicleAction;
use App\Modules\Fleet\Application\Actions\RegisterVehicleAction;
use App\Modules\Fleet\Application\Actions\UnassignDriverFromVehicleAction;
use App\Modules\Fleet\Application\Actions\UpdateVehicleAction;
use App\Modules\Fleet\Domain\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['active', 'maintenance', 'decommissioned'])],
            'type' => ['sometimes', 'string', Rule::in(['car', 'van', 'truck', 'motorcycle', 'bus'])],
            'per_page' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        /** @var Employee $user */
        $user = $request->user();
        $query = Vehicle::where('company_id', $user->company_id);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $vehicles = $query->orderByDesc('created_at')
            ->paginate(max(1, min(100, (int) ($filters['per_page'] ?? 20))));

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

        $vehicle = app(RegisterVehicleAction::class)->execute((string) $user->company_id, $validated);

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

        $vehicle = app(UpdateVehicleAction::class)->execute($vehicle, $validated);

        return (new VehicleResource($vehicle))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        app(DeleteVehicleAction::class)->execute($vehicle);

        // #4812 : littéral EN déplacé au catalogue errors.*
        return response()->json(['message' => __('errors.VEHICLE_DELETED')]);
    }

    public function position(Request $request, int $id, TraccarService $traccar): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        // Sécurité #2217 : un employé ne peut consulter la position LIVE que de
        // SON véhicule assigné (assigned_driver_id) — pas du reste de la flotte.
        if (! $user->isManager() && (int) $vehicle->assigned_driver_id !== (int) $user->id) {
            abort(403, 'FORBIDDEN');
        }

        if (! $vehicle->traccar_device_id) {
            return response()->json(['message' => 'No tracker linked to this vehicle.'], 404);
        }

        $position = $traccar->getLastPosition($vehicle->traccar_device_id);

        return response()->json(['data' => $position]);
    }

    /**
     * Sécurité #2217 — véhicules assignés à l'employé connecté (app mobile
     * employé). Consomme le même format que `position()`.
     */
    public function myVehicles(Request $request, TraccarService $traccar): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $vehicles = Vehicle::where('company_id', $user->company_id)
            ->where('assigned_driver_id', $user->id)
            ->where('status', 'active')
            ->select(['id', 'plate_number', 'brand', 'model', 'type', 'traccar_device_id', 'assigned_driver_id'])
            ->get();

        $result = [];
        foreach ($vehicles as $vehicle) {
            $position = $vehicle->traccar_device_id !== null
                ? $traccar->getLastPosition((int) $vehicle->traccar_device_id)
                : null;

            // Shape aplati aligné sur le modèle mobile `VehiclePosition`
            // (leopardo_employee / leopardo_hr).
            $result[] = [
                'vehicle_id' => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'type' => $vehicle->type,
                'latitude' => isset($position['latitude']) ? (float) $position['latitude'] : null,
                'longitude' => isset($position['longitude']) ? (float) $position['longitude'] : null,
                'speed' => isset($position['speed']) ? (float) $position['speed'] : null,
                'updated_at' => $position['fixTime'] ?? null,
            ];
        }

        return response()->json(['data' => $result]);
    }

    public function trips(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $trips = $vehicle->trips()
            ->orderByDesc('start_time')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return VehicleTripResource::collection($trips)->response();
    }

    public function vehicleAlerts(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $alerts = $vehicle->alerts()
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return VehicleAlertResource::collection($alerts)->response();
    }

    public function maintenance(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $records = $vehicle->maintenances()
            ->orderByDesc('service_date')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return VehicleMaintenanceResource::collection($records)->response();
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            // #4788 : l'employé doit appartenir à la société du véhicule (pattern EmployeeLoanController).
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('company_id', $user->company_id)],
            'start_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        app(AssignDriverToVehicleAction::class)->execute(
            $vehicle,
            (string) $user->company_id,
            (int) $validated['employee_id'],
            (string) $validated['start_date'],
            isset($validated['reason']) ? (string) $validated['reason'] : null,
            (int) $user->id,
        );

        return response()->json(['message' => 'Driver assigned.'], 201);
    }

    public function unassign(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $vehicle = Vehicle::where('company_id', $user->company_id)->findOrFail($id);

        app(UnassignDriverFromVehicleAction::class)->execute($vehicle);

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
