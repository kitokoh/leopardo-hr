<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleMaintenanceResource;
use App\Models\Employee;
use App\Models\VehicleMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $query = VehicleMaintenance::where('company_id', $user->company_id);

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $records = $query->orderByDesc('service_date')
            ->paginate($request->integer('per_page', 20));

        return VehicleMaintenanceResource::collection($records)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
            'type' => 'required|in:oil_change,tire,brake,battery,inspection,repair,other',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'mileage_at_service' => 'nullable|integer|min:0',
            'service_date' => 'required|date',
            'next_service_date' => 'nullable|date',
            'next_service_mileage' => 'nullable|integer|min:0',
            'provider' => 'nullable|string|max:200',
        ]);

        /** @var Employee $user */
        $user = $request->user();
        $validated['company_id'] = $user->company_id;

        $record = VehicleMaintenance::create($validated);

        return (new VehicleMaintenanceResource($record))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $record = VehicleMaintenance::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|in:oil_change,tire,brake,battery,inspection,repair,other',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'mileage_at_service' => 'nullable|integer|min:0',
            'service_date' => 'sometimes|date',
            'next_service_date' => 'nullable|date',
            'next_service_mileage' => 'nullable|integer|min:0',
            'provider' => 'nullable|string|max:200',
        ]);

        $record->update($validated);

        return (new VehicleMaintenanceResource($record->fresh()))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $record = VehicleMaintenance::where('company_id', $user->company_id)->findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Maintenance record deleted.']);
    }
}
