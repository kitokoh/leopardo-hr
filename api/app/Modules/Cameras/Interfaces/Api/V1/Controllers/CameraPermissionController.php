<?php

namespace App\Http\Controllers\Api\V1\Cameras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cameras\StoreCameraPermissionRequest;
use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraPermission;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Gestion des permissions internes sur une caméra — section 4.3 du cahier.
 * Seul un Manager Principal peut les gérer (spec RBAC section 5).
 */
class CameraPermissionController extends Controller
{
    public function index(int $cameraId, Request $request): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('managePermissions', Camera::class);

        $permissions = $camera->permissions()
            ->orderBy('id')
            ->get()
            ->map(fn (CameraPermission $p) => $this->present($p));

        return new JsonResponse(['data' => $permissions]);
    }

    public function store(StoreCameraPermissionRequest $request, int $cameraId): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('managePermissions', Camera::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $data = $request->validated();

        /** @var Employee $target */
        $target = Employee::query()->findOrFail($data['employee_id']);

        $permission = CameraPermission::query()->updateOrCreate(
            [
                'camera_id' => $camera->id,
                'employee_id' => $target->id,
            ],
            [
                'company_id' => $camera->company_id,
                'can_view' => (bool) ($data['can_view'] ?? true),
                'can_share' => (bool) ($data['can_share'] ?? false),
                'can_manage' => (bool) ($data['can_manage'] ?? false),
                'granted_by' => $actor->id,
                'granted_at' => Carbon::now('UTC'),
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        return new JsonResponse(['data' => $this->present($permission->fresh())], 201);
    }

    public function destroy(int $cameraId, int $permissionId): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('managePermissions', Camera::class);

        $permission = $camera->permissions()->findOrFail($permissionId);
        $permission->delete();

        return new JsonResponse(['data' => ['id' => (int) $permissionId, 'deleted' => true]]);
    }

    private function present(CameraPermission $p): array
    {
        return [
            'id' => (int) $p->id,
            'camera_id' => (int) $p->camera_id,
            'employee_id' => (int) $p->employee_id,
            'can_view' => (bool) $p->can_view,
            'can_share' => (bool) $p->can_share,
            'can_manage' => (bool) $p->can_manage,
            'granted_by' => (int) $p->granted_by,
            'granted_at' => optional($p->granted_at)->toIso8601ZuluString(),
            'expires_at' => optional($p->expires_at)?->toIso8601ZuluString(),
        ];
    }
}
