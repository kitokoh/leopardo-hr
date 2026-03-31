# SPÉCIFICATION TenantService — LEOPARDO RH
# Version 1.0 | Mars 2026
# Service : app/Services/TenantService.php
# Appelé par : POST /admin/companies (Super Admin)

---

## MISSION

TenantService orchestre la création complète d'une nouvelle entreprise cliente.
Les 7 étapes s'exécutent dans une **transaction PostgreSQL unique** :
si une étape échoue, tout est rollback — jamais d'état partiel en production.

---

## IMPLÉMENTATION COMPLÈTE

```php
// app/Services/TenantService.php

namespace App\Services;

use App\Models\Public\Company;
use App\Models\Public\UserLookup;
use App\Models\Public\HrModelTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantService
{
    public function createTenant(array $data): Company
    {
        return DB::transaction(function () use ($data) {

            // ─── ÉTAPE 1 : Créer l'enregistrement dans public.companies ───────
            $company = Company::create([
                'name'               => $data['name'],
                'slug'               => Str::slug($data['name']) . '-' . Str::random(4),
                'sector'             => $data['sector'],
                'country'            => $data['country'],
                'city'               => $data['city'],
                'email'              => $data['email'],
                'plan_id'            => $data['plan_id'],
                'tenancy_type'       => $data['tenancy_type'] ?? 'shared',
                'schema_name'        => 'company_' . Str::replace('-', '_', Str::uuid()),
                'status'             => 'trial',
                'subscription_start' => now(),
                'subscription_end'   => now()->addDays(14), // trial
                'language'           => $data['language'] ?? 'fr',
                'timezone'           => $data['timezone'] ?? 'Africa/Algiers',
                'currency'           => $data['currency'] ?? 'DZD',
            ]);

            // ─── ÉTAPE 2 : Créer le schéma PostgreSQL (si Enterprise) ─────────
            if ($company->tenancy_type === 'schema') {
                DB::statement("SELECT create_tenant_schema('{$company->schema_name}')");
                // La fonction PostgreSQL crée toutes les tables (voir 07_SCHEMA_SQL_COMPLET.sql)
            } else {
                // Mode shared : les tables existent déjà dans shared_tenants
                // Passer en mode shared_tenants pour les inserts suivants
                DB::statement("SET search_path TO shared_tenants, public");
            }

            // ─── ÉTAPE 3 : Appliquer le modèle RH du pays ────────────────────
            $hrModel = HrModelTemplate::where('country_code', $data['country'])->first();
            if ($hrModel) {
                $settings = [
                    ['key' => 'hr_model',              'value' => $data['country']],
                    ['key' => 'payroll.cotisations',   'value' => json_encode($hrModel->cotisations)],
                    ['key' => 'payroll.ir_brackets',   'value' => json_encode($hrModel->ir_brackets)],
                    ['key' => 'payroll.overtime_rate_1', 'value' => '1.25'],
                    ['key' => 'payroll.overtime_rate_2', 'value' => '1.50'],
                    ['key' => 'payroll.penalty_mode',  'value' => 'proportional'],
                    ['key' => 'onboarding_completed',  'value' => 'false'],
                ];

                foreach ($settings as $setting) {
                    DB::table('company_settings')->insert(
                        array_merge($setting, ['company_id' => $company->id])
                    );
                }
            }

            // ─── ÉTAPE 4 : Types d'absence standards ─────────────────────────
            $absenceTypes = [
                ['name' => 'Congé annuel',    'code' => 'annual',    'is_paid' => true,  'deducts_leave' => true],
                ['name' => 'Congé maladie',   'code' => 'sick',      'is_paid' => true,  'deducts_leave' => false],
                ['name' => 'Congé maternité', 'code' => 'maternity', 'is_paid' => true,  'deducts_leave' => false],
                ['name' => 'Absence injustifiée', 'code' => 'unjustified', 'is_paid' => false, 'deducts_leave' => false],
            ];

            foreach ($absenceTypes as $type) {
                DB::table('absence_types')->insert(
                    array_merge($type, ['company_id' => $company->id])
                );
            }

            // ─── ÉTAPE 5 : Planning de travail par défaut ─────────────────────
            $scheduleId = DB::table('schedules')->insertGetId([
                'company_id'               => $company->id,
                'name'                     => 'Planning standard',
                'start_time'               => '08:00:00',
                'end_time'                 => '17:00:00',
                'break_minutes'            => 60,
                'work_days'                => json_encode([1, 2, 3, 4, 5]), // Lun-Ven
                'late_tolerance_minutes'   => 15,
                'overtime_threshold_daily' => 8.0,
                'is_default'               => true,
            ]);

            // ─── ÉTAPE 6 : Créer le premier gestionnaire (Manager Principal) ──
            $manager = DB::table('employees')->insertGetId([
                'company_id'    => $company->id,
                'matricule'     => 'MGR-001',
                'first_name'    => $data['manager_first_name'],
                'last_name'     => $data['manager_last_name'],
                'email'         => $data['manager_email'],
                'password_hash' => Hash::make($data['manager_password']),
                'role'          => 'manager',
                'manager_role'  => 'principal',
                'schedule_id'   => $scheduleId,
                'contract_type' => 'CDI',
                'contract_start'=> now(),
                'salary_base'   => 0,
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // ─── ÉTAPE 6b : Enregistrer dans user_lookups (schéma public) ─────
            DB::statement("SET search_path TO public");
            UserLookup::create([
                'email'       => $data['manager_email'],
                'company_id'  => $company->id,
                'employee_id' => $manager,
                'role'        => 'manager',
            ]);

            // ─── ÉTAPE 7 : Envoyer l'email de bienvenue ───────────────────────
            // (via Job pour ne pas bloquer la transaction)
            dispatch(new \App\Jobs\SendWelcomeEmail($company, $data['manager_email'], $data['manager_password']));

            return $company;
        });
        // Si une exception est levée à n'importe quelle étape → rollback complet automatique
    }

    /**
     * Purger un tenant expiré (appelé par CheckExpiredSubscriptions après délai)
     */
    public function purgeExpiredTenant(Company $company): void
    {
        DB::transaction(function () use ($company) {
            if ($company->tenancy_type === 'schema') {
                // Suppression physique du schéma PostgreSQL
                DB::statement("DROP SCHEMA IF EXISTS {$company->schema_name} CASCADE");
            } else {
                // Suppression logique : toutes les tables shared avec company_id
                $tables = ['audit_logs', 'notifications', 'payrolls', 'evaluations',
                           'tasks', 'task_comments', 'projects', 'salary_advances',
                           'absences', 'leave_balance_logs', 'attendance_logs',
                           'employee_devices', 'employees', 'schedules', 'sites',
                           'departments', 'positions', 'company_settings'];

                DB::statement("SET search_path TO shared_tenants, public");
                foreach ($tables as $table) {
                    DB::table($table)->where('company_id', $company->id)->delete();
                }
                DB::statement("SET search_path TO public");
            }

            // Supprimer user_lookups (schéma public)
            UserLookup::where('company_id', $company->id)->delete();

            // Supprimer la company
            $company->delete();
        });
    }
}
```

---

## TESTS OBLIGATOIRES

```php
// tests/Feature/TenantService/CreateTenantTest.php

it('creates a complete tenant in a single transaction', function () {
    $data = [
        'name'                 => 'TestCorp SPA',
        'sector'               => 'construction',
        'country'              => 'DZ',
        'city'                 => 'Alger',
        'email'                => 'contact@testcorp.dz',
        'plan_id'              => Plan::where('name', 'Business')->first()->id,
        'tenancy_type'         => 'shared',
        'language'             => 'fr',
        'manager_first_name'   => 'Karim',
        'manager_last_name'    => 'Bensalem',
        'manager_email'        => 'karim@testcorp.dz',
        'manager_password'     => 'SecurePass123!',
    ];

    $company = app(TenantService::class)->createTenant($data);

    // Vérifications
    expect($company->status)->toBe('trial');
    expect(UserLookup::where('email', 'karim@testcorp.dz')->exists())->toBeTrue();

    DB::statement("SET search_path TO shared_tenants, public");
    expect(DB::table('employees')->where('email', 'karim@testcorp.dz')->exists())->toBeTrue();
    expect(DB::table('company_settings')->where('company_id', $company->id)->count())->toBeGreaterThan(3);
    expect(DB::table('absence_types')->where('company_id', $company->id)->count())->toBe(4);
    expect(DB::table('schedules')->where('company_id', $company->id)->count())->toBe(1);
});

it('rolls back everything if manager email already exists', function () {
    UserLookup::create(['email' => 'exists@test.dz', 'company_id' => Str::uuid(), 'employee_id' => 1, 'role' => 'manager']);

    expect(fn() => app(TenantService::class)->createTenant([
        'manager_email' => 'exists@test.dz',
        // ...
    ]))->toThrow(\Exception::class);

    // Aucune company ne doit avoir été créée
    expect(Company::where('email', 'contact@test.dz')->exists())->toBeFalse();
});
```
