<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * FUEL-017 (#5811) — Reporting opérationnel FuelStation.
 *
 * Lecture pure (manager), agrégats idempotents, périmètre (période, station)
 * borné au tenant courant.
 */
class FuelReportController extends Controller
{
    public function __construct(private readonly FuelReportService $reports)
    {
    }

    public function sales(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->reports->sales(
            $actor->company_id,
            $this->from($request),
            $this->to($request),
            $this->stationId($request),
        )]);
    }

    public function shifts(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->reports->shifts(
            $actor->company_id,
            $this->stationId($request),
        )]);
    }

    public function cashSessions(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->reports->cashSessions(
            $actor->company_id,
            $this->from($request),
            $this->to($request),
            $this->stationId($request),
        )]);
    }

    private function from(Request $request): Carbon
    {
        return $request->query('from') !== null
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : Carbon::today();
    }

    private function to(Request $request): Carbon
    {
        return $request->query('to') !== null
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : Carbon::today()->endOfDay();
    }

    private function stationId(Request $request): ?int
    {
        return $request->query('station_id') !== null ? (int) $request->query('station_id') : null;
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
