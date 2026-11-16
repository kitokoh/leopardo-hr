<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Models\LibraryCheckout;
use App\Modules\Library\Domain\Models\LibraryReadingProgress;
use App\Modules\Library\Domain\Models\LibraryResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /library/stats — KPIs de la bibliothèque d'entreprise (#9548).
 *
 * Agrège : ressources par type et statut, emprunts actifs et en retard,
 * engagement (vues, utilisateurs uniques, complétions), top ressources.
 *
 * Permission : `library.view` (Gate), scope tenant `company_id`.
 */
class LibraryStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('library.view')) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;
        $companyId = (string) $actor->company_id;

        // Resources stats
        $resourcesQuery = LibraryResource::query()->where('company_id', $companyId);
        if ($from) {
            $resourcesQuery->where('created_at', '>=', $from);
        }
        if ($to) {
            $resourcesQuery->where('created_at', '<=', $to);
        }

        $total = $resourcesQuery->count();

        $byType = LibraryResource::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        $byStatus = LibraryResource::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Borrowing stats
        $checkoutsQuery = LibraryCheckout::query()
            ->whereHas('resource', fn ($q) => $q->where('company_id', $companyId));

        if ($from) {
            $checkoutsQuery->where('checked_out_at', '>=', $from);
        }
        if ($to) {
            $checkoutsQuery->where('checked_out_at', '<=', $to);
        }

        $activeCheckouts = (clone $checkoutsQuery)
            ->whereNull('returned_at')
            ->count();

        $overdueCheckouts = (clone $checkoutsQuery)
            ->whereNull('returned_at')
            ->where('due_date', '<', now())
            ->count();

        $returnedOnTime = (clone $checkoutsQuery)
            ->whereNotNull('returned_at')
            ->whereColumn('returned_at', '<=', 'due_date')
            ->count();

        $avgDuration = (clone $checkoutsQuery)
            ->whereNotNull('returned_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (returned_at - checked_out_at)) / 86400) as avg_days')
            ->value('avg_days');

        // Engagement stats
        $progressQuery = LibraryReadingProgress::query()
            ->whereHas('resource', fn ($q) => $q->where('company_id', $companyId));

        if ($from) {
            $progressQuery->where('started_at', '>=', $from);
        }
        if ($to) {
            $progressQuery->where('started_at', '<=', $to);
        }

        $totalViews = (clone $progressQuery)->count();
        $uniqueUsers = (clone $progressQuery)->distinct('employee_id')->count('employee_id');
        $completedReadings = (clone $progressQuery)->where('progress', '>=', 100)->count();
        $avgCompletion = (clone $progressQuery)->avg('progress');

        // Top resources (by views/checkouts)
        $topResources = LibraryResource::query()
            ->where('company_id', $companyId)
            ->withCount([
                'readingProgress as views_count' => function ($query) use ($from, $to) {
                    if ($from) {
                        $query->where('started_at', '>=', $from);
                    }
                    if ($to) {
                        $query->where('started_at', '<=', $to);
                    }
                },
            ])
            ->orderByDesc('views_count')
            ->limit(10)
            ->get(['id', 'title', 'type'])
            ->map(fn ($resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'type' => $resource->type,
                'views' => (int) $resource->views_count,
            ]);

        return new JsonResponse([
            'data' => [
                'period' => [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                ],
                'resources' => [
                    'total' => $total,
                    'by_type' => $byType,
                    'by_status' => $byStatus,
                ],
                'borrowing' => [
                    'active' => $activeCheckouts,
                    'overdue' => $overdueCheckouts,
                    'returned_on_time' => $returnedOnTime,
                    'avg_duration_days' => $avgDuration ? round((float) $avgDuration, 1) : null,
                ],
                'engagement' => [
                    'total_views' => $totalViews,
                    'unique_users' => $uniqueUsers,
                    'completed_readings' => $completedReadings,
                    'avg_completion_rate' => $avgCompletion ? round($avgCompletion / 100, 4) : null,
                ],
                'top_resources' => $topResources->toArray(),
            ],
        ]);
    }
}
