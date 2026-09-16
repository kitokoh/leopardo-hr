<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Cameras\Domain\Models\CameraAlert;
use App\Modules\Cameras\Infrastructure\Services\CameraAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alertes caméra (issue #7427, BC-19).
 *
 * Lecture et cycle de vie des alertes manager, bornés au tenant (scope global
 * `company_id` → 404 cross-tenant) et arbitrés par `CameraAlertPolicy`
 * (manager uniquement). Le module est déjà gardé par `module.cameras`
 * (`companies.features.cameras`).
 */
class CameraAlertController extends Controller
{
    public function __construct(
        private readonly CameraAlertService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', CameraAlert::class);

        $query = CameraAlert::query()
            ->with('camera:id,name')
            ->where('company_id', $actor->company_id);

        $status = $request->input('status');

        if (is_string($status) && in_array($status, CameraAlert::STATUSES, true)) {
            $query->where('status', $status);
        }

        $severity = $request->input('severity');

        if (is_string($severity) && in_array($severity, CameraAlert::SEVERITIES, true)) {
            $query->where('severity', $severity);
        }

        if ($request->filled('camera_id')) {
            $query->where('camera_id', $request->integer('camera_id'));
        }

        $alerts = $query->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($alerts->items())->map(fn (CameraAlert $alert): array => $this->payload($alert)),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    public function acknowledge(Request $request, CameraAlert $alert): JsonResponse
    {
        return $this->transition($request, $alert, CameraAlert::STATUS_ACKNOWLEDGED);
    }

    public function resolve(Request $request, CameraAlert $alert): JsonResponse
    {
        return $this->transition($request, $alert, CameraAlert::STATUS_RESOLVED);
    }

    private function transition(Request $request, CameraAlert $alert, string $target): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($alert->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize($target === CameraAlert::STATUS_RESOLVED ? 'resolve' : 'acknowledge', $alert);

        $updated = $target === CameraAlert::STATUS_RESOLVED
            ? $this->service->resolve($alert, $actor)
            : $this->service->acknowledge($alert, $actor);

        return response()->json(['data' => $this->payload($updated)]);
    }

    /** @return array<string, mixed> */
    private function payload(CameraAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'camera_id' => $alert->camera_id,
            'camera_name' => $alert->camera?->name,
            'camera_event_id' => $alert->camera_event_id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'payload' => $alert->payload,
            'acknowledged_by' => $alert->acknowledged_by,
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'resolved_by' => $alert->resolved_by,
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'created_at' => $alert->created_at?->toIso8601String(),
        ];
    }
}
