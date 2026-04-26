<?php

namespace App\Http\Controllers\Api\V1\Cameras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cameras\StoreCameraAccessTokenRequest;
use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraAccessToken;
use App\Models\Employee;
use App\Services\Cameras\CameraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tokens d'accès tiers (liens publics) — section 6.1 du cahier des charges.
 */
class CameraAccessTokenController extends Controller
{
    public function __construct(private readonly CameraService $cameras) {}

    public function index(int $cameraId, Request $request): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('view', $camera);

        $tokens = $camera->accessTokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CameraAccessToken $t) => $this->present($t, includeToken: false));

        return new JsonResponse([
            'data' => $tokens,
        ]);
    }

    public function store(StoreCameraAccessTokenRequest $request, int $cameraId): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);

        $this->authorize('shareAccess', $camera);

        /** @var Employee $actor */
        $actor = $request->user();

        $token = $this->cameras->issueAccessToken($camera, $actor, $request->validated());

        return new JsonResponse([
            'data' => $this->present($token, includeToken: true),
        ], 201);
    }

    public function destroy(int $cameraId, int $tokenId, Request $request): JsonResponse
    {
        $camera = Camera::query()->findOrFail($cameraId);
        /** @var CameraAccessToken $token */
        $token = $camera->accessTokens()->findOrFail($tokenId);

        $this->authorize('revokeAccess', $token);

        /** @var Employee $actor */
        $actor = $request->user();

        $token = $this->cameras->revokeAccessToken($token, $actor);

        return new JsonResponse([
            'data' => $this->present($token, includeToken: false),
        ]);
    }

    private function present(CameraAccessToken $token, bool $includeToken): array
    {
        $publicUrl = config('cameras.public_view_url');
        $shareUrl = is_string($publicUrl) && $publicUrl !== '' && $includeToken
            ? rtrim($publicUrl, '/').'?t='.$token->token
            : null;

        return array_filter([
            'id' => (int) $token->id,
            'camera_id' => (int) $token->camera_id,
            'label' => $token->label,
            'granted_to_email' => $token->granted_to_email,
            'granted_to_name' => $token->granted_to_name,
            'granted_by' => (int) $token->granted_by,
            'permissions' => $token->permissions,
            'expires_at' => optional($token->expires_at)->toIso8601ZuluString(),
            'last_used_at' => optional($token->last_used_at)?->toIso8601ZuluString(),
            'use_count' => (int) $token->use_count,
            'is_revoked' => (bool) $token->is_revoked,
            'created_at' => optional($token->created_at)->toIso8601ZuluString(),
            'token' => $includeToken ? $token->token : null,
            'share_url' => $shareUrl,
        ], fn ($v) => $v !== null);
    }
}
