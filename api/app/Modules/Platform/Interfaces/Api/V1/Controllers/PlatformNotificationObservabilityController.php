<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\PlatformNotificationObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * PA2-ADM-005 — Monitoring plateforme lisible.
 *
 * GET /api/v1/platform/observability/notifications : cross-tenant outbound
 * notification failure rate (last 24h), grouped by channel, plus the most
 * recent failures and a curated list of operational runbooks — everything
 * the super-admin "System" screen needs to answer "are notifications
 * healthy, and what's the playbook if not?" alongside the existing
 * `observability/queues` endpoint (PA2-QA-006).
 */
class PlatformNotificationObservabilityController extends Controller
{
    public function __invoke(PlatformNotificationObservabilityService $service): JsonResponse
    {
        // `companies` lives in the `public` schema; the service switches
        // search_path per-tenant internally while scanning communication
        // events, then restores it — start from a known baseline.
        DB::statement('SET search_path TO public');

        return response()->json(['data' => $service->snapshot()]);
    }
}
