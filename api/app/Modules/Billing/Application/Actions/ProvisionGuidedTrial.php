<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Events\CompanyCreated;
use App\Support\CountryDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProvisionGuidedTrial
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly SolutionActivator $solutionActivator,
    ) {}

    /**
     * MULTI-PAYS (#1867/#1950) : le pays légal est OBLIGATOIRE et doit être
     * supporté (registre CountryDefaults) — aucun fallback silencieux vers DZ
     * (invariant 10 de la spec MULTI_PAYS_RULES_ENGINE). La langue, la
     * devise et le fuseau sont dérivés du pays validé.
     *
     * @param  list<string>  $solutions  Codes de solutions sectorielles demandées (#6693)
     * @param  string|null  $companyType  #7235 — `company` (défaut) | `solo`
     * @param  list<string>  $modules  #7235 — outils horizontaux choisis à l'inscription
     * @return array<string, mixed>
     */
    public function execute(
        string $email,
        string $companyName,
        ?string $country = null,
        array $solutions = [],
        ?string $companyType = null,
        array $modules = [],
    ): array {
        // BC-25 (#6693) : les solutions demandées doivent exister au catalogue
        // (fail-closed) AVANT tout provisioning — jamais de tenant partiel.
        $solutions = array_values(array_unique(array_map(
            static fn (mixed $code): string => strtolower(trim((string) $code)),
            $solutions,
        )));

        // #3600 : idempotence — un retry de job (tries/backoff) ou une double
        // soumission ne doit jamais créer un second tenant sandbox pour le
        // même email. Le provisioning est transactionnel, mais une erreur
        // transitoire APRÈS le commit (statut, magic link) déclencherait sinon
        // une création dupliquée au retry.
        $existing = Company::query()
            ->where('email', $email)
            ->where('status', 'trial')
            ->where('metadata->provisioned_by', 'guided_trial')
            ->first();

        if ($existing instanceof Company) {
            $this->tenantManager->setTenant($existing);
            try {
                $manager = Employee::query()->where('email', $email)->first();
            } finally {
                $this->tenantManager->resetToPrevious();
            }

            if ($manager instanceof Employee) {
                Log::info('Guided trial : tenant sandbox existant réutilisé', ['company_id' => $existing->id, 'email' => $email]);

                return [
                    'success' => true,
                    'company' => $existing,
                    'manager' => $manager,
                ];
            }

            // Entreprise existante sans manager (provisioning interrompu) :
            // on poursuit la création du manager sous ce tenant.
            Log::warning('Guided trial : company existante sans manager, re-provisioning', ['company_id' => $existing->id, 'email' => $email]);
        }

        $slug = Str::slug($companyName);
        if (! $slug) {
            $slug = 'sandbox-'.Str::random(6);
        }

        // #7235 — Profil d'activité + sélection explicite des outils
        // horizontaux. `modules = []` (inscription rapide, appelants
        // historiques) ⇒ AUCUNE sélection écrite : les tenants existants et
        // les inscriptions sans choix gardent le comportement d'avant.
        $companyType = $companyType === Company::TYPE_SOLO ? Company::TYPE_SOLO : Company::TYPE_COMPANY;
        $moduleSelection = $this->resolveModuleSelection($modules, $companyType);

        $countryDefaults = CountryDefaults::find($country);
        if ($countryDefaults === null) {
            throw new \InvalidArgumentException('Le pays du tenant est obligatoire et doit être supporté ('.implode(', ', array_column(CountryDefaults::all(), 'country')).').');
        }

        return DB::transaction(function () use ($email, $companyName, $slug, $countryDefaults, $solutions, $companyType, $moduleSelection): array {
            $company = Company::query()->create([
                'name' => $companyName,
                'slug' => $slug,
                'sector' => 'Non précisé',
                'country' => $countryDefaults['country'],
                'city' => 'Non précisé',
                'email' => $email,
                'plan_id' => $this->resolveTrialPlanId(),
                'schema_name' => 'shared_tenants',
                'tenancy_type' => 'shared',
                'status' => 'trial',
                'subscription_start' => now()->toDateString(),
                'subscription_end' => now()->addDays($this->trialDays())->toDateString(),
                'language' => strtolower($countryDefaults['language']),
                'timezone' => $countryDefaults['timezone'],
                'currency' => strtoupper($countryDefaults['currency']),
                // #7235 — profil d'activité, métier vertical et outils
                // choisis. `modules` reste ABSENT quand aucune sélection n'a
                // été déclarée (le front retombe alors sur son comportement
                // historique, aucun verrouillage rétroactif).
                'metadata' => array_filter(
                    [
                        'provisioned_by' => 'guided_trial',
                        'is_sandbox' => true,
                        'company_type' => $companyType,
                        'vertical' => $solutions[0] ?? null,
                        'modules' => $moduleSelection,
                    ],
                    static fn (mixed $value): bool => $value !== null,
                ),
                // #7235 — les clés de la sélection qui sont AUSSI des feature
                // flags plateforme (registre `config/feature-flags.php`) sont
                // miroirées dans `features` pour être résolues par
                // `FeatureFlag::for()` (donc visibles dans /auth/me).
                'features' => $this->mirroredFeatures($moduleSelection),
            ]);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
            }
            $this->tenantManager->setTenant($company);

            try {
                /** @var Employee $manager */
                $manager = new Employee([
                    'first_name' => 'Manager',
                    'last_name' => 'Sandbox',
                    'email' => $email,
                    'contract_type' => 'CDI',
                    'contract_start' => now()->toDateString(),
                    'salary_type' => 'fixed',
                    'biometric_face_enabled' => false,
                    'biometric_fingerprint_enabled' => false,
                    'extra_data' => [
                        'job_title' => 'Manager principal',
                        'guided_trial' => true,
                    ],
                ]);
                // Issue #5161 : `password_hash` est NOT NULL sans défaut dans le
                // schéma tenant. Il doit être posé dans le MÊME INSERT (pattern
                // #3677/#4151, cf. VerifyTrialSignup) — un `create()` sans lui
                // échoue en SQLSTATE 23502 avant que l'update post-hoc ne puisse
                // s'exécuter (régression #4558, non couverte par le fix #4947).
                $manager->forceFill([
                    'company_id' => $company->id,
                    'password_hash' => Hash::make(Str::random(16)),
                    'role' => 'manager',
                    'manager_role' => 'principal',
                    'status' => 'active',
                    'salary_base' => 0,
                ])->save();

                // Basic Seeding to make it look active
                $this->seedBasicSandboxData($company->id, $manager->id);

                // BC-25 (#6693) : activation des solutions sectorielles
                // demandées à l'inscription (idempotente, auditée
                // « solution.activated » + « solution.dependencies_activated »,
                // modules requis du pack activés — fail-closed : une solution
                // inconnue annule le provisioning, rollback complet).
                // DANS le contexte tenant (audit_logs est une table tenant) —
                // avant le resetToPrevious du finally.
                foreach ($solutions as $solutionCode) {
                    $this->solutionActivator->activateWithDependencies($company, $solutionCode);
                }

            } finally {
                $this->tenantManager->resetToPrevious();
            }

            event(new CompanyCreated($company));

            return [
                'success' => true,
                'company' => $company,
                'manager' => $manager,
            ];
        });
    }

    /**
     * #7235 — Sélection explicite des outils horizontaux, normalisée :
     * toutes les clés de `Company::HORIZONTAL_TOOLS` sont présentes, avec
     * `true` pour les outils choisis et `false` pour les autres. Un profil
     * `solo` voit en plus les outils d'ÉQUIPE forcés à `false` — la règle est
     * posée côté serveur, elle ne dépend pas du client.
     *
     * @param  list<string>  $modules
     * @return array<string, bool>|null null quand aucune sélection n'a été fournie
     */
    private function resolveModuleSelection(array $modules, string $companyType): ?array
    {
        if ($modules === []) {
            return null;
        }

        $requested = [];

        foreach ($modules as $module) {
            $key = strtolower(trim((string) $module));

            if ($key !== '' && in_array($key, Company::HORIZONTAL_TOOLS, true)) {
                $requested[$key] = true;
            }
        }

        $selection = [];

        foreach (Company::HORIZONTAL_TOOLS as $tool) {
            $selection[$tool] = isset($requested[$tool]);
        }

        if ($companyType === Company::TYPE_SOLO) {
            foreach (Company::TEAM_TOOLS as $tool) {
                $selection[$tool] = false;
            }
        }

        return $selection;
    }

    /**
     * #7235 — Miroir des outils choisis vers `features` pour les clés qui
     * existent réellement dans le registre des feature flags
     * (`config/feature-flags.php`). Les autres clés (employees, attendance…)
     * ne sont PAS des flags plateforme : elles vivent dans
     * `metadata.modules`, que le client web consomme directement.
     *
     * @param  array<string, bool>|null  $selection
     * @return array<string, bool>
     */
    private function mirroredFeatures(?array $selection): array
    {
        if ($selection === null) {
            return [];
        }

        $platformFlags = ['accounting', 'crm'];
        $features = [];

        foreach ($platformFlags as $flag) {
            if (array_key_exists($flag, $selection)) {
                $features[$flag] = $selection[$flag];
            }
        }

        return $features;
    }

    private function resolveTrialPlanId(): int
    {
        /** @var object{id: int}|null $plan */
        $plan = DB::table('plans')->where('is_active', true)->first();
        if ($plan) {
            return $plan->id;
        }

        return DB::table('plans')->insertGetId([
            'name' => 'Sandbox Plan',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'max_employees' => 50,
            'features' => json_encode(['rh' => true, 'tasks' => true, 'attendance' => true, 'mobile_apps' => true]),
            'trial_days' => $this->trialDays(),
            'is_active' => true,
        ]);
    }

    private function seedBasicSandboxData(string $companyId, int $managerId): void
    {
        // 1. Department
        $deptId = DB::table('shared_tenants.departments')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Opérations',
            'manager_id' => $managerId,
            'created_at' => now(),
        ]);

        // 2. Schedule
        $scheduleId = DB::table('shared_tenants.schedules')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Standard 8h-17h',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_days' => json_encode([1, 2, 3, 4, 5]),
            'is_default' => true,
            'created_at' => now(),
        ]);

        // 3. Fake Employee — réservé aux environnements démo explicites
        // (DEMO_MODE_ENABLED=true) : jamais de compte `alice@demo.local`/
        // `password` sur le chemin de trial public (Constitution §V).
        // #6958 : `employees_email_unique` est GLOBAL (migration
        // 2026_04_17_000105, email unique toutes sociétés confondues du
        // schéma partagé) → un email fixe `alice@demo.local` ne peut être
        // seedé qu'UNE fois par environnement. Sans garde, le 2e trial guidé
        // (et tous les suivants) échouait en SQLSTATE 23505 pendant le
        // provisioning → statut `failed` (#6958, constaté DEV 2026-09-09 :
        // 12 jobs échoués identiques). Alice n'est référencée par aucun autre
        // code : si elle existe déjà, on saute le seed (département/horaire du
        // nouveau tenant restent créés — seuls alice + sa trace sont omis).
        if (config('app.demo_mode_enabled')
            && ! DB::table('shared_tenants.employees')->where('email', 'alice@demo.local')->exists()) {
            $empId = DB::table('shared_tenants.employees')->insertGetId([
                'company_id' => $companyId,
                'matricule' => 'EMP-001',
                'first_name' => 'Alice',
                'last_name' => 'Dupont',
                'email' => 'alice@demo.local',
                'password_hash' => Hash::make('password'),
                'role' => 'employee',
                'department_id' => $deptId,
                'schedule_id' => $scheduleId,
                'manager_id' => $managerId,
                'contract_type' => 'CDI',
                'salary_base' => 100000,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('public.user_lookups')->insert([
                'email' => 'alice@demo.local',
                'company_id' => $companyId,
                'schema_name' => 'shared_tenants',
                'employee_id' => $empId,
                'role' => 'employee',
            ]);

            // 4. Attendance log
            DB::table('shared_tenants.attendance_logs')->insert([
                'company_id' => $companyId,
                'employee_id' => $empId,
                'date' => now()->format('Y-m-d'),
                'session_number' => 1,
                'check_in' => now()->setTime(8, 0, 0)->toIso8601String(),
                'method' => 'mobile',
                'status' => 'ontime',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function trialDays(): int
    {
        $days = config('billing.trial_days');

        return \is_int($days) ? $days : 14;
    }
}
