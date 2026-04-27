<?php

namespace App\Http\Controllers\Api\V1\Cameras;

use App\Http\Controllers\Controller;
use App\Services\Cameras\CameraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Endpoint interne consommé par MediaMTX à chaque ouverture de flux WebRTC.
 *
 * Section 6.4 du cahier des charges :
 *   GET /internal/camera-token/verify?token=...&camera_id=...&client_ip=...
 *   Header : Authorization: Bearer {CAMERAS_MEDIAMTX_SECRET}
 *   Réponse 200 toujours, body indique allowed: true|false + reason.
 */
class InternalCameraTokenController extends Controller
{
    public function __construct(private readonly CameraService $cameras) {}

    public function verify(Request $request): JsonResponse
    {
        if (! $this->isMediamtxAuthorized($request)) {
            return new JsonResponse(['allowed' => false, 'reason' => 'unauthorized'], 401);
        }

        $token = (string) $request->query('token', '');
        $cameraId = (int) $request->query('camera_id', 0);
        $clientIp = $request->query('client_ip');
        $clientIp = is_string($clientIp) ? $clientIp : null;

        if ($token === '' || $cameraId <= 0) {
            return new JsonResponse(['allowed' => false, 'reason' => 'invalid_request']);
        }

        $result = $this->cameras->verifyTokenForMediamtx($token, $cameraId, $clientIp);

        return new JsonResponse($result);
    }

    private function isMediamtxAuthorized(Request $request): bool
    {
        $expected = Config::get('cameras.mediamtx_secret');

        if (! is_string($expected) || $expected === '') {
            // En dev sans secret configuré, on autorise (app.env local/testing).
            return in_array(app()->environment(), ['local', 'testing'], true);
        }

        $header = (string) $request->header('Authorization', '');

        if (stripos($header, 'Bearer ') === 0) {
            $provided = substr($header, 7);

            return hash_equals($expected, $provided);
        }

        return false;
    }
}
