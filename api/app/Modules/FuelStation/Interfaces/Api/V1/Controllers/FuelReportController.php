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
use App\Modules\FuelStation\Domain\Policies\FuelReportPolicy;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportingService;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CreateFuelReportExportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporting opérationnel (FUEL-017, issue #5811). deny-by-default
 * (FuelReportPolicy) : rapports réservés aux managers. Read models calculés
 * à la volée, tenant-scoped, bornés.
 */
class FuelReportController extends Controller
{
    public function __construct(
        private readonly FuelReportService $reports,
        private readonly FuelReportingService $typedReports,
    ) {}

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

        $result = $this->typedReports->snapshot($station, $type, $periodStart, $periodEnd, $actor);

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

    /**
     * Rapports « à segments » de l'admin (issue #7398).
     *
     * Les quatre segments d'URL documentés côté admin (`daily-volumes`,
     * `sales`, `stock`, `variances`) et le segment `shifts` délèguent au
     * rapport typé `show()` : une seule implémentation, pas de duplication de
     * la logique de snapshot (idempotente par station/période/type).
     */
    public function dailyVolumes(Request $request): JsonResponse
    {
        // Volumes par pompe : type canonique `pump_volumes` (le segment d'URL
        // reste `daily-volumes`, contrat de l'écran admin).
        return $this->show($request, FuelReportSnapshot::TYPE_PUMP_VOLUMES);
    }

    public function sales(Request $request): JsonResponse
    {
        return $this->show($request, FuelReportSnapshot::TYPE_SALES);
    }

    public function stock(Request $request): JsonResponse
    {
        return $this->show($request, FuelReportSnapshot::TYPE_STOCK);
    }

    public function variances(Request $request): JsonResponse
    {
        return $this->show($request, FuelReportSnapshot::TYPE_VARIANCES);
    }

    public function shifts(Request $request): JsonResponse
    {
        return $this->show($request, FuelReportSnapshot::TYPE_SHIFTS);
    }

    /**
     * Liste des exports de rapport du tenant (GET /reports/exports) — manager.
     */
    public function exports(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->viewAny($actor), 403);

        $query = FuelReportExport::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $exports = $query
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($exports->items())->map(fn (FuelReportExport $export): array => $this->exportPayload($export)),
            'meta' => [
                'current_page' => $exports->currentPage(),
                'last_page' => $exports->lastPage(),
                'total' => $exports->total(),
            ],
        ]);
    }

    /**
     * Lancement d'un export asynchrone (POST /reports/exports) — manager.
     *
     * L'export est créé en `pending` puis confié à la queue `documents`
     * (`GenerateFuelReportExportJob`) : le cycle pending → generating →
     * generated|failed est celui du modèle, le lien de téléchargement est
     * borné par `expires_at`.
     */
    public function createExport(CreateFuelReportExportRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->createExport($actor), 403);

        $stationId = $request->validated('station_id');
        $reportDate = $request->validated('date');

        $export = FuelReportExport::query()->create([
            'company_id' => (string) $actor->company_id,
            'station_id' => is_numeric($stationId) ? (int) $stationId : null,
            'report_type' => (string) $request->validated('report_type'),
            'status' => FuelReportExport::STATUS_PENDING,
            'report_date' => is_string($reportDate) ? $reportDate : now()->toDateString(),
            'requested_by' => $actor->id,
            'expires_at' => now()->addHours(FuelReportExport::EXPORT_TTL_HOURS),
        ]);

        GenerateFuelReportExportJob::dispatch((int) $export->id);

        return response()->json(['data' => $this->exportPayload($export->refresh())], 201);
    }

    /**
     * Téléchargement du CSV généré (GET /reports/exports/{export}/download).
     *
     * 409 tant que l'export n'est pas `generated`, 410 s'il est expiré,
     * 404 pour un export d'un autre tenant (indiscernable d'un inexistant).
     */
    public function download(Request $request, FuelReportExport $export): JsonResponse|StreamedResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($export->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        abort_unless(app(FuelReportPolicy::class)->download($actor, $export), 403);

        $path = $export->file_path;

        if ($export->status !== FuelReportExport::STATUS_GENERATED || ! is_string($path) || $path === '') {
            abort(409, 'EXPORT_NOT_READY');
        }

        abort_if($export->expires_at !== null && $export->expires_at->isPast(), 410, 'EXPORT_EXPIRED');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404, 'EXPORT_FILE_MISSING');

        return $disk->download($path, 'fuel-report-'.$export->id.'.csv');
    }

    /**
     * @return array<string, mixed>
     */
    private function exportPayload(FuelReportExport $export): array
    {
        return [
            'id' => $export->id,
            'station_id' => $export->station_id,
            'report_type' => $export->report_type,
            'status' => $export->status,
            'report_date' => $export->report_date?->toDateString(),
            'requested_by' => $export->requested_by,
            'expires_at' => $export->expires_at?->toIso8601String(),
            'error' => $export->error,
            'created_at' => $export->created_at?->toIso8601String(),
        ];
    }
}
