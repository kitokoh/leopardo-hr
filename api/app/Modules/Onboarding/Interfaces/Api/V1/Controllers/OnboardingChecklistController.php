<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Http\JsonResponse;

/**
 * Checklist d'onboarding calculée — moteur de référence (#3239).
 *
 * Shape canonique de la checklist (les deux endpoints doivent l'exposer) :
 *   data: {
 *     completed_steps: int,
 *     total_steps: int,
 *     progress_percent: int,   // champ canonique
 *     progress: int,           // alias rétrocompatible (moteur DB)
 *     go_live_ready: bool,
 *     next_actions: [{ key, label }],
 *     steps: [...],
 *   }
 *
 * Lecture ouverte à tout utilisateur authentifié du tenant (les données
 * restent scopées à sa société par le middleware tenant) ; les écritures
 * (complete/skip) restent gouvernées par OnboardingStepController.
 */
class OnboardingChecklistController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var Employee|null $actor */
        $actor = request()->user();

        // #3239 — plus de 403 : tout utilisateur authentifié du tenant peut
        // lire sa propre checklist (remplace l'ancien authorize viewAny
        // réservé aux managers). Garde simple : un utilisateur authentifié
        // est forcément un Employee (auth:sanctum + middleware tenant).
        abort_if(! $actor instanceof Employee, 401);

        $company = currentCompany();

        $employeesCount = Employee::query()->where('company_id', $company->id)->count();
        $activeEmployeesCount = Employee::query()->where('company_id', $company->id)->where('status', 'active')->count();
        $biometricReadyCount = Employee::query()
            ->where('company_id', $company->id)
            ->where(function ($query): void {
                $query
                    ->where('biometric_face_enabled', true)
                    ->orWhere('biometric_fingerprint_enabled', true);
            })
            ->count();
        $payrollReadyCount = Employee::query()
            ->where('company_id', $company->id)
            ->where(function ($query): void {
                $query
                    ->where('salary_base', '>', 0)
                    ->orWhere('hourly_rate', '>', 0);
            })
            ->count();
        $kioskCount = AttendanceKiosk::query()->where('company_id', $company->id)->where('status', 'active')->count();
        $geofence = $company->metadata['attendance_geofence'] ?? null;
        $geofenceConfigured = is_array($geofence)
            && isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])
            && (float) $geofence['radius_meters'] > 0;

        // #R15 — required=true pour les étapes essentielles au go-live ;
        // optional pour kiosk/geofence/biometrie (équipements spécifiques).
        $steps = [
            $this->step('company_created',   'Societe creee',              true,  required: true),
            $this->step('manager_active',    'Manager principal actif',    $actor->role === 'manager', required: true),
            $this->step('employees_added',   'Equipe ajoutee',             $employeesCount >= 2,       required: true, metrics: ['employees_count' => $employeesCount]),
            $this->step('employees_active',  'Comptes employes actives',   $activeEmployeesCount >= max(1, $employeesCount), required: true, metrics: ['active_employees_count' => $activeEmployeesCount]),
            $this->step('payroll_ready',     'Bases de paie renseignees',  $employeesCount > 0 && $payrollReadyCount >= $employeesCount, required: false, metrics: ['payroll_ready_count' => $payrollReadyCount]),
            $this->step('geofence_configured','Zone de pointage configuree',$geofenceConfigured,       required: false),
            $this->step('biometrics_ready',  'Biometrie configuree',       $biometricReadyCount > 0,  required: false, metrics: ['biometric_ready_count' => $biometricReadyCount]),
            $this->step('kiosk_connected',   'Kiosque ou borne connecte',  $kioskCount > 0,           required: false, metrics: ['kiosk_count' => $kioskCount]),
        ];

        $completed = collect($steps)->where('completed', true)->count();
        $percent = (int) round(($completed / count($steps)) * 100);

        // #R15 — go_live_ready : toutes les étapes requises doivent être complétées.
        $allRequiredDone = collect($steps)
            ->filter(fn (array $s): bool => $s['required'] === true)
            ->every(fn (array $s): bool => $s['completed'] === true);

        $nextActions = collect($steps)
            ->where('completed', false)
            ->take(3)
            ->map(fn (array $step): array => [
                'key' => $step['key'],
                'label' => $step['label'],
            ])
            ->values();

        return new JsonResponse([
            'data' => [
                'completed_steps' => $completed,
                'total_steps' => count($steps),
                'progress_percent' => $percent,
                'progress' => $percent,
                'go_live_ready' => $allRequiredDone,
                'next_actions' => $nextActions,
                'steps' => $steps,
            ],
        ]);
    }

    // #R15 — `required` ajouté pour go_live_ready pondéré.
    private function step(string $key, string $label, bool $completed, bool $required = true, array $metrics = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'completed' => $completed,
            'required' => $required,
            'metrics' => (object) $metrics,
        ];
    }
}

