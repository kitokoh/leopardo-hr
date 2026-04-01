# 🗄️ Base de données — Leopardo RH
# Guide complet migrations + seeders + factories
# Version 1.0 | 31 Mars 2026

---

## ARCHITECTURE MULTI-TENANT

```
leopardo_db (PostgreSQL 16)
│
├── public                    ← Schéma global (toujours présent)
│   ├── plans                 ← 3 plans tarifaires
│   ├── companies             ← Toutes les entreprises clientes
│   ├── super_admins          ← Administrateurs plateforme
│   ├── user_lookups          ← Dispatch auth email → company + schema
│   ├── languages             ← fr / ar / en / tr
│   ├── hr_model_templates    ← Modèles RH par pays (DZ/MA/TN/FR/TR)
│   ├── invoices              ← Factures SaaS
│   └── billing_transactions  ← Paiements (Stripe, Paydunya...)
│
├── shared_tenants            ← Toutes les PME Starter + Business + Trial
│   ├── employees             ← + company_id (isolation logique)
│   ├── departments           ← + company_id
│   ├── attendance_logs       ← + company_id
│   └── ... (toutes les tables tenant avec company_id)
│
└── company_a1b2c3d4          ← Entreprise Enterprise (schéma dédié)
    ├── employees              ← Sans company_id (isolation physique)
    └── ...
```

---

## ORDRE D'EXÉCUTION DES MIGRATIONS

### Schéma Public (préfixe 000001-000003)
```
000001 → plans
000002 → companies (FK → plans)
000003 → super_admins, user_lookups, languages, hr_model_templates, invoices, billing_transactions
```

### Schéma Tenant (préfixe 000100-000104)
```
000100 → departments (SANS manager_id), positions, schedules, sites
000101 → employees (table centrale — toutes les FK)
000102 → departments.manager_id (ajout après employees — résolution dépendance circulaire)
          employee_devices (FCM tokens), devices (ZKTeco/QR)
000103 → attendance_logs, absence_types, absences, leave_balance_logs, salary_advances
000104 → projects, tasks, task_comments, evaluations, payrolls, payroll_export_batches,
          company_settings, audit_logs, notifications
```

**⚠️ La dépendance circulaire departments ↔ employees est résolue par la migration 000102.**

---

## COMMANDES

### Installation complète (première fois)

```bash
# 1. Exécuter le SQL complet (crée les schémas + toutes les tables)
psql -U leopardo_user -d leopardo_db \
  -f docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql

# 2. Initialiser les tables via les migrations Laravel (pour migrate:rollback)
php artisan migrate --path=database/migrations/public
php artisan migrate --path=database/migrations/tenant

# 3. Seeder de base (plans + langues + modèles RH + super admin)
php artisan db:seed

# 4. Données de démo (local uniquement)
php artisan db:seed --class=DemoCompanySeeder
```

### Rollback sécurisé

```bash
# TOUJOURS faire un backup avant un rollback
pg_dump -Fc -U leopardo_user leopardo_db > backup_$(date +%Y%m%d_%H%M%S).dump

# Rollback dernière migration
php artisan migrate:rollback --step=1

# Restauration backup si nécessaire
pg_restore -Fc -U leopardo_user -d leopardo_db backup_TIMESTAMP.dump
```

### Reset complet (local uniquement !)

```bash
php artisan migrate:fresh --seed
# ou avec données de démo :
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoCompanySeeder
```

---

## SEEDERS

| Seeder | Contenu | Environnement |
|--------|---------|---------------|
| `PlanSeeder` | 3 plans (Starter 29€/Business 79€/Enterprise 199€) | Tous |
| `LanguageSeeder` | 4 langues (fr/ar/en/tr) | Tous |
| `HrModelSeeder` | 5 modèles RH par pays (DZ/MA/TN/FR/TR) | Tous |
| `SuperAdminSeeder` | 1er compte Super Admin (email depuis .env) | Tous |
| `DemoCompanySeeder` | Company + 7 employés + données réalistes | Local uniquement |

---

## FACTORIES (pour les tests Pest)

```php
// Dans les tests — schéma doit être switché avant d'appeler les factories

// Company
$company = Company::factory()->create();          // Starter, shared
$company = Company::factory()->enterprise()->create();
$company = Company::factory()->trial()->create();
$company = Company::factory()->suspended()->create();
$company = Company::factory()->inGracePeriod()->create();

// Employee
$manager  = Employee::factory()->manager()->create();
$managerRh = Employee::factory()->managerRh()->create();
$employee = Employee::factory()->create();
$archived = Employee::factory()->archived()->create();
['employee' => $emp, 'token' => $token] = Employee::factory()->manager()->createWithToken();

// Attendance
$log = AttendanceLog::factory()->create();
$log = AttendanceLog::factory()->late()->create();
$log = AttendanceLog::factory()->withOvertime(2.5)->create();

// Absence
$absence = Absence::factory()->create();          // pending
$absence = Absence::factory()->approved()->create();
$absence = Absence::factory()->past()->create();

// Avance
$advance = SalaryAdvance::factory()->active()->create(); // en cours de remboursement
$advance = SalaryAdvance::factory()->repaid()->create();

// Paie
$payroll = Payroll::factory()->validated()->create();
$payroll = Payroll::factory()->withAdvanceDeduction(10000)->create();
```

---

## DÉCISIONS ARCHITECTURALES IMPORTANTES

| Sujet | Décision | Impact |
|-------|----------|--------|
| Hiérarchie | `manager_id` dans employees (auto-ref) | PAS supervisor_id |
| Statut employees | `status` VARCHAR : active/suspended/archived | PAS is_active BOOL |
| FCM tokens | Table `employee_devices` dédiée | PAS jsonb dans employees |
| gross_salary | Champ dans `payrolls` (calculé) | PAS dans employees (≠ salary_base) |
| salary_advances.status | pending/approved/**active**/repaid/rejected | 'active' = en remboursement |
| Timestamp pointage | Stocké UTC, calculé en timezone entreprise | Via Carbon::setTimezone() |
| Audit logs | Via Observer Eloquent (pas manuel) | Voir 09_tests_qualite/23_AUDIT_LOG_STRATEGY.md |

---

## CONNEXION DÉMO (après DemoCompanySeeder)

```
Manager principal :
  Email    : ahmed.benali@techcorp-algerie.dz
  Password : password123

Manager RH :
  Email    : fatima.meziane@techcorp-algerie.dz
  Password : password123

Employé :
  Email    : karim.aouad@techcorp-algerie.dz
  Password : password123

Super Admin :
  Email    : (valeur de SUPER_ADMIN_EMAIL dans .env)
  Password : (valeur de SUPER_ADMIN_PASSWORD dans .env)
```
