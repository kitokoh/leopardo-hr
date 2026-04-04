# STRATÉGIE MULTITENANCY — LEOPARDO RH
# Version 3.0 | Mars 2026 (REMPLACE v2.0 — contradictions supprimées)

---

## RÈGLE D'OR (À LIRE EN PREMIER)

> **Il existe deux modes de tenancy. Les deux coexistent dans le code.
> Le comportement est déterminé par `companies.tenancy_type` (valeur: `schema` ou `shared`).
> Le `TenantMiddleware` centralise TOUTE la logique — aucun Controller ni Model
> ne doit connaître le mode de tenancy.**

---

## 1. LES DEUX MODES

### Mode `shared` (Starter / Business / Trial)
- Les données de TOUTES les entreprises shared sont dans le schéma `shared_tenants`
- L'isolation est **logique** : une colonne `company_id` est présente sur chaque table
- Un **Global Scope Laravel** injecte automatiquement `WHERE company_id = ?` sur chaque query
- Le `search_path` PostgreSQL pointe vers `shared_tenants`

### Mode `schema` (Enterprise uniquement)
- Chaque entreprise a son propre schéma PostgreSQL : `company_{uuid_court}`
- L'isolation est **physique** : aucune donnée d'autres entreprises n'existe dans ce schéma
- **Aucun `company_id`** sur les tables (inutile — le schéma est déjà isolant)
- Le `search_path` PostgreSQL pointe vers `company_{uuid}`

---

## 2. IMPLÉMENTATION — TenantMiddleware

```php
// app/Http/Middleware/TenantMiddleware.php

namespace App\Http\Middleware;

use App\Models\Public\Company;
use App\Models\Public\UserLookup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }

        // Lookup rapide : email → company_id (évite de scanner tous les schémas)
        $lookup = UserLookup::where('email', $user->email)->firstOrFail();
        $company = Company::findOrFail($lookup->company_id);

        // Suspendre le compte expiré
        if (in_array($company->status, ['suspended', 'expired'])) {
            return response()->json(['error' => 'ACCOUNT_SUSPENDED'], 403);
        }

        // Injecter la company dans le request (accessible partout)
        $request->merge(['_company' => $company]);
        app()->instance('current_company', $company);

        if ($company->tenancy_type === 'schema') {
            // ─── MODE SCHEMA (Enterprise) ─────────────────────────────────
            // Isolation physique PostgreSQL — aucun Global Scope nécessaire
            DB::statement("SET search_path TO {$company->schema_name}, public");

        } else {
            // ─── MODE SHARED (Starter / Business / Trial) ─────────────────
            // Isolation logique — Global Scope injecte WHERE company_id automatiquement
            DB::statement("SET search_path TO shared_tenants, public");

        }

        return $next($request);
    }
}
```

---

## 3. TRAIT UNIQUE POUR LE MODE SHARED (CORRIGE)

```php
// app/Traits/BelongsToCompany.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        // Scope automatique en mode shared
        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->bound('current_company')) {
                $builder->where('company_id', app('current_company')->id);
            }
        });

        // Injection automatique de company_id à la création
        static::creating(function ($model) {
            if (app()->bound('current_company') && empty($model->company_id)) {
                $company = app('current_company');
                if ($company->tenancy_type === 'shared') {
                    $model->company_id = $company->id;
                }
            }
        });
    }
}
```

Ce design elimine le bug du double boot (`bootHasCompanyScope` + `bootHasCompanyId`).

---

## 4. MODÈLES TENANT — UTILISATION DU TRAIT

```php
// app/Models/Tenant/Employee.php

namespace App\Models\Tenant;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use BelongsToCompany;  // obligatoire sur tous les modeles Tenant

    protected $table = 'employees';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'matricule', 'role', 'manager_role',
        'department_id', 'position_id', 'schedule_id',
        'salary_base', 'iban', 'contract_type',
        'hire_date', 'contract_end_date',
        'leave_balance', 'photo_path',
        'status', 'password_hash',  // corrige v4.1.4 : is_active -> status (voir SQL)
        // company_id : présent en DB shared, ignoré en mode schema
    ];

    protected $hidden = ['password_hash', 'iban'];

    protected $casts = [
        'hire_date'         => 'date',
        'contract_end_date' => 'date',
        'salary_base'       => 'decimal:2',
        'leave_balance'     => 'decimal:1',
        'status'            => 'string',   // corrige v4.1.4 : 'active'|'suspended'|'archived'
    ];
}
```

---

## 5. RÈGLE POUR LES CONTROLLERS (non ambiguë)

```php
// ✅ TOUJOURS faire comme ça — fonctionne dans les DEUX modes
Employee::all();
Employee::where('department_id', $deptId)->get();
Employee::create(['first_name' => 'Ahmed', ...]);

// ✅ En mode schema : le schéma isole. En mode shared : le Global Scope filtre.
// Le Controller NE SAIT PAS dans quel mode il tourne — c'est le but.

// ❌ INTERDIT dans un Controller ou Model
Employee::where('company_id', $someId)->get();  // Le scope s'en occupe déjà
DB::statement("SET search_path TO ...");         // Le middleware s'en occupe déjà
```

---

## 6. MIGRATION — SHARED VERS SCHEMA (Upgrade Enterprise)

```php
// app/Services/TenantMigrationService.php
// VERSION ROBUSTE — Transactions + rollback + zero-downtime (Semaine 8+)

class TenantMigrationService
{
    /**
     * Migre une entreprise de shared → schema dédié (upgrade Enterprise).
     * ATOMIC : rollback complet si échec à n'importe quelle étape.
     * ZERO-DOWNTIME : l'entreprise reste accessible en shared pendant la copie.
     */
    public function migrateSharedToSchema(Company $company): void
    {
        // Ordre critique des tables (respecter les FK)
        $tables = [
            'departments', 'positions', 'schedules', 'sites',
            'employees', 'devices', 'attendance_logs',
            'absence_types', 'absences', 'leave_balance_logs',
            'salary_advances', 'projects', 'tasks', 'task_comments',
            'evaluations', 'payrolls', 'payroll_export_batches',
            'company_settings', 'audit_logs', 'notifications',
        ];

        $schemaName = 'company_' . substr(str_replace('-', '', $company->id), 0, 8);
        $backupCreated = false;

        try {
            // ÉTAPE 1 : Backup de sécurité (snapshot avant migration)
            $backupPath = storage_path("backups/pre_migration_{$company->id}_" . now()->format('Ymd_His') . ".dump");
            exec("pg_dump -Fc -U leopardo_user -n shared_tenants leopardo_db > {$backupPath}");
            $backupCreated = true;
            Log::info("TenantMigration: backup créé {$backupPath}");

            // ÉTAPE 2 : Créer le nouveau schéma + tables (idempotent)
            DB::statement("SET search_path TO public");
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schemaName}");
            DB::statement("SET search_path TO {$schemaName}, public");
            Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
            Log::info("TenantMigration: schéma {$schemaName} créé");

            // ÉTAPE 3 : Copie des données en TRANSACTION (batchs de 500)
            DB::beginTransaction();

            foreach ($tables as $table) {
                $offset = 0;
                $batchSize = 500;
                do {
                    $rows = DB::select("
                        SELECT * FROM shared_tenants.{$table}
                        WHERE company_id = ?
                        LIMIT {$batchSize} OFFSET {$offset}
                    ", [$company->id]);

                    if (!empty($rows)) {
                        // Insérer sans company_id (schéma dédié = isolé physiquement)
                        $data = array_map(fn($row) => (array) $row, $rows);
                        foreach ($data as &$row) { unset($row['company_id']); }
                        DB::table("{$schemaName}.{$table}")->insert($data);
                    }
                    $offset += $batchSize;
                } while (count($rows) === $batchSize);

                Log::info("TenantMigration: table {$table} copiée");
            }

            // ÉTAPE 4 : Mettre à jour la company (encore en shared — pas encore basculée)
            DB::table('public.companies')->where('id', $company->id)->update([
                'tenancy_type' => 'schema',
                'schema_name'  => $schemaName,
                'updated_at'   => now(),
            ]);

            DB::commit();
            Log::info("TenantMigration: company {$company->id} basculée vers {$schemaName}");

            // ÉTAPE 5 : Purge des données shared APRÈS commit (RGPD)
            // On attend 24h avant de supprimer (sécurité)
            PurgeTenantSharedDataJob::dispatch($company->id)->delay(now()->addHours(24));

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("TenantMigration FAILED pour {$company->id}: " . $e->getMessage());

            // Nettoyer le schéma partiellement créé
            try {
                DB::statement("DROP SCHEMA IF EXISTS {$schemaName} CASCADE");
            } catch (\Throwable $cleanupErr) {
                Log::error("TenantMigration cleanup failed: " . $cleanupErr->getMessage());
            }

            // Notifier le Super Admin
            Notification::route('mail', config('app.super_admin_email'))
                ->notify(new TenantMigrationFailedNotification($company, $e->getMessage(), $backupPath ?? null));

            throw $e; // Re-throw pour que l'appelant gère l'erreur UI
        }
    }
}
```
---

## 7. SCHÉMA DE LA BASE DE DONNÉES

```
leopardo_db (PostgreSQL 16)
│
├── public                          ← Schéma partagé toute plateforme
│   ├── plans
│   ├── companies
│   ├── super_admins
│   ├── user_lookups                ← email → company_id (lookup rapide auth)
│   ├── invoices
│   ├── billing_transactions
│   ├── languages
│   └── hr_model_templates
│
├── shared_tenants                  ← Toutes les PME Starter/Business/Trial
│   ├── employees          (+ company_id)
│   ├── attendance_logs    (+ company_id)
│   ├── absences           (+ company_id)
│   ├── payrolls           (+ company_id)
│   └── ... (20 tables, toutes avec company_id)
│
├── company_a1b2c3d4               ← Entreprise Enterprise A (schéma dédié)
│   ├── employees          (sans company_id)
│   ├── attendance_logs
│   └── ...
│
└── company_e5f6g7h8               ← Entreprise Enterprise B
    └── ...
```

---

## 8. TABLE user_lookups (critique pour la performance auth)

```sql
-- Dans le schéma public
-- CORRIGE v4.1.4 : aligne sur 07_SCHEMA_SQL_COMPLET.sql (email = PK, pas id SERIAL)
CREATE TABLE user_lookups (
    email       VARCHAR(150) PRIMARY KEY,
    company_id  UUID         NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    schema_name VARCHAR(63)  NOT NULL,
    employee_id INT          NOT NULL,
    role        VARCHAR(20)  NOT NULL
);

CREATE INDEX idx_user_lookups_company ON user_lookups(company_id);
```

**Pourquoi ?**
Sans cette table, pour authentifier un utilisateur il faudrait scanner TOUS les schémas.
Avec elle : 1 query sur `public.user_lookups` → on connaît le company_id → on sait quel schéma switcher.
