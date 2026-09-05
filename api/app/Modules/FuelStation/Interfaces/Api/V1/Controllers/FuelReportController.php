<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Policies\FuelReportPolicy;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
}
