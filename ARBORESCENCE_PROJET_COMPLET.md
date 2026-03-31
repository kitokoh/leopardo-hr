# ARBORESCENCE COMPLÈTE — LEOPARDO RH
# Monorepo : Backend Laravel 11 / Mobile Flutter / Frontend Vue.js
# Version 3.1 | Mars 2026 — MIS À JOUR et validé

---

## VUE MACRO

```
leopardo-rh/                          ← Racine du monorepo
├── .github/
│   └── workflows/
│       ├── deploy.yml                ← CI/CD déploiement o2switch (push sur main)
│       └── tests.yml                 ← Tests automatiques (PR vers develop/main)
├── .gitignore                        ← Exclut *.zip, vendor/, build/, .env
├── api/                              ← BACKEND Laravel 11 (à initialiser : CC-01)
├── mobile/                           ← MOBILE Flutter (à initialiser : JU-01)
├── docs/                             ← DOCUMENTATION complète
├── ARBORESCENCE_PROJET_COMPLET.md    ← Ce fichier
├── AUDIT_COMPLET_MANQUES.md          ← Historique des audits (référence)
├── CHANGELOG.md                      ← Historique des versions (Keep a Changelog)
└── README.md                         ← Guide d'entrée du projet
```

---

## DOSSIER `api/` — Backend Laravel 11

> À créer avec : `composer create-project laravel/laravel . --prefer-dist`
> Prompt de démarrage : `docs/PROMPTS_EXECUTION/backend/CC-01_INIT_LARAVEL.md`

```
api/
├── .env.example                      ← Variables d'environnement (ne jamais commiter .env)
├── README.md                         ← Instructions setup développeur
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php          ← login, logout, refresh, forgot, reset
│   │   │   ├── Admin/                          ← Routes Super Admin uniquement
│   │   │   │   ├── CompanyController.php
│   │   │   │   ├── PlanController.php
│   │   │   │   ├── BillingController.php
│   │   │   │   ├── LanguageController.php
│   │   │   │   └── HRModelController.php
│   │   │   ├── Tenant/                         ← Routes gestionnaire + employé
│   │   │   │   ├── EmployeeController.php
│   │   │   │   ├── DepartmentController.php
│   │   │   │   ├── PositionController.php
│   │   │   │   ├── ScheduleController.php
│   │   │   │   ├── SiteController.php
│   │   │   │   ├── DeviceController.php
│   │   │   │   ├── AttendanceController.php
│   │   │   │   ├── AbsenceTypeController.php
│   │   │   │   ├── AbsenceController.php
│   │   │   │   ├── AdvanceController.php
│   │   │   │   ├── TaskController.php
│   │   │   │   ├── ProjectController.php
│   │   │   │   ├── EvaluationController.php
│   │   │   │   ├── PayrollController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── OnboardingController.php    ← GET /onboarding/status
│   │   │   │   └── SettingsController.php
│   │   │   ├── Public/                         ← Routes sans auth (onboarding Trial)
│   │   │   │   └── RegisterController.php      ← POST /public/register
│   │   │   └── Shared/
│   │   │       ├── NotificationController.php
│   │   │       └── ProfileController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── TenantMiddleware.php            ← SET search_path + injecte company
│   │   │   ├── SuperAdminMiddleware.php
│   │   │   ├── ManagerMiddleware.php
│   │   │   ├── PlanLimitMiddleware.php         ← Vérifie limites du plan (employees, etc.)
│   │   │   ├── SetLocale.php                   ← App::setLocale(company.language)
│   │   │   └── CheckSubscription.php           ← Bloque si abonnement expiré
│   │   │
│   │   ├── Requests/                           ← 1 FormRequest par action
│   │   │   ├── Auth/
│   │   │   ├── Admin/
│   │   │   └── Tenant/
│   │   │
│   │   └── Resources/                          ← API Resources (JSON transform)
│   │       ├── EmployeeResource.php
│   │       ├── EmployeeListResource.php
│   │       ├── AttendanceLogResource.php
│   │       ├── AbsenceResource.php
│   │       ├── SalaryAdvanceResource.php
│   │       ├── TaskResource.php
│   │       ├── PayrollResource.php
│   │       ├── PayrollSlipResource.php
│   │       ├── NotificationResource.php
│   │       └── CompanyResource.php
│   │
│   ├── Models/
│   │   ├── Public/                             ← Schéma public PostgreSQL
│   │   │   ├── Company.php
│   │   │   ├── Plan.php
│   │   │   ├── SuperAdmin.php
│   │   │   ├── Invoice.php
│   │   │   ├── Language.php
│   │   │   ├── UserLookup.php
│   │   │   └── HRModelTemplate.php
│   │   └── Tenant/                             ← Schéma tenant
│   │       ├── Employee.php
│   │       ├── Department.php
│   │       ├── Position.php
│   │       ├── Schedule.php
│   │       ├── Site.php
│   │       ├── Device.php
│   │       ├── EmployeeDevice.php
│   │       ├── AttendanceLog.php
│   │       ├── AbsenceType.php
│   │       ├── Absence.php
│   │       ├── LeaveBalanceLog.php
│   │       ├── SalaryAdvance.php
│   │       ├── Project.php
│   │       ├── Task.php
│   │       ├── TaskComment.php
│   │       ├── Evaluation.php
│   │       ├── Payroll.php
│   │       ├── PayrollExportBatch.php
│   │       ├── PayrollExportItem.php
│   │       ├── CompanySetting.php
│   │       ├── AuditLog.php
│   │       └── Notification.php
│   │
│   ├── Services/                               ← Logique métier (jamais dans Controllers)
│   │   ├── TenantService.php                   ← Création schéma PostgreSQL + migrations
│   │   ├── EmployeeService.php                 ← CRUD + sync user_lookups (toujours en transaction)
│   │   ├── AttendanceService.php               ← Calcul heures, statut, GPS, pénalités
│   │   ├── PayrollService.php                  ← Formule brut → net
│   │   ├── AbsenceService.php                  ← Calcul jours, solde, acquisition
│   │   ├── NotificationService.php             ← FCM + Email + Notification DB + SSE
│   │   ├── BankExportService.php               ← CSV/XML virement bancaire
│   │   ├── ZKTecoService.php                   ← Intégration biométrie
│   │   ├── OnboardingService.php               ← Progression onboarding Trial
│   └── SubscriptionService.php             ← Crons suspend/purge comptes expirés
│   │   └── ReportService.php                   ← PDF + Excel
│   │
│   └── Scopes/
│       └── CompanyScope.php                    ← Global Scope WHERE company_id (mode shared)
│
├── database/
│   ├── migrations/
│   │   ├── public/                             ← Migrations schéma public (run once)
│   │   └── tenant/                             ← Migrations schéma tenant (run par TenantService)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── PlanSeeder.php
│       ├── LanguageSeeder.php
│       └── HRModelSeeder.php                   ← 7 pays : DZ, MA, TN, FR, TR, SN, CI
│
├── lang/
│   ├── fr/
│   │   ├── auth.php
│   │   ├── errors.php
│   │   ├── notifications.php
│   │   └── pdf.php
│   ├── ar/  (même structure)
│   ├── en/  (même structure)
│   └── tr/  (même structure)
│
├── routes/
│   ├── api.php                                 ← Toutes les routes API v1
│   └── web.php                                 ← Landing page + SPA Vue.js
│
└── tests/
    ├── Unit/
    │   ├── PayrollServiceTest.php
    │   ├── AttendanceServiceTest.php
    │   └── AbsenceServiceTest.php
    └── Feature/
        ├── Auth/
        ├── TenantIsolationTest.php             ← TEST BLOQUANT — isolation A vs B
        ├── PlanLimitTest.php
        ├── PublicRegisterTest.php
        └── SSENotificationTest.php
```

---

## DOSSIER `mobile/` — Flutter

> À créer avec : `flutter create . --org com.leopardo --project-name leopardo_rh`
> Prompt de démarrage : `docs/PROMPTS_EXECUTION/mobile/JU-01_A_JU-04_FLUTTER.md`

```
mobile/
├── README.md
├── assets/
│   └── mock/                                   ← Données JSON pour dev sans API
│       ├── mock_auth_login.json
│       ├── mock_auth_me.json
│       ├── mock_attendance_today_A_not_checked.json
│       ├── mock_attendance_today_B_checked_in.json
│       ├── mock_attendance_history.json
│       ├── mock_absences.json
│       ├── mock_payroll.json
│       ├── mock_tasks.json
│       └── mock_notifications.json
│
├── lib/
│   ├── main.dart
│   ├── l10n/                                   ← Fichiers ARB traductions
│   │   ├── app_fr.arb
│   │   ├── app_ar.arb
│   │   ├── app_en.arb
│   │   └── app_tr.arb
│   ├── core/
│   │   ├── api/                                ← Dio client + interceptors
│   │   ├── mock/                               ← Mock service (bascule JSON ↔ API)
│   │   └── router/                             ← GoRouter
│   ├── features/
│   │   ├── auth/
│   │   ├── attendance/
│   │   ├── absences/
│   │   ├── advances/
│   │   ├── tasks/
│   │   ├── payroll/
│   │   └── notifications/
│   └── shared/
│       ├── models/                             ← Classes Dart (voir 16_MODELES_DART/)
│       └── widgets/
│
└── test/
    ├── unit/
    └── widget/
```

---

## DOSSIER `docs/` — Documentation

```
docs/
├── README.md                                   ← Index de la documentation
│
├── dossierdeConception/
│   ├── 00_docs/                                ← CDC, DCT originaux + notes initiales
│   ├── 00_vision_marche/                       ← Vision, marché, concurrents
│   ├── 01_API_CONTRATS_COMPLETS/               ← ✅ SOURCE DE VÉRITÉ API (70 endpoints)
│   ├── 02_personas/                            ← Personas et User Stories
│   ├── 03_modele_economique/                   ← Plans tarifaires + stratégie acquisition
│   ├── 04_architecture_erd/                    ← ✅ ERD v2.0 (source de vérité)
│   ├── 05_regles_metier/                       ← Règles métier + guide pays
│   ├── 07_securite_rbac/                       ← RBAC, Sécurité, PlanLimit, CheckSubscription (grâce 3j)
│   ├── 08_multitenancy/                        ← Stratégie shared/schema + spec TenantService (7 étapes)
│   ├── 09_tests_qualite/                       ← Stratégie tests + Erreurs et logs
│   ├── 10_deploiement_cicd/                    ← Git workflow + Sauvegardes
│   ├── 11_ux_wireframes/                       ← Wireframes HTML + User Flows + Onboarding
│   ├── 12_notifications/                       ← Templates push/email + SSE
│   ├── 13_i18n/                                ← ✅ Stratégie i18n (fr/ar/en/tr + RTL)
│   ├── 15_CICD_ET_CONFIG/                      ← nginx, supervisor, .env.example (référence)
│   ├── 16_MODELES_DART/                        ← Classes Dart Flutter
│   ├── 17_MOCK_JSON/                           ← Documentation mocks (JSON dans mobile/assets/mock/)
│   ├── 18_schemas_sql/                         ← ✅ SQL + Seeders (sources de vérité)
│   └── 20_templates_pdf/                       ← Template bulletin de paie (Blade/DomPDF) + formats export bancaire
│
└── PROMPTS_EXECUTION/
    ├── ORCHESTRATION/
    │   └── ORCHESTRATION_MAITRE.md             ← Tableau de bord — mettre à jour après chaque tâche
    ├── backend/
    │   ├── CC-01_INIT_LARAVEL.md
    │   ├── CC-02_MODULE_AUTH.md
    │   └── CC-03_A_CC-06_MODULES.md
    ├── mobile/
    │   └── JU-01_A_JU-04_FLUTTER.md
    ├── frontend/
    │   └── CU-01_ET_AGENTS.md
    └── 99_prompts_execution/
        └── 01_PROMPT_MASTER_CLAUDE_CODE.md     ← Prompt master complet (501 lignes)
```

---

## NOTES IMPORTANTES

### Ce qui N'est PAS dans ce repo (généré à l'init)
- `api/vendor/` — généré par `composer install`
- `api/node_modules/` — généré par `npm install`
- `mobile/.dart_tool/` — généré par `flutter pub get`
- `mobile/build/` — généré par `flutter build`
- `api/.env` — jamais commité (copier `.env.example`)

### Convention de commits
```
feat(scope): description courte
fix(scope): description courte

Scopes : auth | employees | attendance | payroll | advances
         absences | tasks | billing | tenant | ci | docs | mobile
```

### Sources de vérité — Ordre de priorité en cas de conflit
1. `18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` (SQL fait loi)
2. `04_architecture_erd/03_ERD_COMPLET.md` (structure logique)
3. `01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` (contrats API)
