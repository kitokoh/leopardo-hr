# CC-03 — Migrations tenant + Module Employés + Config organisationnelle
# Agent : Claude Code
# Durée : 6-8 heures
# Prérequis : CC-02 vert (TenantIsolationTest 100%)

---

## PRÉREQUIS VÉRIFIABLES

```bash
php artisan test --filter="Auth|Infrastructure"  # 0 failure
# Vérifier que TenantService.createTenantSchema() fonctionne
php artisan tinker
>>> app(\App\Services\TenantService::class)->createTenantSchema(Company::first())
# Doit créer le schéma et les tables sans erreur
```

---

## MISSION DE CETTE ÉTAPE

Créer TOUTES les migrations du schéma tenant + les 4 modules de configuration
(Departments, Positions, Schedules, Sites) + le module Employees complet.
Ces modules sont nécessaires pour TOUS les modules suivants (Pointage, Paie, Absences).

---

## PARTIE A — MIGRATIONS SCHÉMA TENANT (toutes les 20 tables)

Créer dans `database/migrations/tenant/` (dans cet ordre exact — respecter les FK) :

```bash
# Ordre obligatoire — les FK dépendent de cet ordre
php artisan make:migration create_company_settings_table --path=database/migrations/tenant
php artisan make:migration create_departments_table --path=database/migrations/tenant
php artisan make:migration create_positions_table --path=database/migrations/tenant
php artisan make:migration create_schedules_table --path=database/migrations/tenant
php artisan make:migration create_sites_table --path=database/migrations/tenant
php artisan make:migration create_employees_table --path=database/migrations/tenant
php artisan make:migration create_devices_table --path=database/migrations/tenant
php artisan make:migration create_attendance_logs_table --path=database/migrations/tenant
php artisan make:migration create_absence_types_table --path=database/migrations/tenant
php artisan make:migration create_absences_table --path=database/migrations/tenant
php artisan make:migration create_leave_balance_logs_table --path=database/migrations/tenant
php artisan make:migration create_salary_advances_table --path=database/migrations/tenant
php artisan make:migration create_projects_table --path=database/migrations/tenant
php artisan make:migration create_tasks_table --path=database/migrations/tenant
php artisan make:migration create_task_comments_table --path=database/migrations/tenant
php artisan make:migration create_evaluations_table --path=database/migrations/tenant
php artisan make:migration create_payrolls_table --path=database/migrations/tenant
php artisan make:migration create_payroll_export_batches_table --path=database/migrations/tenant
php artisan make:migration create_audit_logs_table --path=database/migrations/tenant
php artisan make:migration create_notifications_table --path=database/migrations/tenant
```

Contenu de chaque migration : voir `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md`.

**Table attendance_logs — contrainte UNIQUE critique :**
```php
$table->unique(['employee_id', 'date'], 'unique_attendance_per_day');
// Cette contrainte empêche le double check-in et force le check-out en UPDATE
```

**Table absences — contrainte CHECK critique :**
```php
// À ajouter après la création de la table
DB::statement('ALTER TABLE absences ADD CONSTRAINT chk_absence_dates CHECK (end_date >= start_date)');
```

---

## PARTIE B — MODULE CONFIGURATION ORGANISATIONNELLE

### Endpoints à implémenter

```
CRUD /api/v1/departments    → GET(liste), POST, GET/{id}, PUT/{id}, DELETE/{id}
CRUD /api/v1/positions      → GET(liste), POST, GET/{id}, PUT/{id}, DELETE/{id}
CRUD /api/v1/schedules      → GET(liste), POST, GET/{id}, PUT/{id}, DELETE/{id}
CRUD /api/v1/sites          → GET(liste), POST, GET/{id}, PUT/{id}, DELETE/{id}
```

Payloads exacts : `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` — Section 3.

### Tests à écrire en premier

```php
// tests/Feature/Config/DepartmentTest.php

it('manager principal can create department', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');

    $this->withToken($token)
         ->postJson('/api/v1/departments', [
             'name'        => 'Ressources Humaines',
             'description' => 'Département RH',
             'color'       => '#4F46E5',
         ])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['id', 'name', 'employees_count']]);
});

it('cannot delete department with active employees', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');
    $dept = Department::factory()->create();
    Employee::factory()->count(3)->create(['department_id' => $dept->id]);

    $this->withToken($token)
         ->deleteJson("/api/v1/departments/{$dept->id}")
         ->assertStatus(409)
         ->assertJson(['error' => 'DEPARTMENT_HAS_EMPLOYEES']);
});

it('cannot delete schedule in use', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');
    $schedule = Schedule::factory()->create();
    Employee::factory()->create(['schedule_id' => $schedule->id]);

    $this->withToken($token)
         ->deleteJson("/api/v1/schedules/{$schedule->id}")
         ->assertStatus(409)
         ->assertJson(['error' => 'SCHEDULE_IN_USE']);
});

it('departments are cached in Redis', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');
    Department::factory()->count(5)->create();

    $cacheKey = "departments";
    Cache::tags(["tenant:{$company->uuid}"])->forget($cacheKey);

    $this->withToken($token)->getJson('/api/v1/departments');

    // La 2ème requête doit utiliser le cache
    expect(Cache::tags(["tenant:{$company->uuid}"])->has($cacheKey))->toBeTrue();
});

it('cache is invalidated after department creation', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');

    // Mettre quelque chose en cache
    Cache::tags(["tenant:{$company->uuid}"])->put('departments', ['old_data'], 3600);

    // Créer un département → doit vider le cache
    $this->withToken($token)->postJson('/api/v1/departments', ['name' => 'Test Dept']);

    expect(Cache::tags(["tenant:{$company->uuid}"])->has('departments'))->toBeFalse();
});
```

### Fichiers à créer

```
app/Http/Controllers/Tenant/DepartmentController.php
app/Http/Controllers/Tenant/PositionController.php
app/Http/Controllers/Tenant/ScheduleController.php
app/Http/Controllers/Tenant/SiteController.php
app/Http/Requests/Tenant/StoreDepartmentRequest.php
app/Http/Requests/Tenant/StorePositionRequest.php
app/Http/Requests/Tenant/StoreScheduleRequest.php
app/Http/Requests/Tenant/StoreSiteRequest.php
app/Http/Resources/DepartmentResource.php
app/Http/Resources/PositionResource.php
app/Http/Resources/ScheduleResource.php
app/Http/Resources/SiteResource.php
app/Models/Tenant/Department.php
app/Models/Tenant/Position.php
app/Models/Tenant/Schedule.php
app/Models/Tenant/Site.php
```

### Cache Redis obligatoire (pattern pour les 4 modules)

```php
// DepartmentController::index()
public function index(): JsonResponse
{
    $company = app('current_company');
    $departments = Cache::tags(["tenant:{$company->uuid}"])
        ->remember('departments', 86400, fn() => Department::withCount('employees')->get());

    return response()->json(['data' => DepartmentResource::collection($departments)]);
}

// DepartmentController::store() / update() / destroy()
private function invalidateCache(): void
{
    $company = app('current_company');
    Cache::tags(["tenant:{$company->uuid}"])->forget('departments');
}
```

---

## PARTIE C — MODULE EMPLOYÉS

### Endpoints à implémenter

```
GET    /api/v1/employees              → liste paginée avec filtres
POST   /api/v1/employees              → créer un employé
GET    /api/v1/employees/{id}         → fiche complète
PUT    /api/v1/employees/{id}         → modifier
DELETE /api/v1/employees/{id}         → archiver (soft delete)
POST   /api/v1/employees/import       → import CSV
GET    /api/v1/employees/{id}/payslips→ liste des bulletins
GET    /api/v1/profile               → profil de l'employé connecté
PUT    /api/v1/profile               → modifier son profil (champs limités)
POST   /api/v1/profile/photo         → uploader sa photo
PUT    /api/v1/profile/password      → changer son mot de passe
```

Payloads exacts : `02_API_CONTRATS_COMPLET.md` — Section 2.

### Tests à écrire en premier

```php
// tests/Feature/Employees/EmployeeTest.php

it('manager rh can create employee', function () {
    [$company, $manager, $token] = createManagerWithToken('rh');
    $dept = Department::factory()->create();
    $position = Position::factory()->create();
    $schedule = Schedule::factory()->create();

    $this->withToken($token)
         ->postJson('/api/v1/employees', [
             'first_name'    => 'Amina',
             'last_name'     => 'Belkacem',
             'email'         => 'amina@company.dz',
             'matricule'     => 'EMP-2026-001',
             'department_id' => $dept->id,
             'position_id'   => $position->id,
             'schedule_id'   => $schedule->id,
             'salary_base'   => 65000,
             'hire_date'     => '2026-01-15',
             'country'       => 'DZ',
             'contract_type' => 'cdi',
         ])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['id', 'matricule', 'first_name', 'last_name', 'email']]);
});

it('employee role cannot create another employee', function () {
    [$company, $employee, $token] = createEmployeeWithToken();

    $this->withToken($token)
         ->postJson('/api/v1/employees', [
             'first_name' => 'Hacked',
             'email'      => 'hacked@company.dz',
         ])
         ->assertStatus(403);
});

it('plan limit blocks employee creation when limit reached', function () {
    // Plan Starter : max 20 employés
    [$company, $manager, $token] = createManagerWithToken('principal', plan: 'starter');
    Employee::factory()->count(20)->create(); // atteindre la limite

    $this->withToken($token)
         ->postJson('/api/v1/employees', ['first_name' => 'OneMore', 'email' => 'one@more.dz'])
         ->assertStatus(403)
         ->assertJson(['error' => 'PLAN_EMPLOYEE_LIMIT_REACHED']);
});

it('employee can only view their own profile', function () {
    [$company, $employeeA, $tokenA] = createEmployeeWithToken();
    $employeeB = Employee::factory()->create();

    // A peut voir son propre profil
    $this->withToken($tokenA)->getJson('/api/v1/profile')->assertStatus(200);

    // A ne peut pas voir la fiche de B
    $this->withToken($tokenA)
         ->getJson("/api/v1/employees/{$employeeB->id}")
         ->assertStatus(403);
});

it('employee audit is logged on update', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');
    $employee = Employee::factory()->create(['salary_base' => 50000]);

    $this->withToken($token)
         ->putJson("/api/v1/employees/{$employee->id}", ['salary_base' => 60000]);

    // Vérifier que l'audit log est créé
    $this->assertDatabaseHas('audit_logs', [
        'action'    => 'employee.updated',
        'table_name'=> 'employees',
        'record_id' => $employee->id,
    ]);
});

it('csv import creates employees in bulk', function () {
    [$company, $manager, $token] = createManagerWithToken('rh');

    $csv = "first_name,last_name,email,salary_base,hire_date\n";
    $csv .= "Karim,Amrani,karim@co.dz,55000,2026-01-01\n";
    $csv .= "Fatima,Zohra,fatima@co.dz,48000,2026-01-15\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('employees.csv', $csv);

    $this->withToken($token)
         ->post('/api/v1/employees/import', ['file' => $file])
         ->assertStatus(200)
         ->assertJsonStructure(['data' => ['imported', 'errors']]);

    $this->assertDatabaseHas('employees', ['email' => 'karim@co.dz']);
    $this->assertDatabaseHas('employees', ['email' => 'fatima@co.dz']);
});
```

### Fichiers à créer

```
app/Http/Controllers/Tenant/EmployeeController.php
app/Http/Controllers/Shared/ProfileController.php
app/Http/Requests/Tenant/StoreEmployeeRequest.php
app/Http/Requests/Tenant/UpdateEmployeeRequest.php
app/Http/Requests/Shared/UpdateProfileRequest.php
app/Http/Resources/EmployeeResource.php          ← complet avec relations
app/Http/Resources/EmployeeListResource.php      ← version allégée pour les listes
app/Models/Tenant/Employee.php                   ← avec SoftDeletes + EncryptedCast sur national_id
app/Observers/EmployeeObserver.php
app/Policies/EmployeePolicy.php                  ← logique RBAC (voir 10_RBAC_COMPLET.md)
app/Http/Middleware/CheckPlanLimit.php           ← bloque si employees > plan.max_employees
```

### EmployeeObserver (audit automatique obligatoire)

```php
// app/Observers/EmployeeObserver.php
class EmployeeObserver
{
    private array $sensitiveFields = ['password', 'national_id', 'iban'];

    public function updated(Employee $employee): void
    {
        $dirty = collect($employee->getDirty())
            ->except($this->sensitiveFields)  // ne jamais logger les champs sensibles
            ->toArray();

        if (empty($dirty)) return; // rien de notable changé

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'employee.updated',
            'table_name' => 'employees',
            'record_id'  => $employee->id,
            'old_values' => collect($employee->getOriginal())->only(array_keys($dirty))->except($this->sensitiveFields)->toArray(),
            'new_values' => $dirty,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function deleted(Employee $employee): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'employee.archived',
            'table_name' => 'employees',
            'record_id'  => $employee->id,
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'archived'],
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS CC-04

```
[ ] php artisan test --filter="Config|Employee" → 0 failure
[ ] Toutes les migrations tenant s'exécutent sans erreur dans un nouveau schéma
[ ] CRUD Departments avec cache Redis validé
[ ] Contrainte UNIQUE attendance (employee_id, date) vérifiée en DB
[ ] Contrainte FK cascade correctes (employee supprimé → cascade sur devices, etc.)
[ ] Test PLAN_LIMIT passe (403 si dépassement)
[ ] Observer audit écrit dans audit_logs à chaque update employé
[ ] php artisan test (global) → 0 failure (aucune régression)
```

---

## COMMIT

```
feat: add all 20 tenant schema migrations with correct FK order and constraints
feat: add org config modules (departments, positions, schedules, sites) with Redis cache
feat: add complete employee module with RBAC, CSV import, audit observer
feat: add plan limit middleware — blocks creation above plan threshold
test: add config and employee tests including audit, RBAC, plan limits
```
