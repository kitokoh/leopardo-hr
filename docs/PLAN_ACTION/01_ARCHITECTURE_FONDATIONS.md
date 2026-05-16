# 01 — ARCHITECTURE & FONDATIONS

**Objectif :** Rendre la base Leopardo RH plus solide, scalable et mieux engineeree qu'ERPNext, pour supporter l'ajout de modules sans dette technique.

---

## 1. Structure DDD pour les nouveaux modules

Le module Cameras est deja structure en DDD. Tous les nouveaux modules doivent suivre ce pattern.

```
api/app/Modules/{NomModule}/
    Domain/
        Models/          # Eloquent models du module
        ValueObjects/    # Objets valeur (Money, DateRange, etc.)
        Events/          # Domain events (PayrollValidated, etc.)
        Enums/           # Enums PHP 8.1+ (PayrollStatus, etc.)
        Exceptions/      # Exceptions metier (InsufficientLeaveBalance, etc.)
    Application/
        DTOs/            # Data Transfer Objects (request -> action)
        Actions/         # Business Actions (CreatePayrollAction, etc.)
        Queries/         # Read-model queries complexes
        Listeners/       # Event listeners
    Infrastructure/
        Repositories/    # Repository implementations (si necessaire)
        Services/        # Services externes (Traccar, LLM, PSP, etc.)
        Exports/         # Exports CSV/PDF/SEPA
    Interfaces/
        Api/V1/
            Controllers/ # Controllers REST
            Requests/    # FormRequest validation
            Resources/   # API Resources (JSON serialization)
```

### Taches

- [x] **T-ARCH-01** : Creer le template de module vide dans `stubs/module-template/` — **FAIT** (`api/stubs/module-template/` existe avec Application, Domain, Infrastructure, Interfaces)
- [ ] **T-ARCH-02** : Migrer progressivement les controllers existants (Payroll, Absence, etc.) vers la structure DDD — commencer par les nouveaux modules, migrer l'existant quand on y touche
- [x] **T-ARCH-03** : Creer une commande Artisan `php artisan make:module {name}` qui genere la structure — **FAIT** (`app/Console/Commands/MakeModuleCommand.php` + test unit)

---

## 2. Event System (Domain Events)

Chaque action metier importante doit emettre un event Laravel. Cela permet :
- L'audit trail automatique
- Les notifications
- La couche IA (ecoute les events pour construire le contexte)
- Les webhooks futurs

### Events a implementer

| Event | Declencheur | Listeners |
|-------|------------|-----------|
| `EmployeeCreated` | POST /employees | AuditLog, Notification, Webhook |
| `EmployeeArchived` | POST /employees/{id}/archive | AuditLog, Notification |
| `AttendanceCheckedIn` | POST /attendance/check-in | AuditLog, AnomalyCheck |
| `AttendanceCheckedOut` | POST /attendance/check-out | AuditLog, AnomalyCheck |
| `AbsenceRequested` | POST /absences | AuditLog, Notification, ManagerAlert |
| `AbsenceApproved` | PUT /absences/{id}/approve | AuditLog, Notification, LeaveBalanceUpdate |
| `AbsenceRejected` | PUT /absences/{id}/reject | AuditLog, Notification |
| `PayrollValidated` | PUT /payrolls/{id}/validate | AuditLog, Notification, LockPayroll |
| `SalaryAdvanceApproved` | PUT /salary-advances/{id}/approve | AuditLog, Notification, PayrollDeduction |
| `ContractExpiringSoon` | Scheduled job (daily) | Notification, ManagerAlert |
| `EvaluationCompleted` | PUT /evaluations/{id} | AuditLog, Notification |

### Taches

- [x] **T-ARCH-04** : Creer `app/Events/` avec les domain events ci-dessus — **FAIT** (EmployeeCreated, EmployeeArchived, AttendanceCheckedIn/Out, AbsenceRequested/Approved/Rejected, PayrollValidated)
- [x] **T-ARCH-05** : Creer `app/Listeners/AuditLogger.php` — ecoute tous les events et ecrit dans `audit_logs` — **FAIT** (`app/Listeners/AuditLogger.php`)
- [x] **T-ARCH-06** : Creer `app/Listeners/WebhookDispatcher.php` — envoie les events aux URLs configurees par le tenant — **FAIT** (`app/Listeners/WebhookListener.php` + `app/Services/WebhookDispatcher.php`)
- [x] **T-ARCH-07** : Creer la table `audit_logs` (migration) — **FAIT** (`2026_05_10_000001_create_audit_logs_table.php`)
- [x] **T-ARCH-08** : Creer la table `webhook_endpoints` — **FAIT** (`2026_05_10_000002_create_webhook_tables.php`)

---

## 3. API Versioning

L'API actuelle est en `/api/v1/`. Pour la stabilite long terme :

### Regles

- `/api/v1/` est gele — pas de breaking changes
- Les nouveaux modules sont ajoutes en `/api/v1/` (extension, pas remplacement)
- Si un breaking change est necessaire, creer `/api/v2/` pour la route concernee
- L'en-tete `Accept: application/vnd.leopardo.v1+json` est accepte en alternative au prefixe URL

### Taches

- [ ] **T-ARCH-09** : Ajouter le middleware `ApiVersion` qui lit le header Accept et resout la version
- [x] **T-ARCH-10** : Documenter la politique de deprecation dans `docs/api/VERSIONING.md` — **FAIT**

---

## 4. Conventions de code

### PHP Backend

```
- PSR-12 strict (Laravel Pint deja configure)
- PHP 8.4 features : enums, readonly properties, match, named args
- Types stricts : declare(strict_types=1) partout
- Return types explicites sur toutes les methodes publiques
- Pas de `Any`, `mixed` sauf cas justifie
- FormRequest pour TOUTE validation d'entree
- API Resource pour TOUTE serialisation de sortie
- Policy pour TOUTE autorisation
- Action classes pour la logique metier (pas dans les controllers)
```

### Flutter Mobile

```
- Bloc pattern pour state management
- Repository pattern pour data access
- Freezed/json_serializable pour les modeles
- Dart strict analysis (tous les lints actives)
- Widgets reutilisables dans lib/widgets/
- Services dans lib/services/
- Modeles dans lib/models/
```

### Next.js Web

```
- App Router (pas Pages Router)
- Server Components par defaut, Client Components avec 'use client' explicit
- Tailwind CSS + Shadcn/UI
- TypeScript strict
- Zod pour validation
- React Query (TanStack) pour data fetching
- Composants dans src/components/
- API layer dans src/lib/api/
```

### Taches

- [x] **T-ARCH-11** : Creer `.editorconfig` a la racine — **FAIT** (`.editorconfig` existe avec UTF-8, LF)
- [x] **T-ARCH-12** : Ajouter `phpstan.neon` avec level 6 minimum pour les nouveaux modules — **FAIT** (`phpstan.neon` + `phpstan-baseline.neon` + CI diff-gate)
- [x] **T-ARCH-13** : Creer `CONVENTIONS.md` a la racine avec les regles ci-dessus — **FAIT**

---

## 5. Base de donnees — Scalabilite

### Index manquants a ajouter

```sql
-- Performance queries frequentes
CREATE INDEX idx_attendance_logs_company_date ON attendance_logs(company_id, date);
CREATE INDEX idx_attendance_logs_employee_date ON attendance_logs(employee_id, date);
CREATE INDEX idx_employees_company_status ON employees(company_id, status);
CREATE INDEX idx_absences_company_status ON absences(company_id, status);
CREATE INDEX idx_payrolls_company_period ON payrolls(company_id, period_start, period_end);
CREATE INDEX idx_audit_logs_company_created ON audit_logs(company_id, created_at);
```

### Partitioning pour les gros volumes

Quand un client depasse 100K pointages :

```sql
-- Partitionner attendance_logs par mois
CREATE TABLE attendance_logs (
    ...
) PARTITION BY RANGE (date);
```

A activer uniquement en mode Schema (Enterprise). Pas pour le mode Shared.

### Taches

- [x] **T-ARCH-14** : Creer la migration d'index de performance — **FAIT** (`2026_05_10_000008_add_performance_indexes.php`)
- [x] **T-ARCH-15** : Documenter la strategie de partitioning dans `docs/architecture/PARTITIONING.md` — **FAIT**
- [x] **T-ARCH-16** : Ajouter des query scopes optimises sur les modeles — **FAIT** (scopes `active()`, `forPeriod()` trouves sur Contract, EmployeeLoan, Feature, Project, SalaryAdvance)

---

## 6. Queue & Workers

Le MVP utilise `sync` driver. Pour la production a 100+ clients :

### Migration vers async

```
QUEUE_CONNECTION=redis (ou database si pas de Redis)
```

Jobs a mettre en queue :

| Job | Priorite | Queue |
|-----|----------|-------|
| `GeneratePayrollPDF` | default | payroll |
| `SendNotificationEmail` | low | notifications |
| `DispatchWebhook` | default | webhooks |
| `ProcessAnomalyDetection` | default | attendance |
| `GenerateMonthlyReport` | low | reports |
| `SyncTraccarDevices` | default | tracking |
| `ProcessAIRequest` | default | ai |

### Taches

- [x] **T-ARCH-17** : Creer les Job classes dans `app/Jobs/` — **FAIT** (`app/Jobs/DispatchWebhook.php` implementant ShouldQueue)
- [x] **T-ARCH-18** : Configurer `config/queue.php` avec les queues nommees — **FAIT** (`config/queue.php` configure)
- [ ] **T-ARCH-19** : Ajouter le workflow CI pour tester les jobs
- [x] **T-ARCH-20** : Documenter le setup worker dans `DEPLOYMENT_GUIDE.md` (Render Worker) — **FAIT**

---

## 7. Cache Strategy

### Ce qu'il faut cacher

| Donnee | TTL | Invalidation |
|--------|-----|-------------|
| Company settings | 1h | On CompanySetting update |
| Feature flags | 30min | On feature toggle |
| Employee count per company | 15min | On employee create/archive |
| Department hierarchy | 1h | On department change |
| Leave balances | 5min | On absence approve/reject |
| i18n catalogs | 24h | On translation update |

### Taches

- [ ] **T-ARCH-21** : Migrer vers Redis cache en production (garder file pour dev)
- [ ] **T-ARCH-22** : Implementer cache tags par company_id pour invalidation granulaire
- [ ] **T-ARCH-23** : Ajouter les decorateurs de cache sur les services concernes
