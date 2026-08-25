<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cameras\Infrastructure\Services\CameraService;
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

        $tokenInput = $request->query('token');
        $cameraIdInput = $request->query('camera_id');
        $clientIpInput = $request->query('client_ip');

        $token = is_string($tokenInput) ? trim($tokenInput) : '';
        $cameraId = is_string($cameraIdInput) && ctype_digit($cameraIdInput)
            ? (int) $cameraIdInput
            : 0;
        $clientIp = is_string($clientIpInput) ? trim($clientIpInput) : null;

        if (
            $token === '' || strlen($token) > 2048 || $cameraId <= 0 ||
            ($clientIp !== null && filter_var($clientIp, FILTER_VALIDATE_IP) === false)
        ) {
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
