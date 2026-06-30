<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\SmartAttendance\Domain\Models\AttendanceModeSettings;

/**
 * Cas d'usage (niveau 1) : configurer le mode de pointage pour toute l'entreprise.
 * Réservé au rôle principal.
 */
class SetCompanyAttendanceMode
{
    public function handle(string $companyId, array $data, Employee $updatedBy): AttendanceModeSettings
    {
        /** @var AttendanceModeSettings $settings */
        $settings = AttendanceModeSettings::firstOrNew(['company_id' => $companyId]);

        $settings->fill([
            'forced_mode'             => $data['forced_mode'] ?? null,
            'gps_enabled'             => $data['gps_enabled'] ?? false,
            'latitude'                => $data['latitude'] ?? null,
            'longitude'               => $data['longitude'] ?? null,
            'radius_meters'           => $data['radius_meters'] ?? 100,
            'allow_employee_override' => $data['allow_employee_override'] ?? true,
            'updated_by'              => $updatedBy->id,
        ]);

        $settings->save();

        return $settings;
    }
}
