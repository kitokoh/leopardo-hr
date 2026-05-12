<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\VehicleAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $query = VehicleAlert::where('company_id', $user->company_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('acknowledged')) {
            $query->where('acknowledged', $request->boolean('acknowledged'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        $alerts = $query->orderByDesc('created_at')
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

    public function acknowledge(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $alert = VehicleAlert::where('company_id', $user->company_id)->findOrFail($id);

        $alert->update([
            'acknowledged' => true,
            'acknowledged_by' => $user->id,
        ]);

        return response()->json(['data' => $alert->fresh()]);
    }
}
