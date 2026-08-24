<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\GeofenceZoneService;
use App\Modules\Attendance\Application\DTOs\GeoEventDTO;
use App\Modules\Attendance\Domain\Exceptions\OutsideGeofenceException;
use App\Modules\Attendance\Domain\Exceptions\SessionAlreadyOpenException;
use App\Modules\Attendance\Domain\Models\EmployeeLocationEvent;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestion du cycle de vie des sessions GPS.
 *
 * Chemin d'usage unique de la géofence via GeofenceZoneService (ADR-0016 Phase 2, #5353) —
 * pas de duplication de la logique Haversine.
 */
class GeoSessionManager
{
    public function __construct(
        private readonly GeofenceZoneService $zoneService,
    ) {}

    /**
     * Ouvrir une session GPS (événement zone_enter).
     *
     * @throws SessionAlreadyOpenException
     * @throws OutsideGeofenceException
     */
    public function openSession(GeoEventDTO $dto): GeoAttendanceSession
    {
        $employee = Employee::findOrFail($dto->employeeId);
        $company = currentCompany();

        // ── 1. Re-vérifier la zone côté serveur (anti-spoofing) ──────────────
        // #4255 : cette vérification est HORS transaction. Avant, logEvent()
        // (TYPE_OUTSIDE_ZONE) écrivait dans la même transaction que la session,
        // puis OutsideGeofenceException déclenchait un ROLLBACK total → l'événement
        // suspect (spec #3887 « hors géofence → 422 + event outside_zone loggé »)
        // n'était JAMAIS persisté. Loggé avant la transaction, il est committé
        // indépendamment du 422.
        try {
            $this->zoneService->assertInsideZone($company, $employee, $dto->latitude, $dto->longitude);
        } catch (OutsideGeofenceException $e) {
            $this->logEvent($dto, null, EmployeeLocationEvent::TYPE_OUTSIDE_ZONE);

            throw $e;
        }

        return DB::transaction(function () use ($dto): GeoAttendanceSession {

            $employee = Employee::findOrFail($dto->employeeId);
            $company = currentCompany();

            // ── 2. Vérifier qu'aucune session n'est déjà ouverte ─────────────
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
                Log::info('[Attendance.Geo] Duplicate zone_enter ignored', [
                    'employee_id' => $dto->employeeId,
                    'session_id' => $existing->id,
                ]);
                throw new SessionAlreadyOpenException($dto->employeeId, $existing->id);
            }

            // ── 3. Identifier le site (si l'employé est rattaché à un site) ──
            $siteId = $this->resolveSiteId($employee, $company, $dto->latitude, $dto->longitude);

            // ── 4. Créer la session ───────────────────────────────────────────
            /** @var GeoAttendanceSession $session */
            $session = GeoAttendanceSession::create([
                'employee_id' => $dto->employeeId,
                'company_id' => $dto->companyId,
                'site_id' => $siteId,
                'started_at' => now(),
                'check_in_lat' => $dto->latitude,
                'check_in_lng' => $dto->longitude,
                'check_in_accuracy_meters' => $dto->accuracyMeters,
                'status' => GeoAttendanceSession::STATUS_DETECTED,
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
                Log::info('[Attendance.Geo] zone_exit received with no open session', [
                    'employee_id' => $dto->employeeId,
                ]);

                return null;
            }

            // ── 2. Fermer la session ─────────────────────────────────────────
            $endedAt = now();
            $durationSeconds = (int) $session->started_at->diffInSeconds($endedAt);

            $session->update([
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'check_out_lat' => $dto->latitude,
                'check_out_lng' => $dto->longitude,
                'check_out_accuracy_meters' => $dto->accuracyMeters,
                'status' => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
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
            'employee_id' => $dto->employeeId,
            'company_id' => $dto->companyId,
            'geo_session_id' => $sessionId,
            'event_type' => $eventType,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'accuracy_meters' => $dto->accuracyMeters,
            'device_timestamp' => $dto->deviceTimestamp,
            'metadata' => $dto->metadata,
        ]);
    }

    private function resolveSiteId(
        Employee $employee,
        Company $company,
        float $lat,
        float $lng
    ): ?int {
        // Priorité : site assigné à l'employé — résolution centralisée dans le
        // chemin d'usage unique de la géofence (ADR-0016 Phase 2, #5353).
        return $this->zoneService->resolveSiteId($employee, $company, $lat, $lng);
    }
}
