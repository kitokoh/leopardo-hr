<?php

namespace App\Modules\Cameras\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Domain\CameraPermission;
use App\Modules\Cameras\Infrastructure\Services\CameraService;
use App\Modules\Cameras\Interfaces\Api\V1\Requests\StoreCameraRequest;
use App\Modules\Cameras\Interfaces\Api\V1\Requests\TestRtspRequest;
use App\Modules\Cameras\Interfaces\Api\V1\Requests\UpdateCameraRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints REST sur la ressource caméra (section 6.1 du cahier des charges).
 */
class CameraController extends Controller
{
    public function __construct(private readonly CameraService $cameras) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Camera::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $query = Camera::query()->orderBy('sort_order')->orderBy('id');

        // Dept/Superviseur : restreindre via camera_permissions (spec section 5).
        if (! $actor->hasManagerRole('principal', 'rh')) {
            $cameraIds = CameraPermission::query()
                ->where('employee_id', $actor->id)
                ->where('can_view', true)
                ->where(function ($q): void {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->pluck('camera_id');

            $query->whereIn('id', $cameraIds);
        }

        $cameras = $query->get();

        /** @var Company $company */
        $company = currentCompany();
        $max = $this->cameras->maxCameras($company);

        return new JsonResponse([
            'data' => $cameras->map(fn (Camera $cam) => $this->cameras->buildStreamPayload($cam, $actor)),
            'plan_limit' => [
                'max_cameras' => $max,
                'current_count' => $this->cameras->countActive($company),
            ],
        ]);
    }

    public function store(StoreCameraRequest $request): JsonResponse
    {
        $this->authorize('create', Camera::class);

        /** @var Employee $actor */
        $actor = $request->user();
        /** @var Company $company */
        $company = currentCompany();

        $camera = $this->cameras->create($company, $actor, $request->validated());

        return new JsonResponse([
            'data' => $this->cameras->buildStreamPayload($camera, $actor),
        ], 201);
    }

    public function show(int $cameraId, Request $request): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('view', $camera);

        /** @var Employee $actor */
        $actor = $request->user();

        return new JsonResponse([
            'data' => $this->cameras->buildStreamPayload($camera, $actor),
        ]);
    }

    public function update(UpdateCameraRequest $request, int $cameraId): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('update', $camera);

        /** @var Employee $actor */
        $actor = $request->user();

        $camera = $this->cameras->update($camera, $actor, $request->validated());

        return new JsonResponse([
            'data' => $this->cameras->buildStreamPayload($camera, $actor),
        ]);
    }

    public function destroy(int $cameraId, Request $request): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('delete', $camera);

        /** @var Employee $actor */
        $actor = $request->user();

        $this->cameras->softDelete($camera, $actor);

        return new JsonResponse([
            'data' => ['id' => (int) $camera->id, 'is_active' => false],
        ]);
    }

    public function testRtsp(TestRtspRequest $request): JsonResponse
    {
        $this->authorize('testRtsp', Camera::class);

        $result = $this->cameras->testRtsp($request->string('rtsp_url'));

        if (! $result['ok']) {
            $status = match ($result['error']) {
                'timeout' => 408,
                'ffprobe_unavailable' => 503,
                default => 422,
            };

            return new JsonResponse([
                'error' => match ($result['error']) {
                    'timeout' => 'RTSP_TIMEOUT',
                    'ffprobe_unavailable' => 'VIDEO_PROXY_UNAVAILABLE',
                    'invalid_url' => 'VALIDATION_ERROR',
                    default => 'RTSP_CONNECTION_FAILED',
                },
                'message' => match ($result['error']) {
                    'timeout' => 'Connection to camera timed out. Verify the URL and network.',
                    'ffprobe_unavailable' => 'Video proxy unavailable. Please try again.',
                    'invalid_url' => 'The rtsp_url must be a valid RTSP URL starting with rtsp://',
                    default => 'Unable to connect to the camera.',
                },
            ], $status);
        }

        return new JsonResponse([
            'data' => [
                'ok' => true,
                'duration_ms' => $result['duration_ms'] ?? null,
                'skipped' => $result['skipped'] ?? false,
            ],
        ]);
    }

    public function streamToken(int $cameraId, Request $request): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('issueStreamToken', $camera);

        /** @var Employee $actor */
        $actor = $request->user();

        return new JsonResponse([
            'data' => $this->cameras->buildStreamPayload($camera, $actor),
        ]);
    }
}
