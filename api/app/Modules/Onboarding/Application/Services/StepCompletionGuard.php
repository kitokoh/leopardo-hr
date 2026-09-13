<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\UserInvitation;

/**
 * #7261 — vérifie qu'une étape d'onboarding correspond à une action RÉELLE
 * avant de la marquer « completed ».
 *
 * Sans cette garde, le bouton « Suivant » de l'assistant marquait n'importe
 * quelle étape terminée : constaté en live, `first_employee` passait à
 * `completed` sans qu'aucun employé n'ait été ajouté, ce qui rendait
 * `progress_percent` et `go_live_ready` faux — et rendait la mise en route
 * réelle d'un client non mesurable.
 *
 * Périmètre volontairement limité aux étapes dont le **signal serveur existe** :
 * l'absence de prédicat renvoie `null`, c'est-à-dire « étape déclarative » — on
 * ne bloque jamais un client sur une étape non mesurable.
 *
 * Restent déclaratives à ce jour :
 *   - `company_info` : rien n'est persisté lors de cette étape ;
 *   - `first_attendance` : le modèle de pointage n'est pas identifié dans ce BC ;
 *   - `first_report` : aucun compteur de rapport ;
 *   - `configure_schedules` : le modèle vit dans le BC Planning, et un import
 *     croisé `Onboarding → Planning` serait un NOUVEL import inter-modules,
 *     interdit par `dev-hub/tools/check-module-isolation.sh`. À traiter par un
 *     contrat partagé, pas par un import direct.
 *
 * Les prédicats réutilisent **à l'identique** ceux du moteur de checklist
 * calculé (`OnboardingChecklistController`) afin de ne pas créer une deuxième
 * vérité sur ce qu'est une étape « faite ».
 */
final class StepCompletionGuard
{
    /**
     * @return bool|null `true` = action constatée, `false` = action absente,
     *                   `null` = étape sans prédicat (déclarative).
     */
    public function isSatisfied(string $companyId, string $stepKey): ?bool
    {
        return match ($stepKey) {
            'first_department' => Department::query()
                ->where('company_id', $companyId)
                ->exists(),

            // Le manager lui-même est un employé : « ajouter le premier
            // employé » ne peut être vrai qu'à partir du deuxième.
            'first_employee' => Employee::query()
                ->where('company_id', $companyId)
                ->count() > 1,

            'invite_manager' => UserInvitation::query()
                ->where('company_id', $companyId)
                ->exists(),

            'configure_payroll' => Employee::query()
                ->where('company_id', $companyId)
                ->where(function ($query): void {
                    $query
                        ->where('salary_base', '>', 0)
                        ->orWhere('hourly_rate', '>', 0);
                })
                ->exists(),

            'install_kiosk' => AttendanceKiosk::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->exists(),

            'activate_geofence' => $this->isGeofenceConfigured($companyId),

            default => null,
        };
    }

    /**
     * Même définition que la checklist calculée (`geofence_configured`) : une
     * zone est configurée si la société porte des coordonnées ET un rayon > 0.
     */
    private function isGeofenceConfigured(string $companyId): bool
    {
        $geofence = Company::query()->find($companyId)?->metadata['attendance_geofence'] ?? null;

        if (! is_array($geofence)) {
            return false;
        }

        return isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])
            && is_numeric($geofence['radius_meters'])
            && (float) $geofence['radius_meters'] > 0;
    }
}
