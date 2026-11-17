<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Models\LibraryPurchase;
use App\Modules\Library\Domain\Models\LibraryReadingProgress;
use App\Modules\Library\Domain\Models\LibraryResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /library/stats — KPIs de la bibliothèque d'entreprise (#9548).
 *
 * Agrège : ressources par type et statut, ventes/achats de la période,
 * engagement (lectures, lecteurs uniques, complétions), top ressources.
 *
 * ⚠️ Le schéma Library ne contient PAS de table d'emprunt (`library_checkouts`
 * n'existe pas) : le module vend des ressources (`library_purchases`) et suit
 * la progression de lecture (`library_reading_progress`). Les indicateurs
 * « emprunts » ont donc été remplacés par les indicateurs de vente réels.
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

        // --- Ressources -----------------------------------------------------
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
            ->select('document_type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('document_type')
            ->get()
            ->pluck('aggregate', 'document_type')
            ->toArray();

        $byStatus = LibraryResource::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->get()
            ->pluck('aggregate', 'status')
            ->toArray();

        // --- Ventes (library_purchases) -------------------------------------
        $purchasesQuery = LibraryPurchase::query()->where('company_id', $companyId);

        if ($from) {
            $purchasesQuery->where('created_at', '>=', $from);
        }
        if ($to) {
            $purchasesQuery->where('created_at', '<=', $to);
        }

        $purchaseCount = (clone $purchasesQuery)->count();
        $paidCount = (clone $purchasesQuery)->whereNotNull('paid_at')->count();
        $refundedCount = (clone $purchasesQuery)->whereNotNull('refunded_at')->count();
        $revenueMinor = (int) (clone $purchasesQuery)
            ->whereNotNull('paid_at')
            ->whereNull('refunded_at')
            ->sum('total_minor');

        // --- Engagement (library_reading_progress) --------------------------
        $progressQuery = LibraryReadingProgress::query()->where('company_id', $companyId);

        if ($from) {
            $progressQuery->where('updated_at', '>=', $from);
        }
        if ($to) {
            $progressQuery->where('updated_at', '<=', $to);
        }

        $totalReadings = (clone $progressQuery)->count();
        $uniqueReaders = (clone $progressQuery)->distinct()->count('employee_id');
        $completedReadings = (clone $progressQuery)
            ->whereNotNull('total_pages')
            ->whereColumn('page', '>=', 'total_pages')
            ->count();

        // --- Top ressources (par nombre de lectures) ------------------------
        $topResources = LibraryResource::query()
            ->where('company_id', $companyId)
            ->withCount([
                'readingProgress as readings_count' => function ($query) use ($from, $to) {
                    if ($from) {
                        $query->where('updated_at', '>=', $from);
                    }
                    if ($to) {
                        $query->where('updated_at', '<=', $to);
                    }
                },
            ])
            ->orderByDesc('readings_count')
            ->limit(10)
            ->get(['id', 'title', 'document_type'])
            ->map(fn (LibraryResource $resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'type' => $resource->document_type->value,
                'readings' => (int) $resource->getAttribute('readings_count'),
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
                'sales' => [
                    'purchases' => $purchaseCount,
                    'paid' => $paidCount,
                    'refunded' => $refundedCount,
                    'revenue_minor' => $revenueMinor,
                ],
                'engagement' => [
                    'total_readings' => $totalReadings,
                    'unique_readers' => $uniqueReaders,
                    'completed_readings' => $completedReadings,
                    'completion_rate' => $totalReadings > 0
                        ? round($completedReadings / $totalReadings, 4)
                        : null,
                ],
                'top_resources' => $topResources->toArray(),
            ],
        ]);
    }
}
