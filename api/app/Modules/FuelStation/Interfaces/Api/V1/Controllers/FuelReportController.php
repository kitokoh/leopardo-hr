<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reporting opérationnel FuelStation (FUEL-017, issue #5811).
 *
 * - GET /fuel-station/reports/{type}?station_id=&period_start=&period_end=
 *   : snapshot pré-agrégé (généré ou calculé à la volée).
 * - Types : pump_volumes, sales, shifts, variances, stock,
 *   station_performance.
 *
 * Manager uniquement (deny-by-default) ; isolation tenant fail-closed (404) ;
 * recalcul idempotent (clé unique station/type/période).
 */
class FuelReportController extends Controller
{
    public function __construct(private readonly FuelReportingService $reports) {}

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

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
