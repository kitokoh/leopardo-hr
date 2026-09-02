<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Services\TravelReportService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\TravelReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\TravelAgency\Application\Queries\CancellationsReportQuery
use App\Modules\TravelAgency\Application\Queries\DashboardKpisQuery
use App\Modules\TravelAgency\Application\Queries\OccupancyReportQuery
use App\Modules\TravelAgency\Application\Queries\RevenueReportQuery
use App\Modules\TravelAgency\Application\Queries\SalesReportQuery
use App\Modules\TravelAgency\Domain\Models\TravelReportExport
use App\Modules\TravelAgency\Infrastructure\Jobs\ExportTravelReportJob
use App\Modules\TravelAgency\Infrastructure\Services\TravelReportExportStorage
use Illuminate\Support\Facades\Bus;
use App\Modules\TravelAgency\Application\Queries\DashboardKpisQuery
use App\Modules\TravelAgency\Application\Queries\OccupancyReportQuery
use App\Modules\TravelAgency\Application\Queries\RevenueReportQuery
use App\Modules\TravelAgency\Application\Queries\SalesReportQuery
use App\Modules\TravelAgency\Domain\Models\TravelReportExport
use App\Modules\TravelAgency\Infrastructure\Jobs\ExportTravelReportJob
use App\Modules\TravelAgency\Infrastructure\Services\TravelReportExportStorage

/**
 * TRAVEL-501..504 / 507 (#6071..#6074, #6077) — Rapports & dashboard travel.
 *
 * Endpoints agrégés (période, trajet, route, source) : ventes, occupation,
 * recettes, annulations, KPIs du jour. Permission `travel.reports` (Gate).
 * Chiffres TOUJOURS recalculés serveur en minor units.
 */
class TravelReportController extends Controller
{
    public function __construct(private readonly TravelReportService $reports) {}

    public function sales(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->sales(
            $company, $from, $to, $f['trip_id'], $f['route_id'], $f['source'], $f['status'],
        ));
    }

    public function occupancy(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->occupancy(
            $company, $from, $to, $f['trip_id'], $f['route_id'],
        ));
    }

    public function revenue(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->revenue(
            $company, $from, $to, $f['trip_id'], $f['source'],
        ));
    }

    public function cancellations(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->cancellations(
            $company, $from, $to, $f['trip_id'], $f['source'],
        ));
    }

    public function dashboard(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('travel.reports')) {
            abort(403);
        }

        $tripId = $request->query('trip_id');
        $tripId = is_numeric($tripId) ? (int) $tripId : null;

        $days = $request->query('days');
        $days = is_numeric($days) ? max(1, min(90, (int) $days)) : 1;

        return new JsonResponse(['data' => $this->reports->dashboard((string) $actor->company_id, $tripId, $days)]);
    }

    /**
     * @param  callable(string, CarbonImmutable, CarbonImmutable, array<string, mixed>): array<string, mixed>  $aggregate
     */
    /**
     * @param  callable(string, CarbonImmutable, CarbonImmutable, array<string, mixed>): array<string, mixed>  $aggregate
     */
    private function report(TravelReportRequest $request, callable $aggregate): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('travel.reports')) {
            abort(403);
        }

        $v = $request->validated();

        $tripId = is_numeric($v['trip_id'] ?? null) ? (int) $v['trip_id'] : null;
        $routeId = is_numeric($v['route_id'] ?? null) ? (int) $v['route_id'] : null;

        $payload = $aggregate(
            (string) $actor->company_id,
            CarbonImmutable::parse((string) $v['from']),
            CarbonImmutable::parse((string) $v['to']),
            [
                'trip_id' => $tripId,
                'route_id' => $routeId,
                'source' => $v['source'] ?? null,
                'status' => $v['status'] ?? null,
            ],
        );

        return new JsonResponse(['data' => $payload]);
    }

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