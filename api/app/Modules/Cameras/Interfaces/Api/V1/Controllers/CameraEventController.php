<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Cameras\Domain\Models\CameraEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Journal des événements caméra (issue #7427, BC-19).
 *
 * Lecture seule : les événements sont écrits par la chaîne vidéo via
 * `POST /internal/camera-events`. Borné au tenant, manager uniquement.
 */
class CameraEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', CameraEvent::class);

        $query = CameraEvent::query()
            ->with('camera:id,name')
            ->where('company_id', $actor->company_id);

        $type = $request->input('type');

        if (is_string($type) && in_array($type, CameraEvent::TYPES, true)) {
            $query->where('type', $type);
        }

        $severity = $request->input('severity');

        if (is_string($severity) && in_array($severity, CameraEvent::SEVERITIES, true)) {
            $query->where('severity', $severity);
        }

        if ($request->filled('camera_id')) {
            $query->where('camera_id', $request->integer('camera_id'));
        }

        if ($request->filled('from')) {
            $query->where('detected_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('detected_at', '<=', $request->date('to'));
        }

        $events = $query->orderByDesc('detected_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($events->items())->map(fn (CameraEvent $event): array => [
                'id' => $event->id,
                'camera_id' => $event->camera_id,
                'camera_name' => $event->camera?->name,
                'type' => $event->type,
                'severity' => $event->severity,
                'detected_at' => $event->detected_at?->toIso8601String(),
                'has_snapshot' => $event->snapshot_path !== null,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}
