<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Infrastructure\Services\AttendanceGeofenceService;
use App\Modules\SmartAttendance\Application\DTOs\GeoEventDTO;
use App\Modules\SmartAttendance\Domain\Exceptions\OutsideGeofenceException;
use App\Modules\SmartAttendance\Domain\Exceptions\SessionAlreadyOpenException;
use App\Modules\SmartAttendance\Domain\Models\EmployeeLocationEvent;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestion du cycle de vie des sessions GPS.
 *
 * Réutilise AttendanceGeofenceService existant pour le calcul de zone —
 * pas de duplication de la logique Haversine.
 */
class GeoSessionManager
{
    public function __construct(
        private readonly AttendanceGeofenceService $geofenceService,
    ) {}

    /**
     * Ouvrir une session GPS (événement zone_enter).
     *
     * @throws SessionAlreadyOpenException
     * @throws OutsideGeofenceException
     */
    public function openSession(GeoEventDTO $dto): GeoAttendanceSession
    {
        return DB::transaction(function () use ($dto): GeoAttendanceSession {

            $employee = Employee::findOrFail($dto->employeeId);
            $company  = currentCompany();

            // ── 1. Vérifier qu'aucune session n'est déjà ouverte ─────────────
            $existing = GeoAttendanceSession::query()
                ->where('employee_id', $dto->employeeId)
                ->where('company_id', $dto->companyId)
                ->whereNull('ended_at')
                ->whereIn('status', [
                    GeoAttendanceSession::STATUS_DETECTED,
                    GeoAttendanceSession::STATUS_PENDING_VALIDATION,
                ])
                ->first();

            if ($existing) {
                // Log l'événement dupliqué mais ne plante pas
                Log::info('[SmartAttendance] Duplicate zone_enter ignored', [
                    'employee_id' => $dto->employeeId,
                    'session_id'  => $existing->id,
                ]);
                throw new SessionAlreadyOpenException($dto->employeeId, $existing->id);
            }

            // ── 2. Re-vérifier la zone côté serveur (anti-spoofing) ──────────
            $geo = $this->geofenceService->evaluate($company, $employee, $dto->latitude, $dto->longitude);

            if ($geo['configured'] && $geo['inside'] === false) {
                // Loguer l'événement suspect
                $this->logEvent($dto, null, EmployeeLocationEvent::TYPE_OUTSIDE_ZONE);

                throw new OutsideGeofenceException(
                    (float) $geo['distance_meters'],
                    (float) $geo['radius_meters']
                );
            }

            // ── 3. Identifier le site (si l'employé est rattaché à un site) ──
            $siteId = $this->resolveSiteId($employee, $company, $dto->latitude, $dto->longitude);

            // ── 4. Créer la session ───────────────────────────────────────────
            /** @var GeoAttendanceSession $session */
            $session = GeoAttendanceSession::create([
                'employee_id'              => $dto->employeeId,
                'company_id'               => $dto->companyId,
                'site_id'                  => $siteId,
                'started_at'               => now(),
                'check_in_lat'             => $dto->latitude,
                'check_in_lng'             => $dto->longitude,
                'check_in_accuracy_meters' => $dto->accuracyMeters,
                'status'                   => GeoAttendanceSession::STATUS_DETECTED,
            ]);

            // ── 5. Logger l'événement ────────────────────────────────────────
            $this->logEvent($dto, $session->id, EmployeeLocationEvent::TYPE_ZONE_ENTER);

            return $session;
        });
    }

    /**
     * Fermer une session GPS (événement zone_exit).
     * Retourne null si aucune session ouverte (cas normal en offline/retry).
     */
    public function closeSession(GeoEventDTO $dto): ?GeoAttendanceSession
    {
        return DB::transaction(function () use ($dto): ?GeoAttendanceSession {

            // ── 1. Chercher la session ouverte ───────────────────────────────
            /** @var GeoAttendanceSession|null $session */
            $session = GeoAttendanceSession::query()
                ->where('employee_id', $dto->employeeId)
                ->where('company_id', $dto->companyId)
                ->whereNull('ended_at')
                ->whereIn('status', [
                    GeoAttendanceSession::STATUS_DETECTED,
                    GeoAttendanceSession::STATUS_PENDING_VALIDATION,
                ])
                ->orderByDesc('started_at')
                ->first();

            if (! $session) {
                // Pas de session ouverte — événement orphelin, on le log quand même
                $this->logEvent($dto, null, EmployeeLocationEvent::TYPE_ZONE_EXIT);
                Log::info('[SmartAttendance] zone_exit received with no open session', [
                    'employee_id' => $dto->employeeId,
                ]);
                return null;
            }

            // ── 2. Fermer la session ─────────────────────────────────────────
            $endedAt         = now();
            $durationSeconds = (int) $session->started_at->diffInSeconds($endedAt);

            $session->update([
                'ended_at'                  => $endedAt,
                'duration_seconds'          => $durationSeconds,
                'check_out_lat'             => $dto->latitude,
                'check_out_lng'             => $dto->longitude,
                'check_out_accuracy_meters' => $dto->accuracyMeters,
                'status'                    => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
            ]);

            // ── 3. Logger l'événement ────────────────────────────────────────
            $this->logEvent($dto, $session->id, EmployeeLocationEvent::TYPE_ZONE_EXIT);

            return $session->fresh();
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function logEvent(GeoEventDTO $dto, ?int $sessionId, string $eventType): void
    {
        EmployeeLocationEvent::create([
            'employee_id'       => $dto->employeeId,
            'company_id'        => $dto->companyId,
            'geo_session_id'    => $sessionId,
            'event_type'        => $eventType,
            'latitude'          => $dto->latitude,
            'longitude'         => $dto->longitude,
            'accuracy_meters'   => $dto->accuracyMeters,
            'device_timestamp'  => $dto->deviceTimestamp,
            'metadata'          => $dto->metadata,
        ]);
    }

    private function resolveSiteId(
        Employee $employee,
        Company $company,
        float $lat,
        float $lng
    ): ?int {
        // Priorité : site assigné à l'employé
        if ($employee->site_id) {
            $site = Site::where('company_id', $employee->company_id)
                ->find($employee->site_id);

            if ($site && $site->gps_lat !== null) {
                $distance = $this->geofenceService->distanceMeters(
                    $lat, $lng, (float) $site->gps_lat, (float) $site->gps_lng
                );
                if ($distance <= (float) $site->gps_radius_m) {
                    return $site->id;
                }
            }
        }

        return null;
    }
}

