<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Policies\FuelReportPolicy;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;use App\Modules\FuelStation\Domain\Models\FuelStation;use App\Modules\FuelStation\Infrastructure\Services\FuelReportingService;
use App\Jobs\GenerateFuelReportExportJob;use App\Modules\FuelStation\Domain\Models\FuelReportExport;use App\Modules\FuelStation\Domain\Models\FuelStation;use App\Modules\FuelStation\Infrastructure\Services\FuelReportingService;use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CreateFuelReportExportRequest;use Illuminate\Support\Facades\Storage;use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporting opérationnel (FUEL-017, issue #5811). deny-by-default
 * (FuelReportPolicy) : rapports réservés aux managers. Read models calculés
 * à la volée, tenant-scoped, bornés.
 */
class FuelReportController extends Controller
{
    public function __construct(private readonly FuelReportService $reports) {}

    public function dailySales(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->viewAny($actor), 403);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $date = $request->filled('date') ? Carbon::parse((string) $request->string('date')) : now();

        return response()->json([
            'data' => $this->reports->dailySales((string) $actor->company_id, $stationId, $date),
        ]);
    }

    public function shiftSummary(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->viewAny($actor), 403);

        $shiftId = $request->integer('shift_id', 0);

        abort_if($shiftId <= 0, 422, 'SHIFT_ID_REQUIRED');

        $date = $request->filled('date') ? Carbon::parse((string) $request->string('date')) : now();

        return response()->json([
            'data' => $this->reports->shiftSummary((string) $actor->company_id, $shiftId, $date),
        ]);
    }

    public function anomalies(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->viewAny($actor), 403);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $from = $request->filled('date_from') ? Carbon::parse((string) $request->string('date_from')) : now()->subDays(7);
        $to = $request->filled('date_to') ? Carbon::parse((string) $request->string('date_to')) : now();

        return response()->json([
            'data' => $this->reports->meterAnomalies((string) $actor->company_id, $stationId, $from, $to),
        ]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }


    public function show(Request $request, string $type): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewReports', FuelReportSnapshot::class);

        if (! in_array($type, FuelReportSnapshot::TYPES, true)) {
            abort(404, 'SNAPSHOT_TYPE_UNKNOWN');
        }

        $station = FuelStation::query()
            ->where('company_id', $actor->company_id)
            ->find($request->integer('station_id'));

        if (! $station instanceof FuelStation) {
            abort(404);
        }

        $periodStartRaw = $request->input('period_start') ?? now()->startOfMonth()->toDateString();
        $periodEndRaw = $request->input('period_end') ?? now()->toDateString();
        $periodStart = is_string($periodStartRaw) ? $periodStartRaw : now()->startOfMonth()->toDateString();
        $periodEnd = is_string($periodEndRaw) ? $periodEndRaw : now()->toDateString();

        $result = $this->reports->snapshot($station, $type, $periodStart, $periodEnd, $actor);

        return response()->json([
            'data' => [
                'id' => $result['snapshot']->id,
                'station_id' => $result['snapshot']->station_id,
                'snapshot_type' => $result['snapshot']->snapshot_type,
                'period_start' => Carbon::parse((string) $result['snapshot']->period_start)->toDateString(),
                'period_end' => Carbon::parse((string) $result['snapshot']->period_end)->toDateString(),
                'payload' => $result['snapshot']->payload,
                'generated_at' => $result['snapshot']->generated_at->toIso8601String(),
            ],
            'meta' => ['recomputed' => $result['recomputed']],
        ]);
    }


    public function dailyVolumes(Request $request): JsonResponse
    {
        return $this->snapshot($request, FuelReportSnapshot::TYPE_DAILY_VOLUMES);
    }


    public function sales(Request $request): JsonResponse
    {
        return $this->snapshot($request, FuelReportSnapshot::TYPE_SALES_SUMMARY);
    }


    public function stock(Request $request): JsonResponse
    {
        return $this->snapshot($request, FuelReportSnapshot::TYPE_STOCK_STATUS);
    }


    public function variances(Request $request): JsonResponse
    {
        return $this->snapshot($request, FuelReportSnapshot::TYPE_VARIANCE_SUMMARY);
    }


    public function shifts(Request $request): JsonResponse
    {
        return $this->snapshot($request, FuelReportSnapshot::TYPE_SHIFT_SUMMARY);
    }


    public function createExport(CreateFuelReportExportRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->viewAny($actor), 403);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $date = $request->filled('date') ? Carbon::parse((string) $request->string('date')) : now();

        return response()->json([
            'data' => $this->reports->dailySales((string) $actor->company_id, $stationId, $date),
        ]);
    }


    public function exports(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->viewAny($actor), 403);

        $shiftId = $request->integer('shift_id', 0);

        abort_if($shiftId <= 0, 422, 'SHIFT_ID_REQUIRED');

        $date = $request->filled('date') ? Carbon::parse((string) $request->string('date')) : now();

        return response()->json([
            'data' => $this->reports->shiftSummary((string) $actor->company_id, $shiftId, $date),
        ]);
    }


    public function download(Request $request, FuelReportExport $export): StreamedResponse|JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->viewAny($actor), 403);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $from = $request->filled('date_from') ? Carbon::parse((string) $request->string('date_from')) : now()->subDays(7);
        $to = $request->filled('date_to') ? Carbon::parse((string) $request->string('date_to')) : now();

        return response()->json([
            'data' => $this->reports->meterAnomalies((string) $actor->company_id, $stationId, $from, $to),
        $this->authorize('viewReports', FuelReportSnapshot::class);

        if (! in_array($type, FuelReportSnapshot::TYPES, true)) {
            abort(404, 'SNAPSHOT_TYPE_UNKNOWN');
        }

        $station = FuelStation::query()
            ->where('company_id', $actor->company_id)
            ->find($request->integer('station_id'));

        if ($export->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('download', $export);

        if ($export->status !== FuelReportExport::STATUS_GENERATED || $export->file_path === null) {
            return response()->json([
                'error' => 'FUEL_REPORT_NOT_READY',
                'message' => 'L\'export n\'est pas encore généré.',
            ], 422);
        }

        if ($export->expires_at !== null && $export->expires_at->isPast()) {
            return response()->json([
                'error' => 'FUEL_REPORT_EXPIRED',
                'message' => 'L\'export a expiré, relancez la génération.',
            ], 422);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($export->file_path)) {
            return response()->json([
                'error' => 'FUEL_REPORT_FILE_MISSING',
                'message' => 'Le fichier d\'export est introuvable sur le serveur.',
            ], 404);
        }

        return $disk->download($export->file_path, "fuel-report-{$export->id}.csv");
    }


    private function snapshot(Request $request, string $reportType): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelReportSnapshot::class);

        $stationId = $request->integer('station_id');

        if ($stationId <= 0) {
            return response()->json(['error' => 'FUEL_REPORT_STATION_REQUIRED', 'message' => 'station_id est requis.'], 422);
        }

        /** @var FuelStation|null $station */
        $station = FuelStation::query()
            ->where('company_id', $actor->company_id)
            ->find($stationId);

        if (! $station instanceof FuelStation) {
            abort(404);
        }

        $periodStartRaw = $request->input('period_start') ?? now()->startOfMonth()->toDateString();
        $periodEndRaw = $request->input('period_end') ?? now()->toDateString();
        $periodStart = is_string($periodStartRaw) ? $periodStartRaw : now()->startOfMonth()->toDateString();
        $periodEnd = is_string($periodEndRaw) ? $periodEndRaw : now()->toDateString();

        $result = $this->reports->snapshot($station, $type, $periodStart, $periodEnd, $actor);

        return response()->json([
            'data' => [
                'id' => $result['snapshot']->id,
                'station_id' => $result['snapshot']->station_id,
                'snapshot_type' => $result['snapshot']->snapshot_type,
                'period_start' => Carbon::parse((string) $result['snapshot']->period_start)->toDateString(),
                'period_end' => Carbon::parse((string) $result['snapshot']->period_end)->toDateString(),
                'payload' => $result['snapshot']->payload,
                'generated_at' => $result['snapshot']->generated_at->toIso8601String(),
            ],
            'meta' => ['recomputed' => $result['recomputed']],
        $date = $request->input('date', now()->toDateString());

        if (! is_string($date)) {
            $date = now()->toDateString();
        }

        $result = $this->reports->compute($station, $reportType, $date);

        return response()->json([
            'data' => $result['snapshot']->payload,
            'computed_at' => $result['snapshot']->computed_at?->toISOString(),
            'recomputed' => $result['recomputed'],
        ]);
    }

    private function exportPayload(FuelReportExport $export): array
    {
        return [
            'id' => $export->id,
            'station_id' => $export->station_id,
            'report_type' => $export->report_type,
            'status' => $export->status,
            'report_date' => $export->report_date?->toDateString(),
            'expires_at' => $export->expires_at?->toISOString(),
            'error' => $export->error,
            'created_at' => $export->created_at?->toISOString(),
        ];
    }
}