<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
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
    ) {}

    /**
     * MULTI-PAYS (#1867/#1950) : le pays légal est OBLIGATOIRE et doit être
     * supporté (registre CountryDefaults) — aucun fallback silencieux vers DZ
     * (invariant 10 de la spec MULTI_PAYS_RULES_ENGINE). La langue, la
     * devise et le fuseau sont dérivés du pays validé.
     *
     * @return array<string, mixed>
     */
    public function execute(string $email, string $companyName, ?string $country = null): array
    {
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

        $countryDefaults = CountryDefaults::find($country);
        if ($countryDefaults === null) {
            throw new \InvalidArgumentException('Le pays du tenant est obligatoire et doit être supporté ('.implode(', ', array_column(CountryDefaults::all(), 'country')).').');
        }

        return DB::transaction(function () use ($email, $companyName, $slug, $countryDefaults): array {
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
                'subscription_end' => now()->addDays((int) config('billing.trial_days'))->toDateString(),
                'language' => strtolower($countryDefaults['language']),
                'timezone' => $countryDefaults['timezone'],
                'currency' => strtoupper($countryDefaults['currency']),
                'metadata' => [
                    'provisioned_by' => 'guided_trial',
                    'is_sandbox' => true,
                ],
            ]);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
            }
            $this->tenantManager->setTenant($company);

            try {
                // Prepare every NOT NULL field before the initial INSERT.
                // PostgreSQL rejects creating the employee first and assigning
                // password_hash only afterwards (#4978).
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
                // Sensitive fields are deliberately assigned explicitly
                // (not mass-assignable, #3677/#4496).
                $manager->forceFill([
                    'company_id' => $company->id,
                    'role' => 'manager',
                    'manager_role' => 'principal',
                    'status' => 'active',
                    'salary_base' => 0,
                    'password_hash' => Hash::make(Str::random(16)),
                ])->save();

                // Basic Seeding to make it look active
                $this->seedBasicSandboxData($company->id, $manager->id);

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

    private function resolveTrialPlanId(): int
    {
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
            'trial_days' => (int) config('billing.trial_days'),
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
        if (config('app.demo_mode_enabled')) {
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
}
