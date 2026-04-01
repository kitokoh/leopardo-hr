# ✅ SUIVI DES PROMPTS D'EXÉCUTION — LEOPARDO RH
# Cochez chaque item après accomplissement — NE JAMAIS SAUTER UNE ÉTAPE
# Version 4.0 | 31 Mars 2026

---

## COMMENT UTILISER CE FICHIER

```
AVANT session  → Lire la tâche + vérifier les prérequis ✓
PENDANT        → L'agent IA exécute et coche en temps réel
APRÈS          → Remplir le LOG dans JOURNAL_DE_BORD.md
COMMIT         → git commit -m "docs: SUIVI_PROMPTS session CC-XX done"
```

---

## ══════════════════════════════════════════════
## SPRINT 0 — INFRASTRUCTURE (Chef de projet — AVANT toute session IA)
## ══════════════════════════════════════════════

```
[ ] 1. Domaine leopardo-rh.com acheté (Namecheap / OVH)
[ ] 2. DNS api.leopardo-rh.com → IP o2switch
[ ] 3. DNS app.leopardo-rh.com → IP o2switch
[ ] 4. DNS admin.leopardo-rh.com → IP o2switch
[ ] 5. SSL Let's Encrypt activé (cPanel o2switch)
[ ] 6. PostgreSQL configuré :
        CREATE DATABASE leopardo_db;
        CREATE USER leopardo_user WITH ENCRYPTED PASSWORD '...';
        GRANT ALL PRIVILEGES ON DATABASE leopardo_db TO leopardo_user;
        CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
[ ] 7. Redis activé et accessible (redis-cli ping → PONG)
[ ] 8. Projet Firebase créé (console.firebase.google.com)
[ ] 9. FCM activé dans Firebase
[ ] 10. google-services.json téléchargé (Flutter Android)
[ ] 11. GoogleService-Info.plist téléchargé (Flutter iOS)
[ ] 12. Repo GitHub créé (monorepo gestionemployerBackend)
[ ] 13. Secrets GitHub Actions configurés :
        O2SWITCH_HOST, O2SWITCH_USER, O2SWITCH_SSH_KEY, O2SWITCH_PORT
        DB_PASSWORD_PROD, FIREBASE_CREDENTIALS
[ ] 14. Comptes stores : Google Play (25$) + Apple Developer (99$/an)
```

**Validation Sprint 0** : SSH répond + PostgreSQL accessible + Redis PONG
**→ SANS CE SPRINT 0 COMPLET, NE PAS DÉMARRER CC-01**

---

## ══════════════════════════════════════════════
## CC-01 — INIT LARAVEL (Session Backend #1)
## ══════════════════════════════════════════════

**Agent** : Claude Code
**Prompt** : `docs/PROMPTS_EXECUTION/backend/CC-01_INIT_LARAVEL.md`
**Durée** : 4-6 heures | **Prérequis** : Sprint 0 ✅

### Setup projet
```
[ ] composer create-project laravel/laravel leopardo-rh-api (dans api/)
[ ] Packages installés :
    composer require laravel/sanctum
    composer require barryvdh/laravel-dompdf
    composer require kreait/laravel-firebase
    composer require laravel/horizon
    composer require spatie/laravel-permission (optionnel si RBAC via code)
    composer require laravel/telescope --dev
    composer require pestphp/pest --dev
    composer require pestphp/pest-plugin-laravel --dev
```

### Configuration
```
[ ] .env créé depuis .env.example (docs/dossierdeConception/15_CICD_ET_CONFIG/)
[ ] config/database.php — connexion pgsql vérifiée
[ ] Sanctum configuré (config/sanctum.php, middleware)
[ ] Horizon configuré (config/horizon.php)
```

### Base de données
```
[ ] 07_SCHEMA_SQL_COMPLET.sql exécuté : psql -U leopardo_user -d leopardo_db -f ...
[ ] Migrations Laravel créées (équivalent du SQL, pour migrate:rollback futur)
[ ] Seeders exécutés :
    [ ] PlanSeeder (Starter, Business, Enterprise avec toutes les features)
    [ ] LanguageSeeder (fr, ar, en, tr)
    [ ] HrModelSeeder (DZ, MA, TN, FR, TR)
    [ ] SuperAdminSeeder (premier compte admin)
[ ] Factories créées (Employee, Company, Department, Absence, Payroll...)
```

### Middlewares
```
[ ] TenantMiddleware (SET search_path dynamique) créé
[ ] SuperAdminMiddleware (guard dédié super_admins) créé
[ ] CheckSubscription middleware (grâce 3 jours) créé
[ ] PlanLimitMiddleware (max employés par plan) créé
[ ] Tous enregistrés dans bootstrap/app.php
```

### Config serveur
```
[ ] Nginx config copiée (nginx-api.conf → /etc/nginx/sites-available/)
[ ] Supervisor config copiée (leopardo-horizon.supervisor.conf)
[ ] php artisan horizon:install + php artisan horizon:publish
[ ] php artisan storage:link
```

### Tests
```
[ ] php artisan test → 0 erreur
[ ] GET /api/health → 200 {"status":"ok","version":"1.0.0"}
[ ] SELECT name FROM plans → Starter, Business, Enterprise
[ ] SELECT code FROM languages → fr, ar, en, tr
```

**Commit** : `feat(infra): init Laravel 11 with PostgreSQL multi-tenant, Redis, Horizon, all middlewares, seeders`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## JU-01 — INIT FLUTTER (Session Mobile #1 — PARALLÈLE à CC-01)
## ══════════════════════════════════════════════

**Agent** : Jules / Claude Code
**Prompt** : `docs/PROMPTS_EXECUTION/mobile/JU-01_A_JU-04_FLUTTER.md` (section JU-01)
**Durée** : 4-6 heures | **Prérequis** : Sprint 0 ✅ (Firebase au minimum)

```
[ ] flutter create leopardo_rh_mobile (dans mobile/)
[ ] Packages installés :
    flutter_riverpod, riverpod_annotation, go_router
    dio, flutter_secure_storage
    firebase_core, firebase_messaging
    local_auth, geolocator, image_picker
    intl, flutter_localizations
[ ] google-services.json placé dans android/app/
[ ] GoogleService-Info.plist placé dans ios/Runner/
[ ] GoRouter configuré (toutes les routes)
[ ] Riverpod providers configurés
[ ] DioService créé (intercepteurs auth + grace period header)
[ ] Mocks copiés (mobile/assets/mock/*.json vers assets de l'app)
[ ] Modèles Dart créés (voir 16_MODELES_DART/20_MODELES_DART_COMPLET.md) :
    Employee, AttendanceLog, Absence, SalaryAdvance, Task, Payroll, Notification
[ ] ARB files créés : app_fr.arb, app_ar.arb, app_en.arb, app_tr.arb
[ ] RTL arabe configuré (MaterialApp, GoRouter)
[ ] Écran Login (mock)
[ ] Écran Dashboard employé (mock)
[ ] flutter run → app démarre sans erreur
[ ] Login mock → dashboard affiché
[ ] Langue arabe → RTL actif
```

**Commit** : `feat(mobile): init Flutter with Riverpod, GoRouter, mock data, RTL Arabic`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-02 — MODULE AUTH (Session Backend #2)
## ══════════════════════════════════════════════

**Agent** : Claude Code
**Prompt** : `docs/PROMPTS_EXECUTION/backend/CC-02_MODULE_AUTH.md`
**Durée** : 4-5 heures | **Prérequis** : CC-01 ✅

```
[ ] POST /api/v1/public/register (auto-onboarding Trial — TenantService 7 étapes)
[ ] POST /api/v1/auth/login (lookup user_lookups → switch schéma → Sanctum)
[ ] POST /api/v1/auth/logout
[ ] GET  /api/v1/auth/me
[ ] POST /api/v1/auth/refresh
[ ] POST /api/v1/auth/forgot-password
[ ] POST /api/v1/auth/reset-password
[ ] POST /api/v1/auth/device/fcm
[ ] DELETE /api/v1/auth/device/fcm

[ ] TenantService::createTenant() — 7 étapes en DB::transaction()
[ ] Models : Employee (Tenant), EmployeeDevice, UserLookup (Public), Company (Public)
[ ] Resources : EmployeeResource, CompanyResource
[ ] Email de bienvenue (queue)

[ ] Tests :
    [ ] POST /auth/login bonnes credentials → token
    [ ] POST /auth/login mauvais password → 401
    [ ] Token A ne voit pas données B → 404 (TenantIsolationTest)
    [ ] POST /public/register → company + user_lookup créés
    [ ] Compte suspendu → 403
```

**Commit** : `feat(auth): multi-tenant login, public register, FCM device management`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-03 — CONFIG ENTREPRISE (Session Backend #3)
## ══════════════════════════════════════════════

**Prérequis** : CC-02 ✅

```
[ ] CRUD /departments (avec manager_id)
[ ] CRUD /positions
[ ] CRUD /schedules (planning horaire, jours travaillés)
[ ] CRUD /sites (géolocalisation, rayon GPS)
[ ] CRUD /devices (ZKTeco + QR)
[ ] POST /devices/{id}/rotate-token
[ ] GET/PUT /settings (paramètres entreprise)
[ ] Tests CRUD + RBAC (seul principal/rh peut créer)
```

**Commit** : `feat(config): company configuration — departments, schedules, sites, devices`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-04 — EMPLOYÉS (Session Backend #4)
## ══════════════════════════════════════════════

**Prérequis** : CC-03 ✅

```
[ ] GET  /employees (liste paginée, filtres status/department)
[ ] POST /employees (PlanLimitMiddleware actif)
[ ] GET  /employees/{id}
[ ] PUT  /employees/{id}
[ ] DELETE /employees/{id} (soft delete → status=archived)
[ ] POST /employees/import (CSV, batch, PlanLimit)
[ ] GET  /employees/{id}/payslips
[ ] GET/PUT /profile (employé connecté)
[ ] POST /profile/photo
[ ] PUT  /profile/password
[ ] Tests PlanLimit (Starter bloqué à 20)
```

**Commit** : `feat(employees): CRUD employees, CSV import, profile, plan limit enforcement`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-05 — POINTAGE (Session Backend #5)
## ══════════════════════════════════════════════

**Prérequis** : CC-04 ✅

```
[ ] POST /attendance/check-in (horodatage serveur OBLIGATOIRE)
[ ] POST /attendance/check-out
[ ] GET  /attendance/today
[ ] GET  /attendance/history (filtres date/employee)
[ ] POST /attendance/manual (correction manager)
[ ] GET  /attendance/stats (KPIs : taux présence, retards moyens, HS)
[ ] AttendanceService :
    [ ] Calcul hours_worked (checkout - checkin)
    [ ] Calcul late_minutes (check_in - schedule.start_time en timezone entreprise)
    [ ] Calcul overtime_hours (threshold journalier ET hebdomadaire)
    [ ] Règle timezone — OBLIGATION conversion avant calcul
[ ] Tests : pas de double check-in, horodatage serveur, calcul HS correct
```

**Commit** : `feat(attendance): check-in/out with server timestamp, overtime, timezone-aware`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-06 — ABSENCES (Session Backend #6)
## ══════════════════════════════════════════════

**Prérequis** : CC-05 ✅

```
[ ] CRUD /absence-types
[ ] GET  /absences
[ ] POST /absences (demande employé)
[ ] GET  /absences/{id}
[ ] PUT  /absences/{id}/approve
[ ] PUT  /absences/{id}/reject
[ ] PUT  /absences/{id}/cancel
[ ] AbsenceService : déduction leave_balance + cohérence attendance_logs
[ ] Tests : overlapping, solde insuffisant, annulation après approbation
```

**Commit** : `feat(absences): absence request workflow with balance deduction`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-07 — AVANCES SUR SALAIRE (Session Backend #7)
## ══════════════════════════════════════════════

**Prérequis** : CC-06 ✅

```
[ ] GET  /advances
[ ] POST /advances (demande employé)
[ ] GET  /advances/{id}
[ ] PUT  /advances/{id}/approve (→ status='active', calcul remboursement)
[ ] PUT  /advances/{id}/reject
[ ] SalaryAdvanceService : calcul monthly_deduction, repayment_plan JSONB
[ ] Statuts : pending → approved → active → repaid (+ rejected)
[ ] amount_remaining mis à jour par PayrollService
[ ] Tests : approbation, calcul mensualités, statut 'active' présent
```

**Commit** : `feat(advances): salary advance workflow with repayment plan`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-08 — PAIE (Session Backend #8)
## ══════════════════════════════════════════════

**Prérequis** : CC-07 ✅

```
[ ] POST /payroll/calculate (déclenche PayrollService pour un mois)
[ ] GET  /payroll (liste bulletins, filtre période/employé)
[ ] GET  /payroll/{id}
[ ] POST /payroll/{id}/validate (manager → status='validated')
[ ] GET  /payroll/{id}/pdf (stream PDF)
[ ] GET  /payroll/export-bank (CSV virements)
[ ] PayrollService (11 étapes) :
    1. salary_base
    2. heures supplémentaires (taux 1.25x / 1.5x)
    3. primes (bonuses JSONB)
    4. → gross_salary calculé
    5. cotisations salariales (hr_model_templates)
    6. IR (tranches progressives)
    7. pénalités de retard (plafond 1 jour)
    8. déduction avances actives (amount_remaining)
    9. déduction absences non payées
    10. → net_salary calculé
    11. Mise à jour advance.amount_remaining
[ ] GeneratePayslipPdfJob (DomPDF, template 25_TEMPLATE_BULLETIN_PAIE.md)
[ ] BankExportService (DZ_GENERIC CSV — voir 26_FORMATS_EXPORT_BANCAIRE.md)
[ ] Tests PayrollCalculationTest (brut, net, cotisations DZ)
```

**Commit** : `feat(payroll): 11-step PayrollService, PDF generation, bank export CSV`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-09 — TÂCHES + ÉVALUATIONS (Session Backend #9)
## ══════════════════════════════════════════════

**Prérequis** : CC-05 ✅ (en parallèle possible avec CC-08)

```
[ ] CRUD /projects
[ ] CRUD /tasks + GET /tasks/my-tasks
[ ] CRUD /tasks/{id}/comments
[ ] POST /tasks/{id}/status (changer statut)
[ ] CRUD /evaluations
[ ] Tests RBAC tâches (assigné vs manager)
```

**Commit** : `feat(tasks): projects, tasks with comments, evaluations`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-10 — NOTIFICATIONS + SSE (Session Backend #10)
## ══════════════════════════════════════════════

**Prérequis** : CC-08 ✅

```
[ ] GET  /notifications (liste, filtre is_read)
[ ] POST /notifications/mark-read (bulk)
[ ] DELETE /notifications/{id}
[ ] GET  /notifications/stream (SSE — Content-Type: text/event-stream)
[ ] NotificationService : DB + FCM + SSE en un seul appel
[ ] Observers Eloquent (Employee, Payroll, Absence, SalaryAdvance)
[ ] Config Nginx SSE ajoutée (proxy_buffering off)
[ ] Cron subscriptions:check (02h00 quotidien)
[ ] Tests SSE endpoint répond 200 avec bon Content-Type
```

**Commit** : `feat(notifications): DB + FCM + SSE realtime, audit observers, subscription cron`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-11 — SUPER ADMIN + RAPPORTS (Session Backend #11)
## ══════════════════════════════════════════════

**Prérequis** : CC-10 ✅

```
[ ] GET  /admin/companies (liste toutes les entreprises)
[ ] POST /admin/companies (créer via TenantService)
[ ] GET/PUT/DELETE /admin/companies/{id}
[ ] POST /admin/companies/{id}/upgrade-enterprise (TenantMigrationService)
[ ] GET  /admin/stats (MRR, actifs, trials)
[ ] GET  /admin/languages + PUT /admin/languages/{id}
[ ] GET  /admin/hr-models + GET /admin/hr-models/{country}
[ ] GET  /reports/attendance (exports CSV)
[ ] GET  /reports/absences
[ ] GET  /reports/payroll
[ ] Webhooks paiement (Stripe + Paydunya — voir 14_PAYMENT_WEBHOOKS_SPEC.md)
[ ] GET  /onboarding/status + POST /onboarding/complete
[ ] Tests SuperAdmin isolation (token SA ne leak pas dans tenant)
```

**Commit** : `feat(admin): super admin panel, reports, webhooks, onboarding`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## CC-12 — TESTS FINAUX (Session Backend #12)
## ══════════════════════════════════════════════

**Prérequis** : CC-11 ✅

```
[ ] TenantIsolationTest → 100% (Company A ne voit pas Company B)
[ ] PayrollCalculationTest → net correct pour modèle DZ
[ ] AttendanceConflictTest → pas de double check-in
[ ] PlanLimitTest → Starter bloqué à 20 employés
[ ] SubscriptionTest → grâce 3j, blocage après
[ ] PublicRegisterTest → company + user_lookup créés
[ ] Coverage > 80% sur les Services (PayrollService, AttendanceService)
[ ] php artisan test → 0 échec, 0 warning
```

**Commit** : `test: complete test suite — isolation, payroll, attendance, plan limit`
**Statut** : ⬜ À faire | **Date** : ___________

---

## ══════════════════════════════════════════════
## JU-02 à JU-04 — MOBILE FONCTIONNEL (après CC-02/05/07 validés)
## ══════════════════════════════════════════════

### JU-02 — Auth + Profil (après CC-02 ✅)
```
[ ] Login avec vraie API (remplacement mocks)
[ ] Gestion token expiry (refresh auto)
[ ] FCM token envoyé au login
[ ] Écran Profil + modification photo
[ ] Statut : ⬜ | Date : ___________
```

### JU-03 — Pointage (après CC-05 ✅)
```
[ ] Écran pointage temps réel (état check-in/out)
[ ] Confirmation avec animation succès
[ ] Historique calendrier mensuel avec couleurs
[ ] Statut : ⬜ | Date : ___________
```

### JU-04 — HR modules (après CC-07 ✅)
```
[ ] Demande + liste absences
[ ] Demande + liste avances
[ ] Liste tâches assignées
[ ] PDF bulletins de paie (viewer intégré)
[ ] Notifications push FCM
[ ] Statut : ⬜ | Date : ___________
```

---

## ══════════════════════════════════════════════
## CU-01 — FRONTEND WEB (après CC-04 ✅)
## ══════════════════════════════════════════════

**Agent** : Cursor / Claude Code
**Prompt** : `docs/PROMPTS_EXECUTION/frontend/CU-01_ET_AGENTS.md`

```
[ ] Init Vue.js 3 + Inertia.js + Tailwind CSS + PrimeVue
[ ] Layouts (Auth, Dashboard, Admin)
[ ] RTL arabe (dir dynamique, classes rtl:, ms-/me-)
[ ] Dashboard manager (KPIs, liste employés, pointage du jour)
[ ] Module Employés (liste, fiche, import CSV)
[ ] Module Pointage (vue mensuelle, corrections)
[ ] Module Absences (approbation manager)
[ ] Module Paie (validation, téléchargement PDF, export banque)
[ ] Module Tâches (kanban ou liste)
[ ] Cloche notifications SSE (badge + dropdown)
[ ] Onboarding guidé 4 étapes (nouveau client)
[ ] Super Admin panel (/admin)
[ ] Statut : ⬜ | Date : ___________
```

---

## ══════════════════════════════════════════════
## 🚢 DÉPLOIEMENT PRODUCTION
## ══════════════════════════════════════════════

```
[ ] php artisan test → 100% pass
[ ] flutter build apk --release (Android)
[ ] Variables production configurées (.env)
[ ] GitHub Actions deploy.yml déclenché (push main)
[ ] pg_dump backup vérifié avant migrate
[ ] Smoke tests production :
    [ ] POST /auth/login → token
    [ ] GET /auth/me → profil complet
    [ ] POST /attendance/check-in → 201
[ ] Horizon actif (php artisan horizon:status)
[ ] Logs Laravel clean (pas d'erreurs)
[ ] Certificat SSL valide
[ ] Statut : ⬜ | Date : ___________
```
