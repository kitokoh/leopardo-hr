# ARBORESCENCE COMPLÈTE — LEOPARDO RH
# Architecture 3-Tiers : Backend API / Frontend Web / Mobile Flutter
# Version 1.0 | Mars 2026

---

## VUE MACRO — 3 REPOSITORIES GIT

```
GitHub / GitLab
├── leopardo-rh-api/          ← Repo 1 : Backend (Laravel 11 + Vue.js via Inertia)
├── leopardo-rh-mobile/       ← Repo 2 : Mobile (Flutter — Android + iOS)
└── leopardo-rh-docs/         ← Repo 3 : Documentation (ce repo — conception uniquement)
```

Les 3 repos sont indépendants.
Le backend expose une API REST que le mobile ET le frontend web consomment.
Inertia.js est une exception : le frontend web est servi PAR le backend Laravel (pas un repo séparé).

---

## REPO 1 — leopardo-rh-api (Backend + Frontend Web)

```
leopardo-rh-api/
│
├── .env.example                          ← Variables d'environnement (jamais commiter .env)
├── .gitignore
├── .editorconfig
├── CHANGELOG.md                          ← Historique des changements API (Keep a Changelog)
├── README.md                             ← Setup local développeur
│
├── app/
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php              ← login, logout, refresh, forgot, reset
│   │   │   │
│   │   │   ├── Admin/                              ← Routes Super Admin uniquement
│   │   │   │   ├── CompanyController.php           ← CRUD entreprises + suspend/reactivate
│   │   │   │   ├── PlanController.php              ← CRUD plans tarifaires
│   │   │   │   ├── BillingController.php           ← Facturation manuelle, invoices
│   │   │   │   ├── LanguageController.php          ← Gestion des langues disponibles
│   │   │   │   └── HRModelController.php           ← Modèles RH par pays
│   │   │   │
│   │   │   ├── Tenant/                             ← Routes gestionnaire + employé
│   │   │   │   ├── EmployeeController.php          ← CRUD + import CSV + archive
│   │   │   │   ├── DepartmentController.php        ← CRUD départements
│   │   │   │   ├── PositionController.php          ← CRUD postes
│   │   │   │   ├── ScheduleController.php          ← CRUD plannings de travail
│   │   │   │   ├── SiteController.php              ← CRUD sites de travail
│   │   │   │   ├── DeviceController.php            ← CRUD appareils ZKTeco/QR
│   │   │   │   ├── AttendanceController.php        ← check-in, check-out, QR, biometric, today
│   │   │   │   ├── AbsenceTypeController.php       ← CRUD types d'absence
│   │   │   │   ├── AbsenceController.php           ← CRUD absences + approve + reject
│   │   │   │   ├── AdvanceController.php           ← CRUD avances + approve + reject
│   │   │   │   ├── TaskController.php              ← CRUD tâches + statut + commentaires
│   │   │   │   ├── ProjectController.php           ← CRUD projets
│   │   │   │   ├── EvaluationController.php        ← CRUD évaluations + auto-évaluation
│   │   │   │   ├── PayrollController.php           ← calculate, validate, pdf, status, export-bank
│   │   │   │   ├── ReportController.php            ← attendance, absences, payroll, performance
│   │   │   │   └── SettingsController.php          ← GET/PUT paramètres entreprise
│   │   │   │
│   │   │   └── Shared/
│   │   │       ├── NotificationController.php      ← liste notifs, marquer lu
│   │   │       └── ProfileController.php           ← profil personnel, mot de passe
│   │   │
│   │   ├── Middleware/
│   │   │   ├── TenantMiddleware.php                ← SET search_path TO company_{uuid}
│   │   │   ├── SuperAdminMiddleware.php            ← Vérifie role = super_admin
│   │   │   ├── ManagerMiddleware.php               ← Vérifie role = manager
│   │   │   ├── SetLocale.php                      ← App::setLocale(company.language)
│   │   │   └── CheckSubscription.php              ← Bloque si abonnement expiré
│   │   │
│   │   ├── Requests/                              ← 1 FormRequest par action (validation)
│   │   │   ├── Auth/
│   │   │   │   ├── LoginRequest.php
│   │   │   │   └── ResetPasswordRequest.php
│   │   │   ├── Admin/
│   │   │   │   ├── StoreCompanyRequest.php
│   │   │   │   └── StorePlanRequest.php
│   │   │   └── Tenant/
│   │   │       ├── StoreEmployeeRequest.php
│   │   │       ├── UpdateEmployeeRequest.php
│   │   │       ├── CheckInRequest.php
│   │   │       ├── CheckOutRequest.php
│   │   │       ├── StoreAbsenceRequest.php
│   │   │       ├── ApproveAbsenceRequest.php
│   │   │       ├── StoreAdvanceRequest.php
│   │   │       ├── StoreTaskRequest.php
│   │   │       ├── UpdateTaskStatusRequest.php
│   │   │       ├── CalculatePayrollRequest.php
│   │   │       ├── ValidatePayrollRequest.php
│   │   │       └── UpdateSettingsRequest.php
│   │   │
│   │   └── Resources/                             ← API Resources (transformation JSON)
│   │       ├── EmployeeResource.php
│   │       ├── EmployeeListResource.php           ← version allégée pour les listes
│   │       ├── AttendanceLogResource.php
│   │       ├── AbsenceResource.php
│   │       ├── SalaryAdvanceResource.php
│   │       ├── TaskResource.php
│   │       ├── TaskCommentResource.php
│   │       ├── PayrollResource.php
│   │       ├── PayrollSlipResource.php            ← version complète pour le bulletin
│   │       ├── NotificationResource.php
│   │       └── CompanyResource.php
│   │
│   ├── Models/
│   │   ├── Public/                                ← Modèles schéma public
│   │   │   ├── Company.php
│   │   │   ├── Plan.php
│   │   │   ├── SuperAdmin.php
│   │   │   ├── Invoice.php
│   │   │   ├── BillingTransaction.php
│   │   │   ├── Language.php
│   │   │   └── HRModelTemplate.php
│   │   │
│   │   └── Tenant/                                ← Modèles schéma tenant
│   │       ├── Employee.php
│   │       ├── Department.php
│   │       ├── Position.php
│   │       ├── Schedule.php
│   │       ├── Site.php
│   │       ├── Device.php
│   │       ├── EmployeeDevice.php                 ← Tokens FCM push
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
│   ├── Services/                                  ← Logique métier (jamais dans les Controllers)
│   │   ├── TenantService.php                      ← Création schéma PostgreSQL + migrations
│   │   ├── AttendanceService.php                  ← Calcul heures, statut, GPS, pénalités
│   │   ├── PayrollService.php                     ← Formule complète : brut → net
│   │   ├── AbsenceService.php                     ← Calcul jours ouvrables, solde, acquisition
│   │   ├── NotificationService.php                ← FCM push + Email + Notification DB
│   │   ├── BankExportService.php                  ← Génération CSV/XML virement bancaire
│   │   ├── ZKTecoService.php                      ← Intégration Push/Pull biométrie
│   │   ├── ReportService.php                      ← Génération rapports PDF + Excel
│   │   └── HRModelService.php                     ← Application modèles RH par pays
│   │
│   ├── Jobs/                                      ← Jobs asynchrones (Redis queue)
│   │   ├── GeneratePayslipPDF.php                 ← 1 job par employé, queue=payroll
│   │   ├── SendPayrollNotification.php
│   │   ├── SendBulkEmail.php
│   │   ├── SyncZKTeco.php                         ← Pull périodique ZKTeco (fallback Push)
│   │   └── PurgeOldAuditLogs.php                  ← Purge logs > 24 mois
│   │
│   ├── Observers/                                 ← Audit automatique via Observers
│   │   ├── EmployeeObserver.php
│   │   ├── AbsenceObserver.php
│   │   ├── PayrollObserver.php
│   │   └── SettingsObserver.php
│   │
│   ├── Policies/                                  ← Permissions RBAC Laravel
│   │   ├── EmployeePolicy.php
│   │   ├── AttendancePolicy.php
│   │   ├── AbsencePolicy.php
│   │   ├── AdvancePolicy.php
│   │   ├── TaskPolicy.php
│   │   └── PayrollPolicy.php
│   │
│   └── Console/
│       └── Commands/
│           ├── AttendanceCheckMissing.php          ← Alerte non pointés (toutes les heures)
│           ├── LeaveAccrueMonthly.php              ← Acquisition congés (1er du mois)
│           ├── ContractsExpiryAlerts.php           ← Alertes CDD (quotidien)
│           ├── TasksOverdueAlerts.php              ← Alertes tâches retard (lundi)
│           ├── SubscriptionExpiryAlerts.php        ← Alertes abonnements (1er du mois)
│           ├── PayrollDeductAdvances.php           ← Déduction avances (via PayrollService)
│           └── AuditLogsPurge.php                  ← Purge logs > 24 mois (mensuel)
│
├── database/
│   ├── migrations/
│   │   ├── public/                                ← Migrations schéma partagé (php artisan migrate)
│   │   │   ├── 2026_01_01_000001_create_plans_table.php
│   │   │   ├── 2026_01_01_000002_create_companies_table.php
│   │   │   ├── 2026_01_01_000003_create_super_admins_table.php
│   │   │   ├── 2026_01_01_000004_create_invoices_table.php
│   │   │   ├── 2026_01_01_000005_create_billing_transactions_table.php
│   │   │   ├── 2026_01_01_000006_create_languages_table.php
│   │   │   ├── 2026_01_01_000007_create_hr_model_templates_table.php
│   │   │   └── 2026_01_01_000008_add_company_id_to_personal_access_tokens.php
│   │   │
│   │   └── tenant/                               ← Via create_tenant_schema() PostgreSQL
│   │       └── (Toutes les tables tenant sont créées via 07_SCHEMA_SQL_COMPLET.sql)
│   │       └── (Voir TenantService::createTenantSchema())
│   │
│   ├── factories/
│   │   ├── Public/
│   │   │   ├── CompanyFactory.php
│   │   │   └── PlanFactory.php
│   │   └── Tenant/
│   │       ├── EmployeeFactory.php
│   │       ├── AttendanceLogFactory.php
│   │       └── AbsenceFactory.php
│   │
│   └── seeders/
│       ├── PublicSchemaSeeder.php                 ← Lance tous les seeders public
│       ├── PlanSeeder.php                         ← 3 plans (Starter, Business, Enterprise)
│       ├── LanguageSeeder.php                     ← FR, AR, TR, EN
│       └── HRModelSeeder.php                      ← DZ, MA, TN, FR, TR, SN, CI
│
├── routes/
│   ├── api.php                                   ← Auth (public) + webhook ZKTeco
│   ├── admin.php                                  ← Routes Super Admin (/admin/*)
│   └── tenant.php                                 ← Routes tenant (gestionnaire + employé)
│
├── resources/
│   ├── js/                                       ← Vue.js 3 + Inertia (frontend web)
│   │   ├── app.js                                ← Entry point Inertia
│   │   ├── Pages/
│   │   │   ├── Auth/
│   │   │   │   ├── Login.vue
│   │   │   │   └── ForgotPassword.vue
│   │   │   ├── Admin/
│   │   │   │   ├── Companies/
│   │   │   │   │   ├── Index.vue
│   │   │   │   │   └── Create.vue
│   │   │   │   ├── Plans/
│   │   │   │   │   └── Index.vue
│   │   │   │   └── Dashboard.vue
│   │   │   ├── Dashboard/
│   │   │   │   ├── Manager.vue                   ← Présence temps réel
│   │   │   │   └── AttendanceLive.vue
│   │   │   ├── Employees/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Create.vue
│   │   │   │   └── Show.vue
│   │   │   ├── Attendance/
│   │   │   │   ├── Calendar.vue
│   │   │   │   └── History.vue
│   │   │   ├── Absences/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Calendar.vue
│   │   │   ├── Tasks/
│   │   │   │   ├── Kanban.vue
│   │   │   │   └── Detail.vue
│   │   │   ├── Payroll/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Calculate.vue
│   │   │   │   └── Validate.vue
│   │   │   └── Settings/
│   │   │       ├── Company.vue
│   │   │       ├── HRConfig.vue
│   │   │       ├── Payroll.vue
│   │   │       └── Devices.vue
│   │   ├── Components/
│   │   │   ├── Layout/
│   │   │   │   ├── AppLayout.vue
│   │   │   │   ├── AdminLayout.vue
│   │   │   │   ├── Sidebar.vue
│   │   │   │   └── TopBar.vue
│   │   │   ├── UI/
│   │   │   │   ├── StatusBadge.vue
│   │   │   │   ├── DataTable.vue
│   │   │   │   ├── ConfirmModal.vue
│   │   │   │   └── LoadingSpinner.vue
│   │   │   └── Charts/
│   │   │       ├── AttendanceChart.vue
│   │   │       └── PayrollChart.vue
│   │   └── Layouts/                              ← Layouts Inertia
│   │       ├── AppLayout.vue
│   │       └── GuestLayout.vue
│   │
│   ├── views/
│   │   ├── app.blade.php                         ← Entry point Inertia (HTML shell)
│   │   └── pdf/
│   │       ├── payslip.blade.php                 ← Template bulletin de paie DomPDF
│   │       └── report.blade.php                  ← Template rapport PDF
│   │
│   └── lang/
│       ├── fr/
│       │   ├── messages.php                      ← Toutes les chaînes (voir config/lang_fr.php)
│       │   └── notifications.php                 ← Templates messages notification
│       ├── ar/
│       │   ├── messages.php
│       │   └── notifications.php
│       ├── tr/
│       │   ├── messages.php
│       │   └── notifications.php
│       └── en/
│           ├── messages.php
│           └── notifications.php
│
├── storage/
│   └── app/
│       ├── firebase-credentials.json             ← NE PAS COMMITER (dans .gitignore)
│       ├── payslips/                             ← Bulletins PDF générés
│       ├── exports/                              ← Fichiers export bancaire
│       └── reports/                             ← Rapports PDF + Excel
│
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   │   └── AuthTest.php
│   │   ├── Tenant/
│   │   │   ├── TenantIsolationTest.php           ← TEST CRITIQUE : isolation multi-tenant
│   │   │   ├── AttendanceTest.php
│   │   │   ├── AbsenceTest.php
│   │   │   ├── AdvanceTest.php
│   │   │   ├── PayrollTest.php
│   │   │   └── TaskTest.php
│   │   └── Admin/
│   │       └── CompanyTest.php
│   └── Unit/
│       ├── AttendanceServiceTest.php             ← Calcul heures, statut, pénalités
│       ├── PayrollServiceTest.php                ← Formule paie complète
│       └── AbsenceServiceTest.php                ← Jours ouvrables, solde
│
├── config/
│   ├── sanctum.php                              ← token_prefix, expiration 90j
│   ├── tenancy.php                              ← Configuration stancl/tenancy
│   └── leopardo.php                             ← Config custom (ZKTeco, limites, etc.)
│
└── .github/
    └── workflows/
        ├── tests.yml                            ← Lance Pest à chaque PR
        └── deploy.yml                           ← Déploiement auto sur o2switch (main branch)
```

---

## REPO 2 — leopardo-rh-mobile (Flutter)

```
leopardo-rh-mobile/
│
├── pubspec.yaml                                  ← Toutes les dépendances (voir Sprint 0 section 0-D.2)
├── l10n.yaml                                     ← Config i18n Flutter
├── analysis_options.yaml                         ← Règles lint Flutter
├── .env                                          ← Variables d'env Flutter (API_URL, etc.)
├── README.md
│
├── assets/
│   ├── images/
│   │   ├── logo.svg
│   │   └── logo_white.svg
│   ├── fonts/
│   │   └── (LeopardoSans-Regular/Medium/Bold.ttf)
│   └── l10n/
│       ├── app_fr.arb                            ← Français (référence)
│       ├── app_ar.arb                            ← Arabe (RTL)
│       ├── app_tr.arb                            ← Turc
│       └── app_en.arb                            ← Anglais
│
├── lib/
│   ├── main.dart                                 ← Entry point + ProviderScope + MaterialApp
│   │
│   ├── core/
│   │   ├── api/
│   │   │   ├── api_client.dart                  ← Dio singleton
│   │   │   ├── api_endpoints.dart               ← Toutes les URLs (constantes)
│   │   │   ├── auth_interceptor.dart            ← Ajout Bearer token automatique
│   │   │   ├── refresh_interceptor.dart         ← Refresh token si 401
│   │   │   └── api_exception.dart               ← Parsing des erreurs API
│   │   │
│   │   ├── auth/
│   │   │   ├── auth_provider.dart               ← Riverpod AuthNotifier
│   │   │   └── auth_state.dart
│   │   │
│   │   ├── i18n/
│   │   │   └── l10n_extension.dart              ← Extension context.l10n
│   │   │
│   │   ├── router/
│   │   │   ├── app_router.dart                  ← GoRouter avec guards auth
│   │   │   └── route_names.dart                 ← Constantes noms de routes
│   │   │
│   │   ├── storage/
│   │   │   ├── secure_storage.dart              ← flutter_secure_storage wrapper
│   │   │   └── local_cache.dart                 ← Cache local (bulletins, historique)
│   │   │
│   │   └── theme/
│   │       ├── leopardo_theme.dart              ← ThemeData (bleu marine + orange)
│   │       ├── leopardo_colors.dart             ← Constantes couleurs
│   │       └── leopardo_text_styles.dart        ← Styles typographiques
│   │
│   ├── features/
│   │   │
│   │   ├── auth/
│   │   │   ├── login_screen.dart
│   │   │   ├── forgot_password_screen.dart
│   │   │   └── change_password_screen.dart
│   │   │
│   │   ├── dashboard/
│   │   │   ├── employee_dashboard.dart          ← Accueil employé
│   │   │   └── manager_dashboard.dart           ← Présence temps réel
│   │   │
│   │   ├── attendance/
│   │   │   ├── attendance_repository.dart
│   │   │   ├── attendance_notifier.dart
│   │   │   ├── attendance_provider.dart
│   │   │   ├── attendance_screen.dart           ← Grand bouton pointage
│   │   │   ├── qr_scanner_screen.dart
│   │   │   ├── attendance_history_screen.dart
│   │   │   └── widgets/
│   │   │       ├── check_in_button.dart         ← Bouton appui long 1.5s
│   │   │       └── attendance_calendar.dart
│   │   │
│   │   ├── absences/
│   │   │   ├── absence_repository.dart
│   │   │   ├── absence_notifier.dart
│   │   │   ├── absence_provider.dart
│   │   │   ├── absence_list_screen.dart
│   │   │   ├── absence_request_screen.dart
│   │   │   ├── absence_detail_screen.dart
│   │   │   └── manager_absences_screen.dart     ← Vue gestionnaire
│   │   │
│   │   ├── tasks/
│   │   │   ├── task_repository.dart
│   │   │   ├── task_notifier.dart
│   │   │   ├── task_provider.dart
│   │   │   ├── task_list_screen.dart
│   │   │   ├── task_detail_screen.dart
│   │   │   ├── task_comment_thread.dart
│   │   │   └── manager_kanban_screen.dart       ← Vue Kanban gestionnaire
│   │   │
│   │   ├── advances/
│   │   │   ├── advance_repository.dart
│   │   │   ├── advance_notifier.dart
│   │   │   ├── advance_provider.dart
│   │   │   ├── advance_screen.dart
│   │   │   ├── advance_request_screen.dart
│   │   │   └── repayment_plan_screen.dart
│   │   │
│   │   ├── payroll/
│   │   │   ├── payroll_repository.dart
│   │   │   ├── payroll_notifier.dart
│   │   │   ├── payroll_provider.dart
│   │   │   ├── payslip_list_screen.dart
│   │   │   ├── payslip_viewer_screen.dart       ← flutter_pdfview
│   │   │   └── manager_payroll_screen.dart      ← Calcul + validation gestionnaire
│   │   │
│   │   ├── employees/
│   │   │   ├── employee_repository.dart
│   │   │   ├── employee_notifier.dart
│   │   │   ├── employee_provider.dart
│   │   │   ├── employee_list_screen.dart        ← Vue gestionnaire
│   │   │   └── employee_detail_screen.dart
│   │   │
│   │   ├── notifications/
│   │   │   ├── notification_repository.dart
│   │   │   ├── notification_screen.dart
│   │   │   └── fcm_handler.dart                 ← Deep linking depuis push
│   │   │
│   │   └── profile/
│   │       ├── profile_screen.dart
│   │       └── notification_preferences_screen.dart
│   │
│   ├── shared/
│   │   ├── models/                              ← Modèles Dart avec fromJson/toJson
│   │   │   ├── employee.dart
│   │   │   ├── attendance_log.dart
│   │   │   ├── absence.dart
│   │   │   ├── absence_type.dart
│   │   │   ├── salary_advance.dart
│   │   │   ├── task.dart
│   │   │   ├── task_comment.dart
│   │   │   ├── payroll.dart
│   │   │   ├── notification.dart
│   │   │   └── company_settings.dart
│   │   │
│   │   └── widgets/                             ← Composants réutilisables
│   │       ├── leopardo_button.dart
│   │       ├── status_badge.dart
│   │       ├── loading_shimmer.dart
│   │       ├── error_snackbar.dart
│   │       ├── confirm_dialog.dart
│   │       └── empty_state.dart
│   │
│   └── generated/
│       └── l10n/                                ← Généré par flutter gen-l10n (ne pas éditer)
│           └── app_localizations.dart
│
├── android/
│   ├── app/
│   │   ├── google-services.json                 ← NE PAS COMMITER (dans .gitignore)
│   │   └── src/main/AndroidManifest.xml        ← Permissions GPS, caméra, notifications
│   └── build.gradle
│
├── ios/
│   ├── Runner/
│   │   ├── GoogleService-Info.plist             ← NE PAS COMMITER (dans .gitignore)
│   │   └── Info.plist                          ← Permissions GPS, caméra, Face ID
│   └── Podfile
│
└── test/
    ├── unit/
    │   ├── models/
    │   │   └── attendance_log_test.dart
    │   └── services/
    │       └── date_utils_test.dart
    └── widget/
        └── attendance_screen_test.dart
```

---

## REPO 3 — leopardo-rh-docs (Documentation)

```
leopardo-rh-docs/
│
├── README.md                                     ← Point d'entrée unique (voir README existant)
│
├── dossier_de_conception/
│   ├── 01_PROMPT_MASTER_CLAUDE_CODE.md
│   ├── 02_API_CONTRATS.md                        ← À COMPLÉTER (41 endpoints manquants)
│   ├── 03_ERD_COMPLET.md
│   ├── 04_SPRINT_0_CHECKLIST.md
│   ├── 05_SEEDERS_ET_DONNEES_INITIALES.md
│   ├── 06_PROMPT_MASTER_JULES_FLUTTER.md
│   ├── 07_SCHEMA_SQL_COMPLET.sql
│   ├── 08_FEUILLE_DE_ROUTE.md
│   ├── 09_REGLES_METIER_COMPLETES.md
│   ├── 10_RBAC_COMPLET.md
│   ├── 11_MULTITENANCY_STRATEGY.md
│   ├── 12_SECURITY_SPEC_COMPLETE.md
│   ├── 13_USER_FLOWS_VALIDES.md
│   ├── 14_NOTIFICATION_TEMPLATES.md
│   └── 15_GUIDE_AJOUT_PAYS.md
│
├── wireframes/
│   └── leopardo_rh_wireframes.html               ← 20 écrans Flutter
│
├── config/
│   ├── .env.example
│   ├── lang_fr.php
│   ├── lang_ar.php
│   ├── lang_tr.php
│   ├── lang_en.php
│   ├── app_fr.arb
│   ├── app_ar.arb
│   ├── app_tr.arb
│   └── app_en.arb
│
├── prompts_execution/
│   ├── LIRE_EN_PREMIER.md
│   ├── backend/
│   │   ├── P01_INIT_INFRASTRUCTURE.md
│   │   ├── P02_AUTH_MULTITENANT.md
│   │   ├── P03_MODULE_EMPLOYES.md
│   │   ├── P04_MODULE_POINTAGE.md
│   │   ├── P05_MODULE_ABSENCES.md
│   │   ├── P06_MODULE_TACHES.md
│   │   ├── P07_MODULE_PAIE.md
│   │   ├── P08_MODULE_NOTIFICATIONS.md
│   │   ├── P09_SUPER_ADMIN.md
│   │   └── P10_DEPLOIEMENT_O2SWITCH.md
│   └── mobile/
│       ├── P01_INIT_FLUTTER.md
│       ├── P02_ECRAN_POINTAGE.md
│       ├── P03_ECRANS_EMPLOYE.md
│       ├── P04_ECRANS_GESTIONNAIRE.md
│       └── P05_BUILD_PUBLICATION.md
│
└── strategieAquisitionClient/
    └── Leopardo_RH_Funnel.pdf
```

---

## ARCHITECTURE 3-TIERS — SCHÉMA DE COMMUNICATION

```
┌─────────────────────────────────────────────────────────────────────┐
│                         TIER 3 — PRÉSENTATION                       │
│                                                                      │
│  ┌────────────────────┐              ┌──────────────────────────┐   │
│  │   MOBILE Flutter   │              │     WEB Vue.js/Inertia   │   │
│  │  Android + iOS     │              │   (servi par Laravel)    │   │
│  │                    │              │                          │   │
│  │ - App employé      │              │ - Dashboard gestionnaire │   │
│  │ - App gestionnaire │              │ - Interface Super Admin  │   │
│  │ - Riverpod         │              │ - Rapports + Paramètres  │   │
│  └────────┬───────────┘              └──────────┬───────────────┘   │
│           │ HTTPS + Bearer                       │ Cookie httpOnly   │
└───────────┼──────────────────────────────────────┼───────────────────┘
            │                                      │
┌───────────┼──────────────────────────────────────┼───────────────────┐
│           │          TIER 2 — LOGIQUE MÉTIER      │                   │
│           │                                      │                   │
│     ┌─────▼──────────────────────────────────────▼─────┐            │
│     │              Nginx (SSL + Rate Limit)             │            │
│     └─────────────────────────┬───────────────────────-┘            │
│                               │                                      │
│     ┌─────────────────────────▼─────────────────────────┐           │
│     │                Laravel 11 (PHP 8.3)               │           │
│     │                                                   │           │
│     │  Sanctum → TenantMiddleware → SetLocale           │           │
│     │  → Controllers → Services → Eloquent              │           │
│     │                                                   │           │
│     │  Jobs async → Redis Queue → Workers Supervisor    │           │
│     │  Scheduler → Cron → Commandes Artisan             │           │
│     └──┬───────────────────────────────────┬────────────┘           │
│        │                                   │                        │
└────────┼───────────────────────────────────┼────────────────────────┘
         │                                   │
┌────────┼───────────────────────────────────┼────────────────────────┐
│        │         TIER 1 — DONNÉES          │                        │
│        │                                   │                        │
│  ┌─────▼──────────────┐         ┌──────────▼─────────┐             │
│  │  PostgreSQL 16     │         │     Redis 7        │             │
│  │                    │         │                    │             │
│  │  Schéma public     │         │  Cache settings    │             │
│  │  Schéma tenant × N │         │  Queue jobs        │             │
│  │                    │         │  Sessions          │             │
│  └────────────────────┘         └────────────────────┘             │
│                                                                     │
│  ┌─────────────────────┐        ┌────────────────────┐             │
│  │  Storage (fichiers) │        │  Firebase FCM      │             │
│  │  PDF, exports       │        │  Push notifications │             │
│  └─────────────────────┘        └────────────────────┘             │
└─────────────────────────────────────────────────────────────────────┘

SERVICES EXTERNES
├── ZKTeco (lecteurs biométriques) → webhook POST vers Laravel
├── SMTP o2switch → envoi emails
└── Firebase FCM → push notifications Flutter
```
