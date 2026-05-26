<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleAlertResource;
use App\Models\Employee;
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

        return VehicleAlertResource::collection($alerts)->response();
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

        return (new VehicleAlertResource($alert->fresh()))->response();
    }
}
