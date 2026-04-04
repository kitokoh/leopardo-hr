# MVP-01 — Init Laravel + Multitenancy Shared + DB
# Agent : Claude Code
# Durée : 4-6 heures
# Prérequis : Aucun (première tâche)

---

## CE QUE TU FAIS

Créer le projet Laravel 11 dans `api/`, configurer PostgreSQL, écrire le système multitenancy mode shared (Global Scope), créer les migrations du schéma public, et valider que l'isolation inter-tenant fonctionne.

---

## AVANT DE COMMENCER

1. Lis `PILOTAGE.md` à la racine du projet (5 min)
2. Lis `docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` — section SCHÉMA PUBLIC uniquement (tables: plans, companies, super_admins, user_lookups, invoices, billing_transactions, languages, hr_model_templates)
3. Lis `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` — section mode shared uniquement

---

## ÉTAPES (dans l'ordre)

### 1. Créer le projet Laravel

```bash
cd api/
composer create-project laravel/laravel . "11.*"
composer require laravel/sanctum
composer require barryvdh/laravel-dompdf
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

> ⚠️ Ne PAS installer : Horizon, Telescope, Inertia, Firebase, Vue.js

### 2. Configurer .env

```env
DB_CONNECTION=pgsql
DB_DATABASE=leopardo_db
DB_USERNAME=leopardo_user
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```

### 3. Créer le trait BelongsToCompany

```php
// app/Traits/BelongsToCompany.php
// UN SEUL trait, UNE SEULE méthode boot
// - addGlobalScope : filtre automatique WHERE company_id = current
// - creating : auto-set company_id à la création
// - Utilise app('current_company') — PAS de variable static
```

Voir `PILOTAGE.md` section "Architecture MVP" pour le détail.

### 4. Créer le TenantMiddleware simplifié

```php
// app/Http/Middleware/TenantMiddleware.php
// - Récupère l'employé authentifié
// - Charge sa company
// - Vérifie status !== 'suspended'
// - Stocke dans app()->instance('current_company', $company)
// - PAS de SET search_path (mode shared uniquement dans le MVP)
```

### 5. Migrations schéma public

Créer les migrations pour ces tables (dans l'ordre) :
1. `plans`
2. `languages`
3. `hr_model_templates`
4. `companies`
5. `super_admins`
6. `user_lookups`
7. `invoices`
8. `billing_transactions`

Référence = `07_SCHEMA_SQL_COMPLET.sql` lignes 24-167.

### 6. Migrations schéma tenant (tables shared)

Ces tables vivent dans le schéma public avec un `company_id` :
1. `departments`
2. `positions`
3. `schedules`
4. `sites`
5. `employees`
6. `attendance_logs`
7. `company_settings`
8. `audit_logs`
9. `notifications`

Référence = `07_SCHEMA_SQL_COMPLET.sql` lignes 185-675.
Chaque table DOIT avoir `company_id UUID NOT NULL` (contrairement au mode schema où il est NULL).

### 7. Seeders

```
PlanSeeder    → Trial (gratuit 14j, max 5 empl), Starter (29€, max 20), Business (79€, max 100)
LanguageSeeder → fr (actif)
HRModelSeeder → DZ (Algérie) avec CNAS + IRG basiques
```

### 8. Route health check

```php
Route::get('/api/v1/health', fn() => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toISOString(),
]));
```

---

## TESTS À ÉCRIRE (avant le code)

```php
// tests/Feature/HealthCheckTest.php
it('health endpoint returns ok', function () {
    $this->getJson('/api/v1/health')
         ->assertStatus(200)
         ->assertJson(['status' => 'ok']);
});

// tests/Feature/TenantIsolationTest.php
it('company A employee cannot see company B employees', function () {
    $companyA = Company::factory()->create();
    $employeeA = Employee::factory()->for($companyA)->create();

    $companyB = Company::factory()->create();
    $employeeB = Employee::factory()->for($companyB)->create();

    // Simuler le contexte tenant A
    app()->instance('current_company', $companyA);

    // Le scope doit filtrer automatiquement
    $employees = Employee::all();
    expect($employees->pluck('id')->toArray())->toContain($employeeA->id);
    expect($employees->pluck('id')->toArray())->not->toContain($employeeB->id);
});

// tests/Feature/MigrationsTest.php
it('all migrations run without error', function () {
    $this->artisan('migrate:fresh')->assertExitCode(0);
});

it('seeders run without error', function () {
    $this->artisan('db:seed')->assertExitCode(0);
    expect(\App\Models\Plan::count())->toBe(3);
});
```

---

## PORTE VERTE → MVP-02

```
[ ] php artisan test → 0 failure
[ ] GET /api/v1/health → {"status":"ok"}
[ ] php artisan migrate:fresh → 0 erreur
[ ] php artisan db:seed → plans, langue fr, modèle DZ créés
[ ] TenantIsolationTest → vert
[ ] Aucune dépendance Redis/Horizon/Firebase
```

---

## COMMIT

```
feat(init): Laravel 11 with PostgreSQL, Sanctum, shared multitenancy
test(tenant): add tenant isolation test — company A cannot see company B
```
