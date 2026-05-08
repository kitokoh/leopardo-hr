<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformCompanyHealthService
{
    public function __construct(
        private readonly AttendanceAnomalyService $anomalyService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function portfolio(int $limit = 50): array
    {
        $companies = Company::query()
            ->latest()
            ->limit(max(1, min(100, $limit)))
            ->get();

        $items = $companies
            ->map(function (Company $company): array {
                $health = $this->build($company)['data'];

                return [
                    'company' => $health['company'],
                    'plan' => $health['plan'],
                    'subscription' => $health['subscription'],
                    'health_score' => $health['adoption']['health_score'],
                    'risk_level' => $health['adoption']['risk_level'],
                    'employees_active' => $health['adoption']['employees']['active'],
                    'attendance_logs_30d' => $health['adoption']['attendance']['logs_30d'],
                    'last_punch_at' => $health['adoption']['attendance']['last_punch_at'],
                    'critical_anomalies_30d' => $health['adoption']['anomalies']['critical_30d'],
                    'next_action' => $health['next_actions'][0] ?? null,
                ];
            })
            ->values();

        return [
            'data' => [
                'summary' => [
                    'companies' => $items->count(),
                    'active_companies' => $items->where('company.status', 'active')->count(),
                    'mrr' => round((float) $items->sum(fn (array $item): float => (float) ($item['subscription']['mrr'] ?? 0)), 2),
                    'risk' => [
                        'high' => $items->where('risk_level', 'high')->count(),
                        'medium' => $items->where('risk_level', 'medium')->count(),
                        'low' => $items->where('risk_level', 'low')->count(),
                    ],
                ],
                'items' => $items,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Company $company): array
    {
        return $this->withTenantSearchPath($company, function () use ($company): array {
            $now = now($company->timezone);
            $dateTo = $now->toDateString();
            $dateFrom = $now->copy()->subDays(29)->toDateString();

            $employees = $this->employees($company);
            $attendance = $this->attendance($company, $dateFrom, $dateTo);
            $onboarding = $this->onboarding($company, $employees);
            $anomalies = $this->anomalies($company, $dateFrom, $dateTo);
            $score = $this->score($company, $employees, $attendance, $onboarding, $anomalies);

            return [
                'data' => [
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'status' => $company->status,
                        'country' => $company->country,
                        'currency' => $company->currency,
                        'timezone' => $company->timezone,
                    ],
                    'plan' => $this->plan($company),
                    'features' => [
                        'active' => FeatureFlag::for($company),
                        'known_modules' => Company::KNOWN_MODULES,
                    ],
                    'period' => [
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'days' => 30,
                    ],
                    'subscription' => $this->subscription($company, $now),
                    'adoption' => [
                        'health_score' => $score,
                        'risk_level' => $this->riskLevel($score, $company),
                        'employees' => $employees,
                        'attendance' => $attendance,
                        'onboarding' => $onboarding,
                        'anomalies' => $anomalies,
                    ],
                    'next_actions' => $this->nextActions($score, $company, $employees, $attendance, $onboarding, $anomalies),
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function employees(Company $company): array
    {
        $total = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count();
        $active = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->count();
        $payrollReady = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($query): void {
                $query
                    ->where('salary_base', '>', 0)
                    ->orWhere('hourly_rate', '>', 0);
            })
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'payroll_ready' => $payrollReady,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendance(Company $company, string $dateFrom, string $dateTo): array
    {
        $logs = AttendanceLog::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get(['id', 'employee_id', 'date', 'check_in']);

        $lastPunch = AttendanceLog::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotNull('check_in')
            ->orderByDesc('check_in')
            ->value('check_in');

        return [
            'logs_30d' => $logs->count(),
            'active_employees_30d' => $logs->pluck('employee_id')->unique()->count(),
            'active_days_30d' => $logs
                ->pluck('date')
                ->map(fn ($date): string => $date instanceof Carbon ? $date->toDateString() : (string) $date)
                ->unique()
                ->count(),
            'last_punch_at' => $lastPunch instanceof Carbon ? $lastPunch->toIso8601String() : $lastPunch,
        ];
    }

    /**
     * @param array<string, mixed> $employees
     * @return array<string, mixed>
     */
    private function onboarding(Company $company, array $employees): array
    {
        $geofence = $company->metadata['attendance_geofence'] ?? null;
        $geofenceConfigured = is_array($geofence)
            && isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])
            && (float) $geofence['radius_meters'] > 0;

        $completed = collect([
            true,
            (int) $employees['active'] > 0,
            (int) $employees['payroll_ready'] >= max(1, (int) $employees['total']),
            $geofenceConfigured,
            AttendanceLog::withoutGlobalScopes()->where('company_id', $company->id)->exists(),
        ])->filter()->count();
        $total = 5;

        return [
            'completed_steps' => $completed,
            'total_steps' => $total,
            'progress_percent' => (int) round(($completed / $total) * 100),
            'geofence_configured' => $geofenceConfigured,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function anomalies(Company $company, string $dateFrom, string $dateTo): array
    {
        $summary = $this->anomalyService->summarize($company->id, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => 1,
        ])['data']['summary'];

        return [
            'total_30d' => $summary['total'],
            'critical_30d' => $summary['critical'],
            'warning_30d' => $summary['warning'],
            'business_impact' => $summary['business_impact'] ?? (object) [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(Company $company): array
    {
        $plan = DB::table('plans')->where('id', $company->plan_id)->first();

        return [
            'id' => $company->plan_id,
            'name' => $plan->name ?? null,
            'price_monthly' => isset($plan->price_monthly) ? (float) $plan->price_monthly : null,
            'price_yearly' => isset($plan->price_yearly) ? (float) $plan->price_yearly : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscription(Company $company, Carbon $now): array
    {
        $plan = $this->plan($company);
        $end = $company->subscription_end ? Carbon::parse($company->subscription_end, $company->timezone) : null;

        return [
            'mrr' => $plan['price_monthly'],
            'currency' => $company->currency,
            'subscription_start' => $company->subscription_start
                ? Carbon::parse($company->subscription_start, $company->timezone)->toDateString()
                : null,
            'subscription_end' => $company->subscription_end
                ? Carbon::parse($company->subscription_end, $company->timezone)->toDateString()
                : null,
            'days_until_renewal' => $end ? $now->diffInDays($end, false) : null,
        ];
    }

    /**
     * @param array<string, mixed> $employees
     * @param array<string, mixed> $attendance
     * @param array<string, mixed> $onboarding
     * @param array<string, mixed> $anomalies
     */
    private function score(Company $company, array $employees, array $attendance, array $onboarding, array $anomalies): int
    {
        $score = 100;

        if ($company->status !== 'active') {
            $score -= 40;
        }
        if ((int) $employees['active'] === 0) {
            $score -= 25;
        }
        if ((int) $attendance['logs_30d'] === 0) {
            $score -= 30;
        }
        if ((int) $onboarding['progress_percent'] < 80) {
            $score -= 15;
        }

        $score -= min(20, (int) $anomalies['critical_30d'] * 5);

        return max(0, min(100, $score));
    }

    private function riskLevel(int $score, Company $company): string
    {
        if ($company->status !== 'active' || $score < 50) {
            return 'high';
        }

        if ($score < 75) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param array<string, mixed> $employees
     * @param array<string, mixed> $attendance
     * @param array<string, mixed> $onboarding
     * @param array<string, mixed> $anomalies
     * @return list<array{key: string, label: string, priority: string}>
     */
    private function nextActions(int $score, Company $company, array $employees, array $attendance, array $onboarding, array $anomalies): array
    {
        $actions = [];

        if ($company->status !== 'active') {
            $actions[] = ['key' => 'reactivate_subscription', 'label' => 'Verifier le statut abonnement avant relance client.', 'priority' => 'high'];
        }
        if ((int) $employees['active'] === 0) {
            $actions[] = ['key' => 'activate_team', 'label' => 'Activer au moins un employe pour demarrer le pointage.', 'priority' => 'high'];
        }
        if ((int) $attendance['logs_30d'] === 0) {
            $actions[] = ['key' => 'start_attendance', 'label' => 'Planifier une session de demarrage pointage avec le manager.', 'priority' => 'high'];
        }
        if (! (bool) $onboarding['geofence_configured']) {
            $actions[] = ['key' => 'configure_geofence', 'label' => 'Configurer une zone de pointage pour prouver la presence terrain.', 'priority' => 'medium'];
        }
        if ((int) $anomalies['critical_30d'] > 0) {
            $actions[] = ['key' => 'review_critical_anomalies', 'label' => 'Traiter les anomalies critiques avant la cloture paie.', 'priority' => 'high'];
        }
        if ($score >= 80) {
            $actions[] = ['key' => 'prepare_upsell', 'label' => 'Client sain : proposer rapport avance, kiosque ou module Business.', 'priority' => 'medium'];
        }

        return array_slice($actions, 0, 4);
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function withTenantSearchPath(Company $company, callable $callback): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $callback();
        }

        $previous = DB::selectOne('SHOW search_path')->search_path ?? 'public';
        DB::statement('SET search_path TO '.$company->getSafeSearchPath());

        try {
            return $callback();
        } finally {
            DB::statement("SET search_path TO {$previous}");
        }
    }
}
