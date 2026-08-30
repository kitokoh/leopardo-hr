<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Queries\CancellationsReportQuery;
use App\Modules\TravelAgency\Application\Queries\DashboardKpisQuery;
use App\Modules\TravelAgency\Application\Queries\OccupancyReportQuery;
use App\Modules\TravelAgency\Application\Queries\RevenueReportQuery;
use App\Modules\TravelAgency\Application\Queries\SalesReportQuery;
use App\Modules\TravelAgency\Domain\Models\TravelReportExport;
use App\Modules\TravelAgency\Infrastructure\Jobs\ExportTravelReportJob;
use App\Modules\TravelAgency\Infrastructure\Services\TravelReportExportStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

/**
 * TRAVEL-501..507 (#6071..#6077) — Rapports & exports (spec §7.6).
 *
 * Toutes les routes sont protégées par la permission `travel.reports`
 * (Policy TravelReportPolicy) — fail-closed : un employé simple reçoit 403.
 * Les agrégats sont calculés côté serveur en minor units ; l'export CSV
 * est idempotent (même requête → même fichier, URL signée 30 min).
 */
class TravelReportController extends Controller
{
    public function sales(Request $request, SalesReportQuery $sales): JsonResponse
    {
        $this->authorizeReports($request);

        $filters = $this->filters($request);

        return response()->json([
            'data' => $sales->paginated($filters),
            'summary' => $sales->summary($filters),
        ]);
    }

    public function occupancy(Request $request, OccupancyReportQuery $occupancy): JsonResponse
    {
        $this->authorizeReports($request);

        return response()->json([
            'data' => $occupancy->execute($this->filters($request)),
        ]);
    }

    public function revenue(Request $request, RevenueReportQuery $revenue): JsonResponse
    {
        $this->authorizeReports($request);

        return response()->json([
            'data' => $revenue->execute($this->filters($request)),
        ]);
    }

    public function cancellations(Request $request, CancellationsReportQuery $cancellations): JsonResponse
    {
        $this->authorizeReports($request);

        return response()->json([
            'data' => $cancellations->execute($this->filters($request)),
        ]);
    }

    public function dashboard(Request $request, DashboardKpisQuery $kpis): JsonResponse
    {
        $this->authorizeReports($request);

        return response()->json([
            'data' => $kpis->execute($this->filters($request)),
        ]);
    }

    /**
     * Export CSV idempotent : même requête → même fichier (même hash).
     */
    public function export(Request $request, TravelReportExportStorage $storage): JsonResponse
    {
        $this->authorizeReports($request);

        $type = (string) $request->query('type', 'sales');

        if (! in_array($type, ExportTravelReportJob::TYPES, true)) {
            abort(422, "Type d'export inconnu (attendu : ".implode(', ', ExportTravelReportJob::TYPES).').');
        }

        /** @var Employee $actor */
        $actor = $request->user();

        $job = new ExportTravelReportJob(
            companyId: (string) $actor->company_id,
            reportType: $type,
            filters: $this->filters($request),
            generatedByUserId: (int) $actor->id,
        );

        /** @var TravelReportExport $export */
        $export = Bus::dispatchSync($job);

        return response()->json([
            'data' => [
                'request_hash' => $export->request_hash,
                'row_count' => $export->row_count,
                'mime_type' => $export->mime_type,
                'signed_url' => $storage->signedUrl($export->storage_path),
                'expires_at' => $export->expires_at?->toIso8601String(),
            ],
        ]);
    }

    private function authorizeReports(Request $request): void
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelReportExport::class)) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'from' => $request->query('from') ? (string) $request->query('from') : null,
            'to' => $request->query('to') ? (string) $request->query('to') : null,
            'trip_id' => $request->query('trip_id') ? (int) $request->query('trip_id') : null,
            'route_id' => $request->query('route_id') ? (int) $request->query('route_id') : null,
            'source' => $request->query('source') ? (string) $request->query('source') : null,
            'status' => $request->query('status') ? (string) $request->query('status') : null,
            'per_page' => (int) $request->query('per_page', 50),
        ], fn ($v) => $v !== null);
    }
}
