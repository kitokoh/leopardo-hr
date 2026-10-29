<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Attendance\Domain\Exceptions\OutsideGeofenceException;

/**
 * Chemin d'usage UNIQUE de la géofence (ADR-0016 Phase 2, issue #5353).
 *
 * `AttendanceGeofenceService` reste l'unique implémentation du calcul de
 * distance (Haversine) ; ce service est l'unique CONSOMMATEUR direct du
 * module. Les chemins métier (punch mobile `AttendanceService`, sessions
 * GPS `GeoSessionManager`) passent désormais par lui :
 *
 * - `evaluateZone()`    → évaluation pure, informative (jamais d'exception)
 * - `assertInsideZone()` → politique bloquante : même erreur
 *   (`OutsideGeofenceException`) partout, aucun caller ne ré-implémente la
 *   décision « configuré && dehors »
 * - `resolveSiteId()`   → résolution du site assigné par distance (ancienne
 *   logique de `GeoSessionManager::resolveSiteId()`), centralisée ici
 *
 * Règle opérationnelle ADR-0016 : interdiction d'ajouter une seconde logique
 * de calcul de distance — garde CI `check-geofence-single-usage.sh`.
 */
class GeofenceZoneService
{
    public function __construct(
        private readonly AttendanceGeofenceService $geofenceService,
    ) {}

    /**
     * Évaluation pure de la zone — ne jette jamais.
     *
     * @return array{configured: bool, inside: bool|null, distance_meters: int|null, radius_meters: int|null, source: string|null}
     */
    public function evaluateZone(Company $company, Employee $employee, ?float $lat, ?float $lng): array
    {
        /** @var array{configured: bool, inside: bool|null, distance_meters: int|null, radius_meters: int|null, source: string|null} $geo */
        $geo = $this->geofenceService->evaluate($company, $employee, $lat, $lng);

        return $geo;
    }

    /**
     * Politique bloquante — jette `OutsideGeofenceException` si la zone est
     * configurée et que la position est en dehors.
     *
     * @return array{configured: bool, inside: bool|null, distance_meters: int|null, radius_meters: int|null, source: string|null}
     *
     * @throws OutsideGeofenceException
     */
    public function assertInsideZone(Company $company, Employee $employee, ?float $lat, ?float $lng): array
    {
        $geo = $this->evaluateZone($company, $employee, $lat, $lng);

        if ($geo['configured'] && $geo['inside'] === false) {
            throw new OutsideGeofenceException(
                (float) $geo['distance_meters'],
                (float) $geo['radius_meters'],
            );
        }

        return $geo;
    }

    /**
     * Résolution du site assigné à l'employé par distance (rayon `gps_radius_m`).
     * Retourne l'id du site si la position est dans le rayon, sinon null.
     */
    public function resolveSiteId(Employee $employee, Company $company, float $lat, float $lng): ?int
    {
        if (! $employee->site_id) {
            return null;
        }

        $site = Site::query()
            ->where('company_id', $employee->company_id)
            ->whereNotNull('gps_lat')
            ->whereNotNull('gps_lng')
            ->find($employee->site_id);

        if ($site) {
            $distance = $this->geofenceService->distanceMeters(
                $lat,
                $lng,
                (float) $site->gps_lat,
                (float) $site->gps_lng,
            );

            if ($distance <= (float) $site->gps_radius_m) {
                return $site->id;
            }
        }

        return null;
    }
}
