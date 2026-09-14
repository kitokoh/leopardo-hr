<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceAnomalyService;
use App\Modules\Onboarding\Application\Services\OnboardingProgressReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformCompanyHealthService
{
    public function __construct(
        private readonly AttendanceAnomalyService $anomalyService,
        private readonly OnboardingProgressReader $onboardingProgress,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * Portefeuille de sociétés avec leur santé (#7302).
     *
     * AVANT : `build()` était appelé en boucle — ~15 requêtes par société
     * (dont 3 `SET search_path` et un `Company::find` redondant dans les
     * anomalies), soit **674 requêtes** mesurées pour 45 sociétés. À ~40 ms
     * l'aller-retour sur la base distante, cela explique à lui seul les ~27 s
     * constatées en production (#7302).
     *
     * MAINTENANT : les agrégats de tout le portefeuille sont calculés par
     * requêtes **groupées** (`group by company_id`). Les valeurs sont
     * identiques à celles de `build()` : mêmes prédicats, même fenêtre par
     * société. Le nombre de requêtes ne dépend plus du nombre de tenants.
     *
     * Note sur le `search_path` : tous les tenants partagent le schéma
     * `shared_tenants` (le mode « un schéma par tenant » est verrouillé — voir
     * `Company::booted()`), il n'y a donc qu'un seul `search_path` à poser pour
     * tout le portefeuille.
     *
     * @return array<string, mixed>
     */
    /**
     * Durée de mise en cache du portefeuille (#7302).
     *
     * La santé est une donnée **dérivée** (agrégats de pointage, anomalies,
     * progression) : elle n'a pas besoin d'être exacte à la seconde, et un
     * back-office commercial interrogé en rafale (ouverture de plusieurs
     * onglets, rafraîchissement automatique) ne doit pas recalculer N sociétés
     * à chaque requête.
     *
     * Invalidation : **temporelle** (TTL court). Il n'y a pas d'invalidation à
     * l'écriture — une fiche société modifiée peut rester jusqu'à 60 s dans le
     * portefeuille. C'est un choix assumé : le portefeuille est un tableau de
     * bord de supervision, pas une source transactionnelle, et une invalidation
     * à l'écriture exigerait de câbler chaque écriture de chaque module.
     * `Cache::forget()` force un recalcul immédiat au besoin (bouton
     * « Actualiser »).
     */
    private const PORTFOLIO_CACHE_TTL_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function portfolio(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        /** @var array<string, mixed> $result */
        $result = Cache::remember(
            self::portfolioCacheKey($limit),
            self::PORTFOLIO_CACHE_TTL_SECONDS,
            fn (): array => $this->buildPortfolio($limit),
        );

        return $result;
    }

    /**
     * Purge le cache du portefeuille (#7302).
     *
     * Exposé pour que l'action « Actualiser » du back-office demande un
     * recalcul réel au lieu de resservir une valeur mise en cache jusqu'à
     * `PORTFOLIO_CACHE_TTL_SECONDS`.
     */
    public function forgetPortfolioCache(int $limit = 50): void
    {
        Cache::forget(self::portfolioCacheKey($limit));
    }

    /**
     * Clé de cache du portefeuille — normalisée au même bornage que
     * `portfolio()`, pour que purge et lecture ne puissent pas diverger.
     */
    private static function portfolioCacheKey(int $limit): string
    {
        return 'platform.companies.health.limit.'.max(1, min(100, $limit));
    }

    /**
     * Calcul effectif du portefeuille (hors cache) — voir `portfolio()`.
     *
     * @return array<string, mixed>
     */
    private function buildPortfolio(int $limit): array
    {
        $companies = Company::query()
            ->latest()
            ->limit(max(1, min(100, $limit)))
            ->get();

        if ($companies->isEmpty()) {
            return [
                'data' => [
                    'summary' => [
                        'companies' => 0,
                        'active_companies' => 0,
                        'mrr' => 0.0,
                        'risk' => ['high' => 0, 'medium' => 0, 'low' => 0],
                    ],
                    'items' => [],
                ],
            ];
        }

        /** @var list<string> $companyIds */
        /** @var list<string> $companyIds */
        $companyIds = array_values($companies->pluck('id')->map(static fn ($id): string => (string) $id)->all());

        $employees = [];
        $onboarding = [];
        $plans = [];
        $attendance = [];
        $anomalies = [];
        $attendanceExists = [];

        $this->withinPortfolioSearchPath(function () use (
            $companies,
            $companyIds,
            &$employees,
            &$onboarding,
            &$plans,
            &$attendance,
            &$anomalies,
            &$attendanceExists
        ): void {
            // Indépendants de la fenêtre : une requête chacun pour TOUT le
            // portefeuille.
            $employees = $this->employeesMany($companyIds);
            $onboarding = $this->onboardingProgress->readMany($companyIds);
            $plans = $this->plansMany($companies);
            $attendanceExists = $this->attendanceExistsMany($companyIds);

            // Dépendants de la fenêtre de 30 jours — et cette fenêtre est
            // calculée dans le fuseau de CHAQUE société (`now($company->timezone)`),
            // comme `build()`. On groupe donc par fenêtre distincte : en
            // pratique une seule, mais un fuseau différent ne doit pas fausser
            // les compteurs.
            foreach ($this->groupByWindow($companies) as $group) {
                $dateFrom = $group['date_from'];
                $dateTo = $group['date_to'];
                /** @var list<string> $groupIds */
                $groupIds = array_values($group['companies']->pluck('id')->map(static fn ($id): string => (string) $id)->all());

                foreach ($this->attendanceMany($groupIds, $dateFrom, $dateTo) as $id => $row) {
                    $attendance[$id] = $row;
                }

                foreach ($this->anomalyService->summarizeMany($group['companies'], [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'per_page' => 1,
                ]) as $id => $summary) {
                    $anomalies[$id] = [
                        'total_30d' => $summary['total'],
                        'critical_30d' => $summary['critical'],
                        'warning_30d' => $summary['warning'],
                        'business_impact' => $summary['business_impact'] ?? (object) [],
                    ];
                }
            }
        });

        $items = $companies
            ->map(function (Company $company) use ($employees, $onboarding, $plans, $attendance, $anomalies, $attendanceExists): array {
                $id = (string) $company->id;

                $companyEmployees = $employees[$id] ?? ['total' => 0, 'active' => 0, 'payroll_ready' => 0];
                $companyAttendance = $attendance[$id] ?? [
                    'logs_30d' => 0,
                    'active_employees_30d' => 0,
                    'active_days_30d' => 0,
                    'last_punch_at' => null,
                ];
                $companyAnomalies = $anomalies[$id] ?? [
                    'total_30d' => 0,
                    'critical_30d' => 0,
                    'warning_30d' => 0,
                    'business_impact' => (object) [],
                ];
                // Un tenant sans étape seedée renvoie la progression vide — on
                // ne seede JAMAIS depuis le portefeuille (#7300). La mise en
                // forme passe par `onboardingPayload()` : le portefeuille
                // fournit donc les mêmes clés que `build()` (dont
                // `geofence_configured`, sans quoi `nextActions()` proposait de
                // configurer une zone déjà configurée).
                $companyOnboarding = $this->onboardingPayload(
                    $company,
                    $onboarding[$id] ?? $this->onboardingProgress->summarize(new Collection),
                    $companyEmployees,
                    $attendanceExists[$id] ?? false,
                );

                $plan = $this->planPayload($company, $plans[(string) $company->plan_id] ?? null);
                $now = now($company->timezone);
                $score = $this->score($company, $companyEmployees, $companyAttendance, $companyOnboarding, $companyAnomalies);

                return [
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'slug' => $company->slug,
                        'status' => $company->status,
                        'country' => $company->country,
                        'currency' => $company->currency,
                        'timezone' => $company->timezone,
                        'created_at' => $company->created_at?->toIso8601String(),
                    ],
                    'plan' => $plan,
                    'subscription' => $this->subscriptionPayload($company, $now, $plan),
                    'health_score' => $score,
                    'risk_level' => $this->riskLevel($score, $company),
                    'employees_active' => $companyEmployees['active'],
                    'attendance_logs_30d' => $companyAttendance['logs_30d'],
                    'last_punch_at' => $companyAttendance['last_punch_at'],
                    'critical_anomalies_30d' => $companyAnomalies['critical_30d'],
                    'next_action' => $this->nextActions($score, $company, $companyEmployees, $companyAttendance, $companyOnboarding, $companyAnomalies)[0] ?? null,
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
     * Applique un `search_path` unique pour tout le portefeuille (#7302).
     *
     * Tous les tenants partagent le schéma `shared_tenants` (le mode
     * « un schéma par tenant » est refusé à la création — `Company::booted()`),
     * il n'est donc pas nécessaire de changer de schéma par société.
     *
     * @param  callable(): void  $callback
     */
    private function withinPortfolioSearchPath(callable $callback): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $callback();

            return;
        }

        $searchPathRow = DB::selectOne('SHOW search_path');
        $previous = is_object($searchPathRow) && property_exists($searchPathRow, 'search_path')
            ? (string) $searchPathRow->search_path
            : 'public';
        DB::statement('SET search_path TO "shared_tenants",public');

        try {
            $callback();
        } finally {
            DB::statement("SET search_path TO {$previous}");
        }
    }

    /**
     * Groupe les sociétés par fenêtre d'observation de 30 jours, calculée dans
     * le fuseau de chacune (comme `build()`).
     *
     * @param  Collection<int, Company>  $companies
     * @return list<array{date_from: string, date_to: string, companies: Collection<int, Company>}>
     */
    private function groupByWindow(Collection $companies): array
    {
        $groups = [];

        foreach ($companies as $company) {
            $now = now($company->timezone);
            $dateTo = $now->toDateString();
            $dateFrom = $now->copy()->subDays(29)->toDateString();
            $groups[$dateFrom.'|'.$dateTo]['date_from'] = $dateFrom;
            $groups[$dateFrom.'|'.$dateTo]['date_to'] = $dateTo;
            $groups[$dateFrom.'|'.$dateTo]['companies'][] = $company;
        }

        return array_values(array_map(static fn (array $group): array => [
            'date_from' => $group['date_from'],
            'date_to' => $group['date_to'],
            'companies' => new Collection($group['companies']),
        ], $groups));
    }

    /**
     * Compteurs d'employés pour N sociétés — une requête (#7302).
     *
     * Mêmes prédicats que `employees()` : `count(*)`, `status = 'active'`, et
     * « prêt pour la paie » (`salary_base` ou `hourly_rate` > 0).
     *
     * @param  list<string>  $companyIds
     * @return array<string, array{total: int, active: int, payroll_ready: int}>
     */
    private function employeesMany(array $companyIds): array
    {
        $rows = DB::table('employees')
            ->whereIn('company_id', $companyIds)
            ->groupBy('company_id')
            ->select([
                'company_id',
                DB::raw('count(*) as total'),
                DB::raw("count(*) filter (where status = 'active') as active"),
                DB::raw('count(*) filter (where coalesce(salary_base, 0) > 0 or coalesce(hourly_rate, 0) > 0) as payroll_ready'),
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->company_id] = [
                'total' => (int) $row->total,
                'active' => (int) $row->active,
                'payroll_ready' => (int) $row->payroll_ready,
            ];
        }

        return $result;
    }

    /**
     * Agrégats de pointage sur 30 jours pour N sociétés — deux requêtes (#7302).
     *
     * Mêmes prédicats que `attendance()` : `whereBetween('date', [$from, $to])`
     * (bornes « jour », volontairement identiques à `build()` pour que le
     * portefeuille et la fiche société annoncent le même chiffre), et le
     * dernier pointage sur l'historique complet.
     *
     * `active_days_30d` compte des JOURS distincts : la colonne `date` est
     * stockée en timestamp, on la caste donc en date avant `distinct` — sinon
     * deux pointages du même jour à des heures différentes compteraient double.
     *
     * @param  list<string>  $companyIds
     * @return array<string, array{logs_30d: int, active_employees_30d: int, active_days_30d: int, last_punch_at: string|null}>
     */
    private function attendanceMany(array $companyIds, string $dateFrom, string $dateTo): array
    {
        $aggregates = DB::table('attendance_logs')
            ->whereIn('company_id', $companyIds)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->groupBy('company_id')
            ->select([
                'company_id',
                DB::raw('count(*) as logs_30d'),
                DB::raw('count(distinct employee_id) as active_employees_30d'),
                DB::raw('count(distinct (date)::date) as active_days_30d'),
            ])
            ->get();

        $lastPunches = DB::table('attendance_logs')
            ->whereIn('company_id', $companyIds)
            ->whereNotNull('check_in')
            ->groupBy('company_id')
            ->select([
                'company_id',
                DB::raw('max(check_in) as last_punch_at'),
            ])
            ->get();

        $result = [];
        foreach ($aggregates as $row) {
            $result[(string) $row->company_id] = [
                'logs_30d' => (int) $row->logs_30d,
                'active_employees_30d' => (int) $row->active_employees_30d,
                'active_days_30d' => (int) $row->active_days_30d,
                'last_punch_at' => null,
            ];
        }

        foreach ($lastPunches as $row) {
            $id = (string) $row->company_id;
            $result[$id] ??= [
                'logs_30d' => 0,
                'active_employees_30d' => 0,
                'active_days_30d' => 0,
                'last_punch_at' => null,
            ];
            $result[$id]['last_punch_at'] = $row->last_punch_at === null
                ? null
                : Carbon::parse((string) $row->last_punch_at)->setTimezone(config('app.timezone'))->toIso8601String();
        }

        return $result;
    }

    /**
     * Sociétés ayant AU MOINS un pointage, toutes périodes confondues — une
     * requête (#7302).
     *
     * Alimente l'observation serveur (`observed`) du volet onboarding : dans
     * `build()` cette information coûtait un `exists()` par société.
     *
     * @param  list<string>  $companyIds
     * @return array<string, bool>
     */
    private function attendanceExistsMany(array $companyIds): array
    {
        $ids = DB::table('attendance_logs')
            ->whereIn('company_id', $companyIds)
            ->distinct()
            ->pluck('company_id');

        $result = [];
        foreach ($ids as $id) {
            $result[(string) $id] = true;
        }

        return $result;
    }

    /**
     * Plans référencés par le portefeuille — une requête (#7302).
     *
     * `subscription()` rappelait `plan()` (donc `select * from plans where id = ?`)
     * deux fois par société dans l'ancien `portfolio()`.
     *
     * @param  Collection<int, Company>  $companies
     * @return array<string, object>
     */
    private function plansMany(Collection $companies): array
    {
        $planIds = $companies->pluck('plan_id')->filter()->unique()->values()->all();

        if ($planIds === []) {
            return [];
        }

        $result = [];
        foreach (DB::table('plans')->whereIn('id', $planIds)->get() as $plan) {
            $result[(string) $plan->id] = $plan;
        }

        return $result;
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
                        'slug' => $company->slug,
                        'status' => $company->status,
                        'country' => $company->country,
                        'currency' => $company->currency,
                        'timezone' => $company->timezone,
                        'created_at' => $company->created_at?->toIso8601String(),
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
     * Volet « onboarding » du bloc `adoption` du back-office.
     *
     * #7300 — la progression affichée est désormais la progression CANONIQUE
     * (table `onboarding_steps`), la même que celle du client : le back-office
     * ne peut plus annoncer « ONBOARDING 40 % — RISK HIGH » à un client dont
     * l'assistant affiche « Configuration terminée ». Les faits observés côté
     * serveur (équipe active, bases de paie, géofence, premier pointage)
     * restent exposés sous `observed` — c'est de l'**adoption**, pas de
     * l'onboarding, et les deux ne doivent pas être confondus.
     *
     * @param  array<string, mixed>  $employees
     * @return array<string, mixed>
     */
    private function onboarding(Company $company, array $employees): array
    {
        // Source de vérité (identique à /onboarding-setup/checklist).
        $canonical = $this->onboardingProgress->read($company->id);

        $hasAnyAttendance = AttendanceLog::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->exists();

        return $this->onboardingPayload($company, $canonical, $employees, $hasAnyAttendance);
    }

    /**
     * Mise en forme du volet onboarding/adoption (#7302).
     *
     * Extrait pour que `build()` (une société) et `portfolio()` (N sociétés)
     * produisent EXACTEMENT la même structure — c'est précisément l'absence de
     * source commune qui avait laissé `nextActions()` lire une clé que le
     * portefeuille ne fournissait pas.
     *
     * @param  array<string, mixed>  $canonical  progression canonique (`OnboardingProgressReader`)
     * @param  array<string, mixed>  $employees
     * @return array<string, mixed>
     */
    private function onboardingPayload(Company $company, array $canonical, array $employees, bool $hasAnyAttendance): array
    {
        $geofence = $company->metadata['attendance_geofence'] ?? null;
        $geofenceConfigured = is_array($geofence)
            && isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])
            && (float) $geofence['radius_meters'] > 0;

        // Observation serveur — sert au score d'adoption, jamais d'échelle
        // d'onboarding.
        $observedCompleted = collect([
            true,
            (int) $employees['active'] > 0,
            (int) $employees['payroll_ready'] >= max(1, (int) $employees['total']),
            $geofenceConfigured,
            $hasAnyAttendance,
        ])->filter()->count();
        $observedTotal = 5;

        return [
            // ── Progression canonique (setup), partagée avec le client
            'initialized' => $canonical['initialized'],
            'completed_steps' => $canonical['completed_steps'],
            'total_steps' => $canonical['total_steps'],
            'progress_percent' => $canonical['progress_percent'],
            'go_live_ready' => $canonical['go_live_ready'],
            'next_actions' => $canonical['next_actions'],
            'source' => 'onboarding_steps',
            // ── Adoption observée (≠ onboarding)
            'observed' => [
                'completed_steps' => $observedCompleted,
                'total_steps' => $observedTotal,
                'progress_percent' => (int) round(($observedCompleted / $observedTotal) * 100),
            ],
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
        /** @var object{name?: string, price_monthly?: numeric-string|float, price_yearly?: numeric-string|float}|null $plan */
        $plan = DB::table('plans')->where('id', $company->plan_id)->first();

        return $this->planPayload($company, $plan);
    }

    /**
     * Mise en forme d'un plan déjà chargé (#7302).
     *
     * Le portefeuille charge tous les plans en une requête puis passe ici
     * l'objet correspondant, au lieu d'un `select * from plans` par société.
     *
     * @param  object{name?: string, price_monthly?: numeric-string|float, price_yearly?: numeric-string|float}|null  $plan
     * @return array<string, mixed>
     */
    private function planPayload(Company $company, ?object $plan): array
    {
        return [
            'id' => $company->plan_id,
            'name' => is_object($plan) && isset($plan->name) ? (string) $plan->name : null,
            'price_monthly' => is_object($plan) && isset($plan->price_monthly) ? (float) $plan->price_monthly : null,
            'price_yearly' => is_object($plan) && isset($plan->price_yearly) ? (float) $plan->price_yearly : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscription(Company $company, Carbon $now): array
    {
        return $this->subscriptionPayload($company, $now, $this->plan($company));
    }

    /**
     * Mise en forme d'un abonnement à partir d'un plan déjà chargé (#7302).
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function subscriptionPayload(Company $company, Carbon $now, array $plan): array
    {
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
     * @param  array<string, mixed>  $employees
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $onboarding
     * @param  array<string, mixed>  $anomalies
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
        // #7300 — le malus « onboarding inachevé » se juge sur la SOURCE DE
        // VÉRITÉ (go_live_ready), plus sur une échelle parallèle : un client
        // qui a terminé sa configuration n'est plus pénalisé pour un
        // onboarding « à 40 % » calculé autrement. L'absence d'usage réel
        // reste captée par le malus `logs_30d` ci-dessus.
        if ($onboarding['go_live_ready'] !== true) {
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
     * @param  array<string, mixed>  $employees
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $onboarding
     * @param  array<string, mixed>  $anomalies
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
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withTenantSearchPath(Company $company, callable $callback): array // @phpstan-ignore callable.nonCallable
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $callback();
        }

        $searchPathRow = DB::selectOne('SHOW search_path');
        $previous = is_object($searchPathRow) && property_exists($searchPathRow, 'search_path')
            ? (string) $searchPathRow->search_path
            : 'public';
        DB::statement('SET search_path TO '.$company->getSafeSearchPath());

        try {
            return $callback();
        } finally {
            DB::statement("SET search_path TO {$previous}");
        }
    }
}
