<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\SmartAttendance\Application\DTOs\AttendanceModeConfigDTO;
use App\Modules\SmartAttendance\Domain\Models\AttendanceModeSettings;
use App\Modules\SmartAttendance\Domain\Models\EmployeeAttendancePreference;

/**
 * Résout le mode de pointage effectif pour un employé donné.
 *
 * Priorité :
 *   1. attendance_mode_settings.forced_mode (mode entreprise)
 *   2. employee_attendance_preferences.preferred_mode (choix employé)
 *   3. Défaut : 'manual'
 */
class AttendanceModeResolver
{
    public function resolve(Employee $employee): AttendanceModeConfigDTO
    {
        $companyId = (string) $employee->company_id;

        /** @var AttendanceModeSettings|null $companySettings */
        $companySettings = AttendanceModeSettings::where('company_id', $companyId)->first();

        // ── Niveau 1 : mode entreprise forcé ─────────────────────────────────
        if ($companySettings && $companySettings->hasForcedMode()) {
            return new AttendanceModeConfigDTO(
                mode:              (string) $companySettings->forced_mode,
                canOverride:       false,
                gpsEnabled:        $companySettings->gps_enabled,
                geofenceLat:       $companySettings->latitude,
                geofenceLng:       $companySettings->longitude,
                geofenceRadius:    $companySettings->radius_meters,
                requiresConsent:   $companySettings->forced_mode === 'gps_auto',
                requiresPunchPhoto: $companySettings->requiresPunchPhoto(),
            );
        }

        // ── Niveau 2 : préférence individuelle ───────────────────────────────
        $canOverride = $companySettings?->allow_employee_override ?? true;

        /** @var EmployeeAttendancePreference|null $pref */
        $pref = EmployeeAttendancePreference::where('employee_id', $employee->id)->first();

        $mode      = $pref?->preferred_mode ?? 'manual';
        $gpsEnabled = $companySettings?->gps_enabled
            ?? ($mode === 'gps_auto');

        return new AttendanceModeConfigDTO(
            mode:              $mode,
            canOverride:       $canOverride,
            gpsEnabled:        $gpsEnabled,
            geofenceLat:       $companySettings?->latitude,
            geofenceLng:       $companySettings?->longitude,
            geofenceRadius:    $companySettings?->radius_meters ?? 100,
            requiresConsent:   $mode === 'gps_auto' && ! ($pref?->hasGpsConsent() ?? false),
            requiresPunchPhoto: $companySettings?->requiresPunchPhoto() ?? false,
        );
    }
}
