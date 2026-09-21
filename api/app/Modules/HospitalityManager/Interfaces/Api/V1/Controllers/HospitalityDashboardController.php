<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KPIs du tableau de bord Hospitality — HOSP-004 (#7946).
 *
 * Occupation du jour, arrivées/départs du jour, réservations en ligne en
 * attente. Les KPIs locatifs (loyers en retard) arrivent avec HOSP-005.
 */
class HospitalityDashboardController extends Controller
{
    use ChecksHospitalitySolution;

    public function kpis(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HospitalityReservation::class);

        $companyId = $actor->company_id;
        $today = now()->toDateString();

        $unitsQuery = fn () => HospitalityUnit::query()->where('company_id', $companyId);

        $operationalUnits = $unitsQuery()
            ->whereNotIn('status', [HospitalityUnit::STATUS_MAINTENANCE, HospitalityUnit::STATUS_OUT_OF_SERVICE])
            ->count();

        $occupiedUnits = $unitsQuery()
            ->where('status', HospitalityUnit::STATUS_OCCUPIED)
            ->count();

        $reservationsQuery = fn () => HospitalityReservation::query()->where('company_id', $companyId);

        return response()->json([
            'data' => [
                'date' => $today,
                'occupancy' => [
                    'operational_units' => $operationalUnits,
                    'occupied_units' => $occupiedUnits,
                    'rate' => $operationalUnits > 0
                        ? round($occupiedUnits / $operationalUnits, 4)
                        : null,
                ],
                'arrivals_today' => $reservationsQuery()
                    ->whereDate('check_in', $today)
                    ->whereIn('status', [HospitalityReservation::STATUS_CONFIRMED, HospitalityReservation::STATUS_CHECKED_IN])
                    ->count(),
                'departures_today' => $reservationsQuery()
                    ->whereDate('check_out', $today)
                    ->whereIn('status', [HospitalityReservation::STATUS_CHECKED_IN, HospitalityReservation::STATUS_CHECKED_OUT])
                    ->count(),
                'pending_online' => $reservationsQuery()
                    ->where('status', HospitalityReservation::STATUS_PENDING)
                    ->where('source', HospitalityReservation::SOURCE_ONLINE)
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->count(),
            ],
        ]);
    }
}
