<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\SmartAttendance\Application\Actions\SetCompanyAttendanceMode;
use App\Modules\SmartAttendance\Application\Actions\SetEmployeeAttendanceMode;
use App\Modules\SmartAttendance\Domain\Exceptions\GpsConsentMissingException;
use App\Modules\SmartAttendance\Domain\Models\AttendanceModeSettings;
use App\Modules\SmartAttendance\Domain\Models\EmployeeAttendancePreference;
use App\Modules\SmartAttendance\Infrastructure\Services\AttendanceModeResolver;
use App\Modules\SmartAttendance\Interfaces\Api\V1\Requests\SetCompanyModeRequest;
use App\Modules\SmartAttendance\Interfaces\Api\V1\Requests\SetModeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Gestion des configurations de mode de pointage.
 *
 * GET  /config         → résoudre le mode actif pour l'employé connecté
 * PUT  /preferences    → employé modifie sa préférence
 * GET  /mode-settings  → manager lit la config entreprise
 * PUT  /mode-settings  → principal modifie la config entreprise
 */
class AttendanceModeController extends Controller
{
    public function __construct(
        private readonly AttendanceModeResolver    $resolver,
        private readonly SetCompanyAttendanceMode  $setCompanyMode,
        private readonly SetEmployeeAttendanceMode $setEmployeeMode,
    ) {}

    /**
     * GET /api/v1/smart-attendance/config
     * Retourne la config de mode active pour l'employé connecté.
     * Appelé au démarrage de l'app mobile.
     */
    public function config(): JsonResponse
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = auth()->user();
        $config   = $this->resolver->resolve($employee);

        return response()->json([
            'data' => [
                'mode'             => $config->mode,
                'can_override'     => $config->canOverride,
                'gps_enabled'      => $config->gpsEnabled,
                'geofence'         => $config->gpsEnabled ? [
                    'latitude'       => $config->geofenceLat,
                    'longitude'      => $config->geofenceLng,
                    'radius_meters'  => $config->geofenceRadius,
                ] : null,
                'requires_consent' => $config->requiresConsent,
            ],
        ]);
    }

    /**
     * PUT /api/v1/smart-attendance/preferences
     * L'employé définit son mode préféré (niveau 2).
     */
    public function updatePreference(SetModeRequest $request): JsonResponse
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = auth()->user();
        $company  = currentCompany();

        // Vérifier que l'entreprise autorise l'override
        $settings = AttendanceModeSettings::where('company_id', $company->id)->first();
        if ($settings && $settings->hasForcedMode()) {
            return response()->json([
                'message' => 'Votre entreprise impose un mode de pointage. Vous ne pouvez pas le modifier.',
                'code'    => 'COMPANY_MODE_FORCED',
            ], 403);
        }

        if ($settings && ! $settings->allow_employee_override) {
            return response()->json([
                'message' => 'La personnalisation du mode de pointage est désactivée.',
                'code'    => 'OVERRIDE_NOT_ALLOWED',
            ], 403);
        }

        try {
            $pref = $this->setEmployeeMode->handle($employee, $request->validated());

            return response()->json([
                'message' => 'Préférence mise à jour.',
                'data'    => [
                    'preferred_mode'    => $pref->preferred_mode,
                    'gps_consent_given' => $pref->gps_consent_given,
                    'gps_consent_at'    => $pref->gps_consent_at?->toIso8601String(),
                ],
            ]);
        } catch (GpsConsentMissingException) {
            return response()->json([
                'message' => 'Le consentement GPS est obligatoire pour activer le pointage automatique.',
                'code'    => 'GPS_CONSENT_REQUIRED',
            ], 422);
        }
    }

    /**
     * GET /api/v1/smart-attendance/mode-settings
     * Manager/RH lit la configuration de leur entreprise.
     */
    public function getCompanySettings(): JsonResponse
    {
        $company  = currentCompany();
        $settings = AttendanceModeSettings::where('company_id', $company->id)->first();

        return response()->json([
            'data' => $settings ? [
                'forced_mode'             => $settings->forced_mode,
                'gps_enabled'             => $settings->gps_enabled,
                'latitude'                => $settings->latitude,
                'longitude'               => $settings->longitude,
                'radius_meters'           => $settings->radius_meters,
                'allow_employee_override' => $settings->allow_employee_override,
            ] : null,
        ]);
    }

    /**
     * PUT /api/v1/smart-attendance/mode-settings
     * Principal configure le mode de pointage de l'entreprise.
     */
    public function updateCompanySettings(SetCompanyModeRequest $request): JsonResponse
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager  = auth()->user();
        $company  = currentCompany();

        $settings = $this->setCompanyMode->handle(
            companyId: (string) $company->id,
            data:      $request->validated(),
            updatedBy: $manager,
        );

        return response()->json([
            'message' => 'Configuration mise à jour.',
            'data'    => [
                'forced_mode'             => $settings->forced_mode,
                'gps_enabled'             => $settings->gps_enabled,
                'radius_meters'           => $settings->radius_meters,
                'allow_employee_override' => $settings->allow_employee_override,
            ],
        ]);
    }

    /**
     * GET /api/v1/smart-attendance/employees/{id}/preference
     * Manager lit la préférence d'un employé.
     */
    public function employeePreference(int $employeeId): JsonResponse
    {
        $company = currentCompany();

        $pref = EmployeeAttendancePreference::query()
            ->where('employee_id', $employeeId)
            ->where('company_id', $company->id)
            ->first();

        return response()->json([
            'data' => $pref ? [
                'preferred_mode'    => $pref->preferred_mode,
                'gps_consent_given' => $pref->gps_consent_given,
            ] : null,
        ]);
    }
}
