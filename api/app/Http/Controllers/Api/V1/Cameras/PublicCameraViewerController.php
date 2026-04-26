<?php

namespace App\Http\Controllers\Api\V1\Cameras;

use App\Http\Controllers\Controller;
use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraAccessToken;
use App\Services\Cameras\CameraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Viewer public pour un token tiers. Renvoie un payload minimal (pas de
 * RTSP URL, juste l'URL WebRTC et le token qui sera validé par MediaMTX).
 */
class PublicCameraViewerController extends Controller
{
    public function __construct(private readonly CameraService $cameras) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) $request->query('t', '');

        if ($token === '') {
            return new JsonResponse([
                'error' => 'INVALID_TOKEN',
                'message' => 'INVALID_TOKEN',
            ], 404);
        }

        /** @var CameraAccessToken|null $access */
        $access = CameraAccessToken::withoutGlobalScopes()
            ->where('token', $token)
            ->first();

        if ($access === null || ! $access->isValid()) {
            return new JsonResponse([
                'error' => 'INVALID_TOKEN',
                'message' => 'INVALID_TOKEN',
            ], 404);
        }

        /** @var Camera|null $camera */
        $camera = Camera::withoutGlobalScopes()
            ->where('id', $access->camera_id)
            ->whereNull('deleted_at')
            ->first();

        if ($camera === null || ! $camera->is_active) {
            return new JsonResponse([
                'error' => 'CAMERA_NOT_FOUND',
                'message' => 'CAMERA_NOT_FOUND',
            ], 404);
        }

        $streamBase = rtrim((string) config('cameras.stream_base_url', ''), '/');
        $path = $camera->stream_path_override ?: (string) $camera->id;
        $streamUrl = $streamBase.'/'.trim($path, '/').'/webrtc';

        return new JsonResponse([
            'data' => [
                'camera' => [
                    'id' => (int) $camera->id,
                    'name' => $camera->name,
                    'location' => $camera->location,
                ],
                'stream_url' => $streamUrl,
                'stream_token' => $token,
                'expires_at' => optional($access->expires_at)->toIso8601ZuluString(),
                'permissions' => $access->permissions,
                'label' => $access->label,
                'granted_to_name' => $access->granted_to_name,
            ],
        ]);
    }
}
