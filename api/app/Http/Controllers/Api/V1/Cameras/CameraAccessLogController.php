<?php

namespace App\Http\Controllers\Api\V1\Cameras;

use App\Http\Controllers\Controller;
use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraAccessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Historique des accès caméra (30 jours glissants).
 * Section 6.1 — GET /api/v1/cameras/{id}/access-logs.
 */
class CameraAccessLogController extends Controller
{
    public function index(int $cameraId, Request $request): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('viewLogs', $camera);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $since = Carbon::now('UTC')->subDays(30);

        $paginator = CameraAccessLog::query()
            ->where('camera_id', $camera->id)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => collect($paginator->items())->map(fn (CameraAccessLog $log) => [
                'id' => (int) $log->id,
                'camera_id' => (int) $log->camera_id,
                'employee_id' => $log->employee_id ? (int) $log->employee_id : null,
                'access_token_id' => $log->access_token_id ? (int) $log->access_token_id : null,
                'actor_type' => $log->actor_type,
                'action' => $log->action,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'metadata' => $log->metadata,
                'created_at' => optional($log->created_at)->toIso8601ZuluString(),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'window_days' => 30,
            ],
        ]);
    }
}
