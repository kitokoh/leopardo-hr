<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KPIs du tableau de bord Hospitality — HOSP-004 (#7946), durci #8019.
 *
 * Occupation du jour, arrivées/départs du jour, réservations en ligne en
 * attente. Les KPIs locatifs (loyers en retard) arrivent avec HOSP-005.
 *
 * CHOIX #8019 — les agrégats sont BORNÉS aux établissements que l'acteur peut
 * LIRE (RBAC ressource-scopé `hospitality_property`, niveau `view`), exactement
 * comme `HospitalityReservationController::index` : `null` = aucune restriction
 * (principal, rh en lecture, ou type pas encore assigné) → agrégat tenant-wide
 * historique. Sans ce bornage, un réceptionniste scopé au site A lisait
 * l'occupation, les arrivées/départs et les réservations en attente des autres
 * sites du tenant (fuite de volumétrie + d'activité commerciale).
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

        // RBAC ressource-scopé progressif (HOSP-003 #7945) : bornage des
        // agrégats aux établissements lisibles (`null` = aucune restriction).
        $accessiblePropertyIds = $actor->accessibleResourceIds(
            'hospitality_property',
            EmployeeResourceAssignment::LEVEL_VIEW
        );

        $unitsQuery = fn (): Builder => $this->unitsQuery($companyId, $accessiblePropertyIds);

        $operationalUnits = $unitsQuery()
            ->whereNotIn('status', [HospitalityUnit::STATUS_MAINTENANCE, HospitalityUnit::STATUS_OUT_OF_SERVICE])
            ->count();

        $occupiedUnits = $unitsQuery()
            ->where('status', HospitalityUnit::STATUS_OCCUPIED)
            ->count();

        $reservationsQuery = fn (): Builder => $this->reservationsQuery($companyId, $accessiblePropertyIds);

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

    /**
     * Unités du tenant, bornées aux établissements lisibles par l'acteur
     * (`$accessiblePropertyIds === null` = aucune restriction).
     *
     * @param  list<int>|null  $accessiblePropertyIds
     * @return Builder<HospitalityUnit>
     */
    private function unitsQuery(string $companyId, ?array $accessiblePropertyIds): Builder
    {
        $query = HospitalityUnit::query()->where('company_id', $companyId);

        if ($accessiblePropertyIds !== null) {
            $query->whereIn('property_id', $accessiblePropertyIds);
        }

        return $query;
    }

    /**
     * Réservations du tenant, bornées aux établissements lisibles par l'acteur
     * (`$accessiblePropertyIds === null` = aucune restriction).
     *
     * @param  list<int>|null  $accessiblePropertyIds
     * @return Builder<HospitalityReservation>
     */
    private function reservationsQuery(string $companyId, ?array $accessiblePropertyIds): Builder
    {
        $query = HospitalityReservation::query()->where('company_id', $companyId);

        if ($accessiblePropertyIds !== null) {
            $query->whereIn('property_id', $accessiblePropertyIds);
        }

        return $query;
    }
}
