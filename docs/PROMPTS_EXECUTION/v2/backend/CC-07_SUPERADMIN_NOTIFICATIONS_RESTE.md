# CC-07 — Super Admin + Notifications + Tâches + Évaluations + Rapports
# Agent : Claude Code
# Durée : 8-10 heures
# Prérequis : CC-06 vert

---

## POURQUOI CE PROMPT EXISTE

Le Super Admin était absent des prompts précédents malgré son importance critique.
Ce prompt couvre les modules restants du backend avant le déploiement.

---

## PARTIE A — SUPER ADMIN

### Endpoints

```
GET    /api/v1/admin/companies           → liste toutes les entreprises
GET    /api/v1/admin/companies/{id}      → détail + stats
POST   /api/v1/admin/companies/{id}/suspend   → suspendre
POST   /api/v1/admin/companies/{id}/activate  → réactiver
GET    /api/v1/admin/plans               → liste des plans
PUT    /api/v1/admin/plans/{id}          → modifier un plan
GET    /api/v1/admin/stats               → statistiques globales SaaS
GET    /api/v1/admin/languages           → liste des langues
PUT    /api/v1/admin/languages/{id}      → activer/désactiver une langue
GET    /api/v1/admin/hr-models           → modèles RH par pays
GET    /api/v1/admin/hr-models/{country} → modèle RH d'un pays
```

### Tests

```php
it('super admin can list all companies', function () {
    $superAdmin = SuperAdmin::factory()->create();
    $token = $superAdmin->createToken('admin')->plainTextToken;
    Company::factory()->count(5)->create();

    $this->withToken($token)
         ->getJson('/api/v1/admin/companies')
         ->assertStatus(200)
         ->assertJsonStructure(['data' => [['id', 'name', 'status', 'employees_count', 'plan']]]);
});

it('regular employee cannot access super admin routes', function () {
    [$company, $employee, $token] = createEmployeeWithToken();

    $this->withToken($token)
         ->getJson('/api/v1/admin/companies')
         ->assertStatus(403);
});

it('super admin can suspend a company', function () {
    $superAdmin = SuperAdmin::factory()->create();
    $token = $superAdmin->createToken('admin')->plainTextToken;
    $company = Company::factory()->create(['status' => 'active']);

    $this->withToken($token)
         ->postJson("/api/v1/admin/companies/{$company->id}/suspend", [
             'reason' => 'Non-paiement de la facture',
         ])
         ->assertStatus(200);

    expect($company->fresh()->status)->toBe('suspended');
});
```

### Middleware SuperAdmin

Le SuperAdmin utilise une table séparée (`super_admins` schéma public).
Sanctum doit être configuré avec un second guard `super_admin`.

```php
// config/auth.php — ajouter
'guards' => [
    'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
    'super_admin' => ['driver' => 'sanctum', 'provider' => 'super_admins'],
],
'providers' => [
    'users'        => ['driver' => 'eloquent', 'model' => \App\Models\Tenant\Employee::class],
    'super_admins' => ['driver' => 'eloquent', 'model' => \App\Models\Public\SuperAdmin::class],
],
```

Voir spec complète : `docs/dossierdeConception/07_securite_rbac/15_SUPERADMIN_MIDDLEWARE_SPEC.md`

---

## PARTIE B — NOTIFICATIONS

### Endpoints

```
GET    /api/v1/notifications            → liste des notifications non lues
PUT    /api/v1/notifications/{id}/read  → marquer comme lue
PUT    /api/v1/notifications/read-all   → tout marquer comme lu
GET    /api/v1/notifications/stream     → SSE (Server-Sent Events) — Content-Type: text/event-stream
```

### NotificationService

```php
// app/Services/NotificationService.php

public function sendToEmployee(Employee $employee, array $payload): void
{
    // 1. Persister en base (table notifications du schéma tenant)
    Notification::create([
        'employee_id' => $employee->id,
        'type'        => $payload['type'],
        'title'       => $payload['title'],
        'body'        => $payload['body'],
        'data'        => $payload['data'] ?? [],
        'is_read'     => false,
    ]);

    // 2. Incrémenter le compteur Redis (badge)
    $cacheKey = "tenant:{$employee->company->uuid}:notif:{$employee->id}:unread";
    Cache::increment($cacheKey);
    Cache::expire($cacheKey, 3600);

    // 3. Envoyer push FCM (via job pour ne pas bloquer la requête)
    if ($employee->devices->isNotEmpty()) {
        dispatch(new SendFcmNotificationJob($employee, $payload));
    }
}
```

---

## PARTIE C — TÂCHES ET PROJETS

### Endpoints

```
CRUD   /api/v1/projects                 → projets
CRUD   /api/v1/tasks                    → tâches (liées ou non à un projet)
GET    /api/v1/tasks?assigned_to=me     → mes tâches
PUT    /api/v1/tasks/{id}/status        → changer le statut
POST   /api/v1/tasks/{id}/comments      → ajouter un commentaire
GET    /api/v1/tasks/{id}/comments      → liste des commentaires
POST   /api/v1/tasks/{id}/assign        → assigner à un employé (manager)
```

### Tests critiques

```php
it('employee can only see tasks assigned to them', function () {
    [$company, $employeeA, $tokenA] = createEmployeeWithToken();
    $employeeB = Employee::factory()->create();

    Task::factory()->create(['assigned_to' => $employeeA->id]);
    Task::factory()->create(['assigned_to' => $employeeB->id]);

    $response = $this->withToken($tokenA)
         ->getJson('/api/v1/tasks?assigned_to=me');

    expect($response->json('data'))->toHaveCount(1);
});

it('task overdue alert triggers after deadline', function () {
    $task = Task::factory()->create(['due_date' => now()->subDay(), 'status' => 'pending']);

    $this->artisan('tasks:check-overdue');

    // Vérifie que la notification a été créée
    $this->assertDatabaseHas('notifications', [
        'employee_id' => $task->assigned_to,
        'type'        => 'task.overdue',
    ]);
});
```

---

## PARTIE D — ÉVALUATIONS

### Endpoints

```
GET    /api/v1/evaluations              → liste
POST   /api/v1/evaluations              → créer (manager)
GET    /api/v1/evaluations/{id}         → détail
PUT    /api/v1/evaluations/{id}         → modifier (si status=draft)
POST   /api/v1/evaluations/{id}/submit  → soumettre à l'employé
POST   /api/v1/evaluations/{id}/acknowledge  → employé accuse réception
```

---

## PARTIE E — RAPPORTS

### Endpoints

```
GET    /api/v1/reports/attendance?month=&year=   → rapport présences mensuel
GET    /api/v1/reports/absences?month=&year=     → rapport absences
GET    /api/v1/reports/payroll?month=&year=      → rapport paie
GET    /api/v1/reports/overtime?month=&year=     → rapport heures sup
```

Tous retournent JSON + peuvent exporter en CSV via `?format=csv`.

---

## COMMANDES ARTISAN RESTANTES

```bash
php artisan make:command ContractsExpiryAlerts   # alertes contrats expirant dans 30j
php artisan make:command TasksOverdueAlerts       # alertes tâches en retard
php artisan make:command SubscriptionExpiryAlerts # alertes abonnements expirant dans 7j
```

Planification dans `app/Console/Kernel.php` :
```php
$schedule->command('attendance:check-missing')->hourly();
$schedule->command('leave:accrue-monthly')->monthlyOn(1, '00:00');
$schedule->command('contracts:expiry-alerts')->daily();
$schedule->command('tasks:check-overdue')->daily();
$schedule->command('subscription:expiry-alerts')->daily();
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS CC-08 (déploiement)

```
[ ] php artisan test (global) → 0 failure — TOUS les modules
[ ] Super Admin : 403 pour tout employé normal sur /admin/*
[ ] Notifications : SSE endpoint répond Content-Type: text/event-stream
[ ] Tâches : employé voit seulement ses tâches
[ ] Rapports : CSV généré correctement pour /reports/attendance
[ ] Toutes les commandes Artisan planifiées dans Kernel.php
[ ] php artisan schedule:list → liste complète des 5 crons
```

---

## COMMIT

```
feat: add super admin module with company management and dual Sanctum guard
feat: add notification service with FCM, Redis badge counter, and SSE stream
feat: add tasks and projects module with assignment and comments
feat: add evaluations module with draft/submit/acknowledge workflow
feat: add reports module with CSV export
feat: add 5 scheduled Artisan commands
test: add super admin, notifications, tasks, evaluations tests
```
