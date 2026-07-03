<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\SmartAttendance\Domain\Exceptions\GpsConsentMissingException;
use App\Modules\SmartAttendance\Domain\Models\EmployeeAttendancePreference;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage (niveau 2) : l'employé définit son mode de pointage préféré.
 * N'est exécuté que si la company n'impose pas de mode (forced_mode IS NULL).
 */
class SetEmployeeAttendanceMode
{
    /**
     * @throws GpsConsentMissingException
     */
    public function handle(Employee $employee, array $data): EmployeeAttendancePreference
    {
        $preferredMode = $data['preferred_mode'];

        // Si l'employé veut le GPS auto, le consentement est obligatoire
        if ($preferredMode === 'gps_auto' && empty($data['gps_consent_given'])) {
            throw new GpsConsentMissingException();
        }

        /** @var EmployeeAttendancePreference $pref */
        $pref = EmployeeAttendancePreference::firstOrNew([
            'employee_id' => $employee->id,
        ]);

        $pref->fill([
            'employee_id'    => $employee->id,
            'company_id'     => $employee->company_id,
            'preferred_mode' => $preferredMode,
        ]);

        if ($preferredMode === 'gps_auto' && ! empty($data['gps_consent_given'])) {
            $pref->gps_consent_given = true;
            $pref->gps_consent_at    = Carbon::now();
        }

        if (($data['revoke_consent'] ?? false) === true) {
            $pref->gps_consent_given = false;
            $pref->gps_consent_at    = null;
            // Si on révoque le consentement, repasser en mode manuel
            $pref->preferred_mode = 'manual';
        }

        $pref->save();

        return $pref;
    }
}
