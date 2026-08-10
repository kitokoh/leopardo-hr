<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Cameras\Application\Actions\CreateCamera;
use App\Modules\Cameras\Application\Actions\DeleteCamera;
use App\Modules\Cameras\Application\Actions\UpdateCamera;
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
    public function __construct(
        private readonly CameraService $cameras,
        private readonly CreateCamera $createCamera,
        private readonly UpdateCamera $updateCamera,
        private readonly DeleteCamera $deleteCamera,
    ) {}

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

        $cameras = $query->paginate($request->integer('per_page', 50));

        /** @var Company $company */
        $company = currentCompany();
        $max = $this->cameras->maxCameras($company);

        // Pagination (#1703) : `data` reste une liste simple (contrat
        // historique), les métadonnées de page sont exposées dans `meta`.
        return new JsonResponse([
            'data' => $cameras->through(fn (Camera $cam) => $this->cameras->buildStreamPayload($cam, $actor))->items(),
            'meta' => [
                'current_page' => $cameras->currentPage(),
                'per_page' => $cameras->perPage(),
                'total' => $cameras->total(),
                'last_page' => $cameras->lastPage(),
            ],
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

        $camera = $this->createCamera->execute($company, $actor, $request->validated());

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

        $camera = $this->updateCamera->execute($camera, $actor, $request->validated());

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

        $this->deleteCamera->execute($camera, $actor);

        return new JsonResponse([
            'data' => ['id' => (int) $camera->id, 'is_active' => false],
        ]);
    }

    public function testRtsp(TestRtspRequest $request): JsonResponse
    {
        $this->authorize('testRtsp', Camera::class);

        $result = $this->cameras->testRtsp($request->string('rtsp_url')->toString());

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
