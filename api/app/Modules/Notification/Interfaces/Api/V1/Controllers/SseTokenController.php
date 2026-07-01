<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SseTokenController — Issues short-lived SSE tokens.
 *
 * Rationale: the SSE stream requires authentication but we cannot pass
 * the main API token in the EventSource URL (it would appear in server logs).
 * We instead issue a single-use 60-second token stored in Redis/cache and
 * consumed by NotificationStreamController on first use.
 *
 * Flow:
 *   1. Frontend POSTs to /api/v1/notifications/sse-token (auth required)
 *   2. Gets back { token: "<uuid>", expires_in: 60 }
 *   3. Opens EventSource to /api/v1/notifications/stream?sse_token=<uuid>
 *   4. NotificationStreamController validates & consumes the token
 */
class SseTokenController extends Controller
{
    public const TTL_SECONDS = 60;

    /**
     * POST /api/v1/notifications/sse-token
     */
    public function issue(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $token = Str::uuid()->toString();
        $cacheKey = 'sse_token:'.$token;

        Cache::put($cacheKey, [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
        ], self::TTL_SECONDS);

        return new JsonResponse([
            'token' => $token,
            'expires_in' => self::TTL_SECONDS,
        ]);
    }
}
