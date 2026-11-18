<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\QueueObservabilityService;
use App\Support\SearchPathGuard;
use Illuminate\Http\JsonResponse;

/**
 * PA2-QA-006 — Observabilite Redis/jobs.
 *
 * GET /api/v1/platform/observability/queues : queue depth, failed jobs
 * (count + recent) and last run/status of every scheduled Artisan command,
 * plus a derived `alerts` summary — everything the super-admin "System"
 * screen needs to answer "are the background jobs healthy right now?"
 * without SSH-ing into the box. Depends on PA2-JOB-001 (failed_jobs table,
 * queue:health-check command).
 */
class QueueObservabilityController extends Controller
{
    public function __invoke(QueueObservabilityService $service): JsonResponse
    {
        // scheduled_task_runs / failed_jobs both live in the `public` schema
        // (platform-wide, not tenant-scoped) — see PlatformCompanyHealthController
        // for the same pattern on the super-admin routes.
        // Issue #9115 : restauration garantie du search_path d'entree.
        return SearchPathGuard::run('public', static fn (): JsonResponse => response()->json(['data' => $service->snapshot()]));
    }
}
