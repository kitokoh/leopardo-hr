<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\ClientEvent;
use App\Models\CommunicationEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LaunchReadinessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Employee || ! $actor->company_id || ! ($actor->isPrincipal() || $actor->isHr())) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $company = app()->bound('current_company') ? currentCompany() : null;
        if (! $company instanceof Company || (string) $company->id !== (string) $actor->company_id) {
            $company = Company::query()->find((string) $actor->company_id);
        }

        if (! $company instanceof Company) {
            return response()->json([
                'message' => 'Company context unavailable.',
            ], 404);
        }

        $companyId = (string) $company->id;
        $activeEmployees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();
        $activeManagers = Employee::query()
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->where('status', 'active')
            ->count();
        $payrollReadyQuery = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active');

        if (Schema::hasColumn('employees', 'salary_base') || Schema::hasColumn('employees', 'hourly_rate')) {
            $payrollReadyQuery->where(function ($query): void {
                $hasCondition = false;

                if (Schema::hasColumn('employees', 'salary_base')) {
                    $query->where('salary_base', '>', 0);
                    $hasCondition = true;
                }

                if (Schema::hasColumn('employees', 'hourly_rate')) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $query->{$method}('hourly_rate', '>', 0);
                }
            });
            $payrollReady = $payrollReadyQuery->count();
        } else {
            $payrollReady = 0;
        }
        $preferencesConfigured = NotificationPreference::query()
            ->where('company_id', $companyId)
            ->count();
        $communicationFailures7d = CommunicationEvent::query()
            ->where('company_id', $companyId)
            ->where('status', 'failed')
            ->where('occurred_at', '>=', now()->subDays(7))
            ->count();
        $clientEvents7d = ClientEvent::query()
            ->where('company_id', $companyId)
            ->where('occurred_at', '>=', now()->subDays(7))
            ->count();
        $kiosksConnected = AttendanceKiosk::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();
        $geofenceConfigured = $this->geofenceConfigured($company);

        $checks = [
            $this->check('company_profile', 'Profil entreprise complet', true, $company->status === 'active'
                && $company->name !== ''
                && $company->email !== ''
                && $company->country !== ''
                && $company->timezone !== '', [
                    'status' => $company->status,
                    'country' => $company->country,
                    'timezone' => $company->timezone,
                ]),
            $this->check('manager_access', 'Acces manager/RH actif', true, $activeManagers > 0, [
                'active_managers' => $activeManagers,
            ]),
            $this->check('employee_base', 'Base collaborateurs exploitable', true, $activeEmployees >= 2, [
                'active_employees' => $activeEmployees,
            ]),
            $this->check('communication_governance', 'Preferences et audit communication prets', true, $preferencesConfigured >= $activeEmployees && $communicationFailures7d === 0, [
                'preferences_configured' => $preferencesConfigured,
                'communication_failures_7d' => $communicationFailures7d,
            ]),
            $this->check('payroll_base', 'Donnees de paie minimales renseignees', false, $activeEmployees > 0 && $payrollReady >= $activeEmployees, [
                'payroll_ready_employees' => $payrollReady,
            ]),
            $this->check('attendance_entry', 'Pointage terrain pret', false, $geofenceConfigured || $kiosksConnected > 0, [
                'geofence_configured' => $geofenceConfigured,
                'active_kiosks' => $kiosksConnected,
            ]),
            $this->check('client_experience_tracking', 'Experience client instrumentee', false, $clientEvents7d > 0, [
                'client_events_7d' => $clientEvents7d,
            ]),
        ];

        $completed = collect($checks)->where('completed', true)->count();
        $requiredIncomplete = collect($checks)->where('required', true)->where('completed', false)->values();
        $score = (int) round(($completed / count($checks)) * 100);

        return response()->json([
            'data' => [
                'company_id' => $companyId,
                'score' => $score,
                'go_live_ready' => $requiredIncomplete->isEmpty() && $score >= 70,
                'required_blockers' => $requiredIncomplete->map(fn (array $check): array => [
                    'key' => $check['key'],
                    'label' => $check['label'],
                    'metrics' => $check['metrics'],
                ])->all(),
                'next_actions' => collect($checks)
                    ->where('completed', false)
                    ->take(4)
                    ->map(fn (array $check): array => [
                        'key' => $check['key'],
                        'label' => $check['label'],
                        'required' => $check['required'],
                    ])
                    ->values()
                    ->all(),
                'checks' => $checks,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{key: string, label: string, required: bool, completed: bool, metrics: object}
     */
    private function check(string $key, string $label, bool $required, bool $completed, array $metrics = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required,
            'completed' => $completed,
            'metrics' => (object) $metrics,
        ];
    }

    private function geofenceConfigured(Company $company): bool
    {
        $metadata = $company->metadata ?? [];
        $geofence = is_array($metadata) ? ($metadata['attendance_geofence'] ?? null) : null;

        return is_array($geofence)
            && isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])
            && (float) $geofence['radius_meters'] > 0;
    }
}
