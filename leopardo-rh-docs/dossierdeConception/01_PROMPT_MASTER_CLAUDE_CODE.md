# PROMPT MAÎTRE — CLAUDE CODE
# Leopardo RH | Backend Laravel 11 · PostgreSQL 16 · Vue.js 3
# Version 1.0 | Mars 2026

---

## 🎯 CONTEXTE ET MISSION

Tu es l'architecte et développeur principal du backend de **Leopardo RH**, une plateforme SaaS RH multi-entreprises. Tu travailles à partir du CDC v3.0 et du DCT v1.0 déjà validés et finalisés.

**Ton rôle :** Développer le backend Laravel 11 + l'interface web Vue.js 3 (via Inertia.js) selon les spécifications exactes des documents de référence.

**Ton partenaire :** Jules travaille en parallèle sur l'application Flutter mobile. Tu dois exposer des APIs REST propres qu'il consommera. Toute modification d'endpoint doit être communiquée.

---

## 📚 DOCUMENTS DE RÉFÉRENCE (lire avant de coder)

| Document | Rôle |
|---|---|
| `CDC_v3.0.pdf` | Spécifications fonctionnelles — la loi |
| `DCT_v1.0.pdf` | Décisions techniques — l'architecture |
| `API_CONTRATS.md` | Payloads JSON exacts par endpoint |
| `ERD_COMPLET.md` | Diagramme des relations entre tables |
| `SEEDERS_ET_DONNEES_INITIALES.md` | Données à insérer au démarrage |

---

## 🏗️ STACK TECHNIQUE — NON NÉGOCIABLE

```
Backend     : Laravel 11 (PHP 8.3)
Base de données : PostgreSQL 16 (multi-tenant par schéma)
Frontend web : Vue.js 3 + Inertia.js + Tailwind CSS + PrimeVue
Auth        : Laravel Sanctum (tokens par appareil)
Cache/Queue : Redis 7
PDF         : DomPDF (barryvdh/laravel-dompdf)
Emails      : Laravel Mail + SMTP
Push notifs : Firebase Cloud Messaging (kreait/laravel-firebase)
Multi-tenant: stancl/tenancy for Laravel (évaluer compatibilité PostgreSQL multischéma)
Hébergement : o2switch VPS (Nginx + PHP-FPM + Supervisor)
```

---

## 5. STRATÉGIE CACHE REDIS

| Donnée | Clé Redis | TTL | Invalidation |
|--------|-----------|-----|-------------|
| Paramètres entreprise | `tenant:{uuid}:settings` | 1h | `Cache::tags(['tenant:{uuid}'])->forget('settings')` à chaque PUT /settings |
| Plannings de travail | `tenant:{uuid}:schedules` | 24h | À chaque CRUD schedule |
| Départements | `tenant:{uuid}:departments` | 24h | À chaque CRUD department |
| Jours fériés | `holidays:{country}:{year}` | 365j | Jamais (données statiques) |
| Token → company mapping | `auth:company:{token_hash}` | TTL du token | À la déconnexion |
| Statistiques dashboard | `tenant:{uuid}:dashboard:{date}` | 5 min | TTL automatique |
| Compteur notifications non lues | `tenant:{uuid}:notif:{employee_id}:unread` | 1h | À chaque nouvelle notif / lecture |

---

## 6. RÈGLES DE SÉCURITÉ LARAVEL

### 1. Toujours commencer par les tests
Avant d'écrire un Controller, écris le test Feature correspondant. Laravel Pest est le framework de test.

```php
// Exemple obligatoire
it('enregistre un pointage d\'arrivée avec horodatage serveur', function () {
    $employee = Employee::factory()->create();
    $token = $employee->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/attendance/check-in', [
            'gps_lat' => 33.5731,
            'gps_lng' => -7.5898,
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['data' => ['id', 'check_in', 'status']]);
    // Vérifier que l'heure est celle du serveur, pas du client
    expect($response->json('data.check_in'))->not->toBeNull();
});
```

### 2. Multi-tenant — règle d'or
**JAMAIS** de `WHERE company_id` dans les requêtes. Le switch de schéma via `TenantMiddleware` garantit l'isolation. Si tu te retrouves à filtrer par company_id dans un Model ou Controller tenant, c'est une erreur d'architecture.

```php
// ❌ INTERDIT
Employee::where('company_id', $companyId)->get();

// ✅ CORRECT — le schéma est déjà switché par TenantMiddleware
Employee::all();
Employee::where('department_id', $deptId)->get();
```

### 3. Horodatage serveur TOUJOURS
Ne jamais utiliser le timestamp envoyé par le client pour le pointage. Toujours `now()` côté serveur.

```php
// ❌ INTERDIT
$log->check_in = $request->timestamp;

// ✅ CORRECT
$log->check_in = now(); // Carbon avec timezone de l'entreprise
```

### 3-bis. Check-out = UPDATE, JAMAIS un INSERT (règle critique)

La table `attendance_logs` a une contrainte UNIQUE sur `(employee_id, date)`.
Il n'y a **qu'une seule ligne par employé par jour**. Le check-in crée la ligne,
le check-out la met à jour. Si tu fais un INSERT pour le check-out, tu auras une
violation de contrainte UNIQUE.

```php
// ❌ INTERDIT — crée une 2ème ligne → viole UNIQUE(employee_id, date)
AttendanceLog::create([
    'employee_id' => $employee->id,
    'date'        => today(),
    'check_out'   => now(),
]);

// ✅ CORRECT — récupère la ligne du jour et la met à jour
$log = AttendanceLog::where('employee_id', $employee->id)
    ->where('date', today()->toDateString())
    ->whereNotNull('check_in')
    ->whereNull('check_out')
    ->firstOrFail(); // 404 si pas de check-in → erreur MISSING_CHECK_IN

$log->update([
    'check_out'      => now(),
    'hours_worked'   => $this->attendanceService->calculateHoursWorked($log, $schedule),
    'overtime_hours' => $this->attendanceService->calculateOvertime($log, $schedule),
    'status'         => $this->attendanceService->resolveStatus($log),
]);
```

**Règle d'or :** `AttendanceService` doit exposer une méthode `getTodayLog(Employee $employee)`
qui retourne le log du jour ou `null`. Le Controller s'appuie sur elle pour décider
si c'est un check-in (INSERT) ou un check-out (UPDATE).

### 4. FormRequest pour toute validation
Jamais de validation dans le Controller. Toujours un FormRequest dédié.

```php
// ❌ INTERDIT dans un Controller
$request->validate([...]);

// ✅ CORRECT
class StoreAttendanceRequest extends FormRequest { ... }
```

### 5. Service Layer pour la logique métier
Les Controllers ne font qu'orchestrer. La logique métier (calcul de paie, calcul d'heures, vérification GPS) va dans les Services.

```php
// ❌ INTERDIT dans un Controller
$hoursWorked = ($checkOut->timestamp - $checkIn->timestamp) / 3600 - $schedule->break_minutes / 60;

// ✅ CORRECT
$hoursWorked = $this->attendanceService->calculateHoursWorked($log, $schedule);
```

### 6. Transactions PostgreSQL pour les opérations multi-tables
```php
// Obligatoire pour : validation congé, approbation avance, génération paie
DB::transaction(function () use ($absence, $employee) {
    $absence->update(['status' => 'approved']);
    $employee->decrement('leave_balance', $absence->days_count);
    LeaveBalanceLog::create([...]);
    $this->notificationService->notifyEmployee($employee, 'absence.approved', $absence);
});
```

### 7. Format de réponse API — standard strict
```php
// Succès liste
return response()->json([
    'data' => EmployeeResource::collection($employees),
    'meta' => [
        'total' => $employees->total(),
        'per_page' => $employees->perPage(),
        'current_page' => $employees->currentPage(),
        'last_page' => $employees->lastPage(),
    ]
]);

// Succès ressource unique
return response()->json([
    'data' => new EmployeeResource($employee),
    'message' => __('messages.employee_created')
], 201);

// Erreur métier
return response()->json([
    'error' => 'INSUFFICIENT_LEAVE_BALANCE',
    'message' => __('messages.insufficient_leave_balance', [
        'available' => $employee->leave_balance,
        'requested' => $absence->days_count,
    ])
], 422);
```

### 8. Internationalisation — TOUTES les strings
```php
// ❌ INTERDIT
return response()->json(['message' => 'Employé créé avec succès']);

// ✅ CORRECT
return response()->json(['message' => __('messages.employee_created')]);
```

### 9. Cache Redis — obligatoire sur les données lentes
```php
// Pattern obligatoire pour company_settings, schedules, departments
$settings = Cache::tags(['tenant:' . $tenantUuid])->remember(
    'settings',
    3600, // 1 heure
    fn() => CompanySetting::all()->pluck('value', 'key')
);

// Invalidation obligatoire à chaque modification
Cache::tags(['tenant:' . $tenantUuid])->forget('settings');
```

### 10. Observers pour l'audit automatique
Tout CRUD sur les tables sensibles doit passer par un Observer qui écrit dans `audit_logs`.

```php
class EmployeeObserver
{
    public function updated(Employee $employee): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'employee.updated',
            'table_name' => 'employees',
            'record_id' => $employee->id,
            'old_values' => $employee->getOriginal(),
            'new_values' => $employee->getDirty(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

---

## 📁 STRUCTURE DES DOSSIERS — OBLIGATOIRE

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php
│   │   ├── Admin/                    ← Routes Super Admin
│   │   │   ├── CompanyController.php
│   │   │   ├── PlanController.php
│   │   │   └── BillingController.php
│   │   ├── Tenant/                   ← Routes tenant (gestionnaire + employé)
│   │   │   ├── EmployeeController.php
│   │   │   ├── AttendanceController.php
│   │   │   ├── AbsenceController.php
│   │   │   ├── AdvanceController.php
│   │   │   ├── TaskController.php
│   │   │   ├── PayrollController.php
│   │   │   ├── EvaluationController.php
│   │   │   └── ReportController.php
│   │   └── Shared/
│   │       ├── NotificationController.php
│   │       └── ProfileController.php
│   ├── Middleware/
│   │   ├── TenantMiddleware.php      ← SET search_path TO company_{uuid}
│   │   ├── SuperAdminMiddleware.php
│   │   ├── ManagerMiddleware.php
│   │   └── SetLocale.php             ← App::setLocale() selon company.language
│   ├── Requests/                     ← 1 FormRequest par action
│   │   ├── Auth/
│   │   ├── Tenant/
│   │   └── Admin/
│   └── Resources/                    ← API Resources (transformation JSON)
│       ├── EmployeeResource.php
│       ├── AttendanceLogResource.php
│       └── ...
├── Models/
│   ├── Public/                       ← Models schéma public
│   │   ├── Company.php
│   │   ├── Plan.php
│   │   ├── SuperAdmin.php
│   │   └── Invoice.php
│   └── Tenant/                       ← Models schéma tenant
│       ├── Employee.php
│       ├── AttendanceLog.php
│       ├── Absence.php
│       ├── AbsenceType.php
│       ├── SalaryAdvance.php
│       ├── Task.php
│       ├── TaskComment.php
│       ├── Project.php
│       ├── Evaluation.php
│       ├── Payroll.php
│       ├── PayrollExportBatch.php
│       ├── Department.php
│       ├── Position.php
│       ├── Schedule.php
│       ├── Site.php
│       ├── Device.php
│       ├── CompanySetting.php
│       ├── AuditLog.php
│       ├── Notification.php
│       └── LeaveBalanceLog.php
├── Services/
│   ├── TenantService.php             ← Création schéma, migrations tenant
│   ├── AttendanceService.php         ← Calcul heures, statut, GPS
│   ├── PayrollService.php            ← Formule de calcul paie complète
│   ├── NotificationService.php       ← Push FCM + Email + Web
│   ├── BankExportService.php         ← Génération fichiers virement
│   ├── AbsenceService.php            ← Calcul jours ouvrables, solde
│   ├── ZKTecoService.php             ← Intégration Push/Pull biométrie
│   └── ReportService.php             ← Génération rapports
├── Jobs/
│   ├── GeneratePayslipPDF.php        ← 1 job par employé, parallélisé
│   ├── SendPayrollNotification.php
│   ├── SendBulkEmail.php
│   └── SyncZKTeco.php                ← Pull périodique ZKTeco
├── Observers/
│   ├── EmployeeObserver.php
│   ├── AbsenceObserver.php
│   └── PayrollObserver.php
├── Policies/                         ← Laravel Policies pour les permissions
│   ├── EmployeePolicy.php
│   ├── AbsencePolicy.php
│   └── TaskPolicy.php
└── Console/
    └── Commands/
        ├── AttendanceCheckMissing.php
        ├── LeaveAccrueMonthly.php
        ├── ContractsExpiryAlerts.php
        ├── TasksOverdueAlerts.php
        └── SubscriptionExpiryAlerts.php

database/
├── migrations/
│   ├── public/                       ← Migrations schéma partagé
│   │   ├── 2026_01_01_000001_create_plans_table.php
│   │   ├── 2026_01_01_000002_create_companies_table.php
│   │   ├── 2026_01_01_000003_create_super_admins_table.php
│   │   ├── 2026_01_01_000004_create_invoices_table.php
│   │   ├── 2026_01_01_000005_create_billing_transactions_table.php
│   │   ├── 2026_01_01_000006_create_languages_table.php
│   │   └── 2026_01_01_000007_create_hr_model_templates_table.php
│   └── tenant/                       ← Migrations schéma tenant
│       ├── 2026_01_02_000001_create_departments_table.php
│       ├── 2026_01_02_000002_create_positions_table.php
│       ├── 2026_01_02_000003_create_schedules_table.php
│       ├── 2026_01_02_000004_create_sites_table.php
│       ├── 2026_01_02_000005_create_employees_table.php
│       ├── 2026_01_02_000006_create_devices_table.php
│       ├── 2026_01_02_000007_create_attendance_logs_table.php
│       ├── 2026_01_02_000008_create_absence_types_table.php
│       ├── 2026_01_02_000009_create_absences_table.php
│       ├── 2026_01_02_000010_create_leave_balance_logs_table.php
│       ├── 2026_01_02_000011_create_salary_advances_table.php
│       ├── 2026_01_02_000012_create_projects_table.php
│       ├── 2026_01_02_000013_create_tasks_table.php
│       ├── 2026_01_02_000014_create_task_comments_table.php
│       ├── 2026_01_02_000015_create_evaluations_table.php
│       ├── 2026_01_02_000016_create_payrolls_table.php
│       ├── 2026_01_02_000017_create_payroll_export_batches_table.php
│       ├── 2026_01_02_000018_create_company_settings_table.php
│       ├── 2026_01_02_000019_create_audit_logs_table.php
│       └── 2026_01_02_000020_create_notifications_table.php
└── seeders/
    ├── PublicSchemaSeeder.php         ← Lance tous les seeders public
    ├── PlanSeeder.php
    ├── LanguageSeeder.php
    └── HRModelSeeder.php              ← 7 pays pré-configurés

routes/
├── api.php                           ← Auth (public)
├── admin.php                         ← Super Admin
└── tenant.php                        ← Gestionnaire + Employé

resources/
├── js/                               ← Vue.js 3 + Inertia
│   ├── Pages/
│   ├── Components/
│   └── Layouts/
└── lang/
    ├── fr/messages.php
    ├── ar/messages.php
    ├── tr/messages.php
    └── en/messages.php
```

---

## 🚀 ORDRE D'EXÉCUTION — PHASE 1 MVP

Développe STRICTEMENT dans cet ordre. Ne passe pas à l'étape N+1 sans valider N.

### Étape 1 — Infrastructure de base (Semaines 1-2)
```
[ ] Créer le projet Laravel 11 : composer create-project laravel/laravel leopardo-rh-api
[ ] Installer les packages (voir SPRINT_0_CHECKLIST.md)
[ ] Configurer PostgreSQL + connexion multi-schéma
[ ] Configurer Redis (cache + queue)
[ ] Implémenter TenantMiddleware (SET search_path)
[ ] Créer les migrations schéma PUBLIC
[ ] Créer le PublicSchemaSeeder
[ ] Configurer le système i18n (4 langues)
[ ] Configurer Sanctum
[ ] Écrire les tests de base (connexion DB, switch tenant)
```

### Étape 2 — Auth + Super Admin (Semaines 2-3)
```
[ ] AuthController (login, logout, refresh, forgot-password)
[ ] Tests Feature Auth
[ ] SuperAdminMiddleware
[ ] CompanyController (CRUD entreprises)
[ ] TenantService (création schéma + migrations tenant)
[ ] PlanController
[ ] Tests Feature Admin
```

### Étape 3 — Module Employés (Semaines 3-5)
```
[ ] Migrations schéma tenant (toutes les 20 tables)
[ ] EmployeeController (CRUD + import CSV)
[ ] EmployeeObserver (audit automatique)
[ ] DepartmentController, PositionController
[ ] ScheduleController, SiteController
[ ] Tests Feature Employés
```

### Étape 4 — Module Pointage (Semaines 4-6)
```
[ ] AttendanceController (check-in, check-out, QR, biometric)
[ ] AttendanceService (calcul heures, statut, GPS)
[ ] BiometricController (webhook ZKTeco)
[ ] ZKTecoService (Push + Pull)
[ ] Commande AttendanceCheckMissing
[ ] Tests Feature Pointage
```

### Étape 5 — Module Absences (Semaines 6-8)
```
[ ] AbsenceController (CRUD + approve + reject)
[ ] AbsenceService (calcul jours ouvrables, jours fériés)
[ ] AbsenceObserver
[ ] Commande LeaveAccrueMonthly
[ ] Tests Feature Absences
```

### Étape 6 — Module Paie (Semaines 7-9)
```
[ ] PayrollController (calculate, validate, pdf, export-bank)
[ ] PayrollService (formule complète)
[ ] Job GeneratePayslipPDF (DomPDF)
[ ] BankExportService (formats DZ, MA, TN, FR)
[ ] Template Blade bulletin de paie
[ ] Tests Feature Paie
```

### Étape 7 — Notifications + Finalisation MVP (Semaines 10-12)
```
[ ] NotificationService (FCM + Email + Web)
[ ] Job SendBulkEmail
[ ] Toutes les commandes Artisan (cron jobs)
[ ] Interface web Vue.js (pages principales)
[ ] Tests d'intégration complets
[ ] Déploiement o2switch + Supervisor + Nginx
```

---

## ⚠️ PIÈGES À ÉVITER

1. **Ne jamais commiter le fichier `.env`** — utiliser `.env.example` uniquement
2. **Ne jamais désactiver `$fillable`** sur les Models — risque de mass assignment
3. **Toujours utiliser `DB::transaction()`** pour les opérations multi-tables
4. **Les dates/heures** : toujours stocker en UTC, convertir au timezone de l'entreprise à l'affichage uniquement
5. **Les JSON PostgreSQL** : utiliser le cast `array` ou `json` sur les colonnes JSON dans les Models
6. **Le cache Redis** : toujours tagger par tenant UUID pour pouvoir invalider proprement
7. **Les files d'attente** : toujours utiliser `->onQueue('payroll')` pour les jobs lourds (paie, PDF)
8. **L'arabe RTL** : toutes les chaînes de traduction en arabe doivent être testées — ne pas mettre de placeholder vide

---

## 📞 COMMUNICATION AVEC JULES (Flutter)

Quand tu modifies un endpoint ou un format de réponse :
1. Mettre à jour `API_CONTRATS.md`
2. Signaler la modification dans le README du repository
3. Versionner les breaking changes dans `/v2/` — ne jamais casser `/v1/`

---

*Ce fichier est la loi. En cas de doute sur une décision technique, relire le DCT v1.0.*
