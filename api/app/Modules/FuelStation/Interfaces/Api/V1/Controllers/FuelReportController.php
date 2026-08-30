<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateFuelReportExportJob;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelReportExport;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportingService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CreateFuelReportExportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporting opérationnel FuelStation (FUEL-017, #5811).
 *
 * Read models (snapshots idempotents) + exports CSV asynchrones
 * (pending → generating → generated | failed, lien borné 24 h).
 * Manager + solution active (fail-closed) + tenant-scoped (404
 * cross-tenant).
 */
class FuelReportController extends Controller
{
    public function __construct(
        private readonly FuelReportingService $reports,
    ) {}

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
        $this->authorize('createExport', FuelReportExport::class);

        $stationId = $request->input('station_id');
        $date = $request->input('date', now()->toDateString());

        if (is_array($stationId)) {
            abort(422);
        }

        /** @var FuelReportExport $export */
        $export = FuelReportExport::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $stationId !== null ? (int) $stationId : null,
            'report_type' => $request->input('report_type'),
            'status' => FuelReportExport::STATUS_PENDING,
            'report_date' => is_string($date) ? $date : now()->toDateString(),
            'requested_by' => $actor->id,
        ]);

        GenerateFuelReportExportJob::dispatch($export->id);

        return response()->json(['data' => $this->exportPayload($export->refresh())], 201);
    }

    public function exports(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelReportExport::class);

        $query = FuelReportExport::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $exports = $query->orderByDesc('id')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($exports->items())->map(fn (FuelReportExport $export): array => $this->exportPayload($export)),
            'meta' => [
                'current_page' => $exports->currentPage(),
                'last_page' => $exports->lastPage(),
                'total' => $exports->total(),
            ],
        ]);
    }

    public function download(Request $request, FuelReportExport $export): StreamedResponse|JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

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

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /** @return array<string, mixed> */
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
