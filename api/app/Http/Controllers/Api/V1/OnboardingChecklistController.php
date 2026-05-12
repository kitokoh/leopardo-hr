<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class OnboardingChecklistController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var Employee $actor */
        $actor = request()->user();
        $company = currentCompany();

        $this->authorize('viewAny', Employee::class);

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

        $steps = [
            $this->step('company_created', 'Societe creee', true),
            $this->step('manager_active', 'Manager principal actif', $actor->role === 'manager'),
            $this->step('employees_added', 'Equipe ajoutee', $employeesCount >= 2, ['employees_count' => $employeesCount]),
            $this->step('employees_active', 'Comptes employes actives', $activeEmployeesCount >= max(1, $employeesCount), ['active_employees_count' => $activeEmployeesCount]),
            $this->step('payroll_ready', 'Bases de paie renseignees', $employeesCount > 0 && $payrollReadyCount >= $employeesCount, ['payroll_ready_count' => $payrollReadyCount]),
            $this->step('geofence_configured', 'Zone de pointage configuree', $geofenceConfigured),
            $this->step('biometrics_ready', 'Biometrie configuree', $biometricReadyCount > 0, ['biometric_ready_count' => $biometricReadyCount]),
            $this->step('kiosk_connected', 'Kiosque ou borne connecte', $kioskCount > 0, ['kiosk_count' => $kioskCount]),
        ];

        $completed = collect($steps)->where('completed', true)->count();
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
                'progress_percent' => (int) round(($completed / count($steps)) * 100),
                'go_live_ready' => $completed >= count($steps) - 1,
                'next_actions' => $nextActions,
                'steps' => $steps,
            ],
        ]);
    }

    private function step(string $key, string $label, bool $completed, array $metrics = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'completed' => $completed,
            'metrics' => (object) $metrics,
        ];
    }
}
