# 📦 FICHIER HISTORIQUE (BOT ARTEFACT) - REMPLACE PAR PILOTAGE.md
# PROGRAM_VERSION DE REFERENCE: 4.1.85
# Date de gel: 02 Mai 2026

Ce document est conserve pour tracabilite uniquement.
Ne pas l'utiliser pour piloter l'execution.

Source de verite active:
- `../../../PILOTAGE.md`
- `../../README.md`

---

# EXPLORE — Exploration technique du projet Leopardo RH

**Date :** 2 mai 2026
**Auteur :** Devin (session automatisee)
**Branche de reference :** `main` (commit de base de la PR #235)

---

## 1. Vue d'ensemble

**Leopardo RH** est un monorepo compose de trois applications principales :

| Couche | Techno | Dossier | Etat |
|--------|--------|---------|------|
| Backend API | Laravel 11 / PHP 8.2+ / PostgreSQL | `api/` | Production |
| Application mobile | Flutter 3.3+ / Dart | `front/mobile/` | Production |
| Application web | Next.js (App Router) | `front/web/` | En developpement |

Le projet gere les ressources humaines (pointage, absences, paie, evaluations, etc.) avec une architecture multi-tenant basee sur les schemas PostgreSQL.

---

## 2. Architecture backend (`api/`)

### 2.1 Stack technique

- **Framework :** Laravel 11.31+
- **PHP :** ^8.2 (`composer.json`)
- **Base de donnees :** PostgreSQL avec schemas par tenant
- **Cache / Session :** File (configurable Redis)
- **Queue :** Sync (configurable async)
- **Auth :** Laravel Sanctum (tokens Bearer)
- **Analyse statique :** PHPStan au niveau `max` avec baseline (`phpstan-baseline.neon`)
- **Style de code :** Laravel Pint (PSR-12 strict)
- **Monitoring :** Sentry (optionnel)

### 2.2 Multi-tenancy

Le systeme repose sur des **schemas PostgreSQL** par entreprise (pas de SaaS classique par database).

**Flux d'une requete authentifiee :**
1. `auth:sanctum` — verifie le token Bearer
2. `TenantMiddleware` (`app/Http/Middleware/TenantMiddleware.php`) :
   - Charge l'employee et sa company
   - Verifie les statuts (suspended, archived, expired)
   - Appelle `TenantManager::setTenant()` qui fait `SET search_path TO <schema>`
   - Bind `current_company` dans le container Laravel
3. Le trait `BelongsToCompany` (sur chaque modele tenant) ajoute un global scope `company_id`

**Fichier cle :** `app/Services/TenantManager.php`

```
search_path = tenant_schema, public
```

### 2.3 Structure des dossiers (backend)

```
api/
├── app/
│   ├── DTOs/                        # Data Transfer Objects
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/             # 25 controleurs RESTful
│   │   │   └── Web/                # Controleurs web (invitations, etc.)
│   │   ├── Middleware/
│   │   │   ├── TenantMiddleware.php # Isolation multi-tenant
│   │   │   ├── SetLocale.php       # i18n
│   │   │   ├── Cameras/            # Middleware specifique module cameras
│   │   │   └── Web/                # Middlewares web (EnsureEmployee, etc.)
│   │   ├── Requests/Api/V1/        # Form Requests par module
│   │   └── Resources/Api/V1/       # API Resources (serialisation)
│   ├── Mail/                       # Mailables (email transactionnel)
│   ├── Models/                     # 27 modeles Eloquent
│   ├── Services/                   # 16 services metier
│   └── Traits/                     # BelongsToCompany, etc.
├── config/                         # Configurations Laravel + cameras
├── database/
│   └── migrations/
│       ├── public/                 # Tables partagees (companies, super_admins)
│       └── tenant/                 # Tables par schema tenant (employees, etc.)
├── lang/
│   ├── ar/                         # Arabe
│   ├── en/                         # Anglais
│   ├── fr/                         # Francais
│   └── tr/                         # Turc
├── routes/
│   ├── api.php                     # Point d'entree principal
│   └── modules/
│       ├── rh.php                  # Module RH (modules 1-7)
│       ├── cameras.php             # Module cameras/securite
│       └── cabinet.php             # Module Placard (documents)
├── phpstan.neon                    # Config PHPStan (level: max)
├── phpstan-baseline.neon           # Baseline erreurs connues (~5300 lignes)
└── composer.json
```

### 2.4 Modeles Eloquent (27 modeles)

| Modele | Table | Scope | Description |
|--------|-------|-------|-------------|
| `Company` | `companies` | Public | Entreprise / tenant |
| `SuperAdmin` | `super_admins` | Public | Super-administrateur plateforme |
| `Employee` | `employees` | Tenant | Utilisateur principal (auth Sanctum) |
| `Department` | `departments` | Tenant | Service / departement |
| `Position` | `positions` | Tenant | Poste / fonction |
| `Schedule` | `schedules` | Tenant | Planning horaire |
| `Site` | `sites` | Tenant | Site geographique |
| `AttendanceLog` | `attendance_logs` | Tenant | Pointage entree/sortie |
| `AttendanceKiosk` | `attendance_kiosks` | Tenant | Borne de pointage ZKTeco |
| `Absence` | `absences` | Tenant | Demande d'absence |
| `AbsenceType` | `absence_types` | Tenant | Type d'absence configurable |
| `SalaryAdvance` | `salary_advances` | Tenant | Avance sur salaire |
| `Payroll` | `payrolls` | Tenant | Bulletin de paie |
| `Evaluation` | `evaluations` | Tenant | Evaluation collaborateur |
| `Notification` | `notifications` | Tenant | Notification interne |
| `Project` | `projects` | Tenant | Projet |
| `Task` | `tasks` | Tenant | Tache projet |
| `TaskComment` | `task_comments` | Tenant | Commentaire sur tache |
| `BiometricEnrollmentRequest` | `biometric_enrollment_requests` | Tenant | Demande biometrique |
| `UserInvitation` | `user_invitations` | Tenant | Invitation onboarding |
| `LeaveBalanceLog` | `leave_balance_logs` | Tenant | Historique solde conges |
| `Language` | `languages` | Public | Langues supportees |
| `CompanySetting` | `company_settings` | Tenant | Parametres entreprise |
| `CabinetFolder` | `cabinet_folders` | Tenant | Dossier du placard |
| `CabinetDocument` | `cabinet_documents` | Tenant | Document du placard |
| `CabinetShare` | `cabinet_shares` | Tenant | Partage de document/dossier |

Chaque modele tenant utilise le trait `BelongsToCompany` qui ajoute automatiquement un global scope sur `company_id`.

### 2.5 Services metier (16 services)

| Service | Responsabilite |
|---------|---------------|
| `AuthService` | Login, tokens Sanctum, changement mdp |
| `TenantManager` | Switch de schema PostgreSQL par tenant |
| `EmployeeService` | CRUD employes, archivage |
| `AttendanceService` | Check-in / check-out avec geolocalisation |
| `AbsenceService` | Demandes, approbation, solde conges |
| `SalaryAdvanceService` | Avances sur salaire |
| `PayrollService` | Generation bulletins de paie |
| `EstimationService` | Estimation salaire journaliere / mensuelle |
| `BiometricEnrollmentService` | Inscription biometrique |
| `MobileExperienceService` | Modules et actions rapides selon le role |
| `CompanyProvisioningService` | Creation entreprise + schema tenant |
| `UserInvitationService` | Invitations et onboarding |
| `KioskAttendanceService` | Pointage via bornes ZKTeco |
| `SuperAdminService` | Gestion super-admins plateforme |
| `FeatureFlag` | Feature flags par entreprise |
| `CabinetService` | Upload, partage, suppression documents |

### 2.6 Authentification

**Deux guards Sanctum :**
- `sanctum` — Employes (`Employee` model)
- `super_admin_api` — Super-admins (`SuperAdmin` model)

**Flux login :**
1. `POST /api/v1/auth/login` avec `email` + `password` + `company_code`
2. Le guard resout l'employee dans le bon schema tenant
3. Retourne un token Bearer Sanctum
4. Toutes les requetes suivantes incluent `Authorization: Bearer <token>`

**Roles :** Le modele `Employee` a des methodes `isPrincipal()`, `isHr()`, `isManager()`, `hasManagerRole()` pour le controle d'acces.

### 2.7 Routes API (`routes/`)

Les routes sont organisees en modules :

```
api.php (point d'entree)
├── /health                          # Health check (public)
├── /auth/login                      # Authentification (throttled)
├── /platform/auth/login             # Auth super-admin
├── /onboarding/invitation/{token}   # Onboarding public
├── [auth:sanctum + tenant]          # Routes protegees
│   ├── /auth/me, /auth/profile...
│   ├── modules/rh.php              # Tous les modules RH
│   ├── modules/cameras.php         # Module cameras
│   └── modules/cabinet.php         # Module Placard
└── [auth:super_admin_api]           # Routes plateforme
    └── /platform/...
```

**Module RH (rh.php) :** Employees, Attendance, Absences, SalaryAdvances, Payrolls, Evaluations, Notifications, Projects, Tasks, Departments, Positions, Schedules, Sites, Kiosks, Invitations, Biometrics.

### 2.8 Internationalisation (i18n)

4 langues supportees : **Francais (fr)**, **Anglais (en)**, **Turc (tr)**, **Arabe (ar)**.

Fichiers dans `api/lang/{locale}/`. Le middleware `SetLocale` detecte la langue via le header `Accept-Language` envoye par le client.

L'Employee a un champ `language` qui peut surcharger la preference.

### 2.9 CI/CD (GitHub Actions)

| Check | Description |
|-------|-------------|
| Backend Quality | Pint (style) + PHP Syntax + PHPStan/Larastan (level max) |
| Backend Tests | PHPUnit sur PHP 8.4 + PostgreSQL 16 + Redis 7 |
| Backend Security | Composer Audit (vulnerabilites) |
| CodeQL | Analyse securite statique |
| Mobile Flutter | Build Flutter (Stable Channel) |
| Dependency Review | Audit securite des PR |
| Governance Gates | Changelog + fichiers canoniques |
| Vercel Preview | Preview deployments web |

**Deploiement :** L'API est deployee sur Render (`gestionemployerbackend.onrender.com`).

---

## 3. Architecture mobile (`front/mobile/`)

### 3.1 Stack technique

- **Framework :** Flutter 3.3+ / Dart
- **State management :** Riverpod (flutter_riverpod ^3.3.1)
- **Routing :** GoRouter (go_router ^17.2.2)
- **HTTP client :** Dio (dio ^5.9.2)
- **Stockage securise :** flutter_secure_storage
- **Stockage local :** Hive
- **Biometrie :** local_auth
- **i18n :** intl + flutter_localizations

### 3.2 Structure des dossiers (mobile)

```
mobile/lib/
├── main.dart                        # Point d'entree
├── app.dart                         # GoRouter + MaterialApp.router
├── core/
│   ├── api/
│   │   ├── api_client.dart          # Client Dio (base URL, auth, interceptors)
│   │   ├── api_exceptions.dart      # Gestion erreurs API
│   │   └── mock_interceptor.dart    # Mode mock pour dev
│   ├── providers/
│   │   └── core_providers.dart      # Tous les providers Riverpod (DI)
│   ├── storage/
│   │   ├── app_preferences.dart     # Preferences Hive
│   │   └── secure_storage.dart      # Token securise
│   ├── theme/
│   │   ├── app_colors.dart          # Palette couleurs (dark/light)
│   │   ├── app_theme.dart           # ThemeData
│   │   ├── app_typography.dart      # Typographie Inter
│   │   └── mobile_experience_icons.dart # Icones par module
│   └── widgets/                     # Composants reutilisables
│       ├── alert_banner.dart
│       ├── empty_state.dart
│       ├── leopardo_badge.dart
│       ├── pulse_button.dart
│       └── shimmer_loading.dart
├── features/                        # Feature-based architecture
│   ├── absences/
│   │   ├── data/absence_repository.dart
│   │   ├── providers/absence_provider.dart
│   │   └── screens/absence_list_screen.dart
│   ├── attendance/
│   │   ├── data/attendance_repository.dart
│   │   ├── providers/attendance_provider.dart
│   │   └── screens/ (3 screens)
│   ├── auth/
│   │   ├── data/auth_repository.dart
│   │   ├── providers/auth_provider.dart
│   │   └── screens/ (login, register, welcome)
│   ├── evaluations/
│   ├── home/
│   │   ├── data/project_repository.dart
│   │   └── screens/ (home_screen, modules_hub_screen)
│   ├── modules/
│   ├── notifications/
│   ├── payrolls/
│   ├── salary_advances/
│   ├── settings/
│   └── team/
└── models/                          # Modeles de donnees (Dart)
    ├── absence.dart
    ├── attendance_log.dart
    ├── company.dart
    ├── daily_summary.dart
    ├── employee.dart
    ├── evaluation.dart
    ├── mobile_experience.dart       # Modules dynamiques par role
    ├── monthly_summary.dart
    ├── notification.dart
    ├── payroll.dart
    ├── payroll_record.dart
    ├── project_task.dart
    └── salary_advance.dart
```

### 3.3 Pattern de feature

Chaque feature suit le meme pattern **data / providers / screens** :

1. **Repository** (`data/xxx_repository.dart`) — Appels HTTP via `ApiClient.dio`
2. **Provider** (`providers/xxx_provider.dart`) — `FutureProvider` ou `StateNotifierProvider` Riverpod
3. **Screen** (`screens/xxx_screen.dart`) — Widget `ConsumerWidget` qui watch le provider

**Pour ajouter une nouvelle feature :**
1. Creer le dossier `features/xxx/` avec les 3 sous-dossiers
2. Creer le modele dans `models/xxx.dart`
3. Enregistrer le repository dans `core/providers/core_providers.dart`
4. Ajouter la route GoRouter dans `app.dart`
5. Ajouter l'icone dans `mobile_experience_icons.dart`
6. Ajouter le module dans `MobileExperienceService.php` (backend)

### 3.4 Systeme de modules dynamiques

Le backend envoie la liste des modules via `/api/v1/auth/me` dans l'objet `mobile_experience` :

```json
{
  "data": {
    "id": 1,
    "mobile_experience": {
      "stage": "regular",
      "modules": [
        {
          "key": "attendance",
          "title": "Pointage",
          "description": "...",
          "domain": "rh",
          "route": "/attendance",
          "status": "active"
        }
      ],
      "quick_actions": [...]
    }
  }
}
```

Le service `MobileExperienceService` (backend) construit cette liste en fonction du role de l'employe. Cote mobile, `ModulesHubScreen` et `HomeScreen` lisent `employee.mobileExperience` pour afficher les modules actifs et les actions rapides.

### 3.5 API Client

Le `ApiClient` (`core/api/api_client.dart`) gere :
- **Base URL** : auto-resolue (production Render, Android emulator 10.0.2.2, localhost)
- **Auth** : injecte automatiquement le token Bearer depuis `SecureStorage`
- **Langue** : injecte `Accept-Language` depuis les preferences
- **Erreurs** : intercepte les 401 (logout auto), 403 (compte suspendu), timeouts

### 3.6 Navigation (GoRouter)

Routes definies dans `app.dart` :

| Route | Screen | Auth requise |
|-------|--------|-------------|
| `/welcome` | WelcomeScreen | Non |
| `/login` | LoginScreen | Non |
| `/register` | RegisterScreen | Non |
| `/` | HomeScreen | Oui |
| `/modules` | ModulesHubScreen | Oui |
| `/attendance` | AttendanceScreen | Oui |
| `/history` | HistoryScreen | Oui |
| `/me/monthly` | MonthlySummaryScreen | Oui |
| `/absences` | AbsenceListScreen | Oui |
| `/salary-advances` | SalaryAdvanceListScreen | Oui |
| `/payrolls` | PayrollListScreen | Oui |
| `/evaluations` | EvaluationListScreen | Oui |
| `/notifications` | NotificationListScreen | Oui |
| `/team` | TeamScreen | Oui |
| `/settings` | SettingsScreen | Oui |

La redirection automatique envoie vers `/welcome` si non authentifie, et vers `/` si authentifie sur une page publique.

---

## 4. Application web (`front/web/`)

- **Framework :** Next.js (App Router avec `(dashboard)` et `(landing)` route groups)
- **Etat :** En cours de developpement (12 fichiers TS/TSX)
- **Config :** `package.json`, `eslint.config.mjs`, `postcss.config.mjs`

Le web est encore en phase de construction. Le backend et le mobile sont les cibles principales.

---

## 5. Schemas de base de donnees

### 5.1 Organisation des migrations

```
database/migrations/
├── public/    # Schema partage (companies, super_admins, languages)
└── tenant/    # Schema par entreprise (15 fichiers de migration)
```

Les migrations tenant sont executees dans le schema de chaque entreprise lors du provisioning (`CompanyProvisioningService`).

### 5.2 Conventions de nommage

- Tables : `snake_case` pluriel (ex: `salary_advances`, `cabinet_documents`)
- Cles etrangeres : `{table_singulier}_id` (ex: `employee_id`, `company_id`)
- Timestamps : `created_at`, `updated_at` (Laravel defaults)
- Soft delete : non utilise (suppression physique)
- Champs polymorphiques : `shareable_type` + `shareable_id` (convention Laravel)

---

## 6. Conventions de code

### 6.1 Backend (PHP / Laravel)

- **PSR-12** via Laravel Pint (formatage automatique)
- **PHPStan level max** : tous les types doivent etre explicites
  - Generics obligatoires sur les relations : `@return BelongsTo<Employee, $this>`
  - `array` doit avoir un type de valeur : `@param array<string, mixed> $data`
  - `mixed` ne peut pas etre cast directement : utiliser `is_string()`, `$request->string()`, `intval()` avec check `is_numeric()`
- **Trait BelongsToCompany** sur tous les modeles tenant
- **Form Requests** pour chaque operation de validation (pas de validation inline)
- **Services** pour la logique metier (les controleurs delegent aux services)
- **Routes par module** dans `routes/modules/`
- **Baseline PHPStan** : les erreurs connues (ex: `app('current_company')` retourne `mixed`) sont dans `phpstan-baseline.neon`

### 6.2 Mobile (Dart / Flutter)

- **Feature-based** architecture
- **Riverpod** pour le state management (providers dans `core/providers/`)
- **GoRouter** pour la navigation declarative
- **ConsumerWidget** plutot que StatefulWidget + setState
- **Modeles immutables** avec factory `fromJson`

---

## 7. Variables d'environnement

### Backend (`.env.example`)

| Variable | Description | Exemple |
|----------|------------|---------|
| `APP_URL` | URL de l'API | `https://api.leopardo-rh.com` |
| `FRONTEND_URL` | URL du frontend web | `https://app.leopardo-rh.com` |
| `DB_CONNECTION` | Driver DB | `pgsql` |
| `DB_SEARCH_PATH` | Schemas par defaut | `shared_tenants,public` |
| `SENTRY_LARAVEL_DSN` | DSN Sentry (optionnel) | |
| `MAIL_MAILER` | Driver mail | `smtp` / `ses` |

### Mobile

| Variable | Description |
|----------|------------|
| `API_BASE_URL` | Compile-time override (`--dart-define=API_BASE_URL=...`) |
| `API_BASE_URL=mock` | Active le `MockInterceptor` pour dev sans serveur |

---

## 8. Commandes utiles

### Backend

```bash
cd api

# Installer les dependances
composer install

# Lancer le serveur local
php artisan serve

# Lancer les tests
php artisan test

# Verifier le style (Pint)
./vendor/bin/pint --test

# Analyse statique (PHPStan)
./vendor/bin/phpstan analyse

# Regenerer la baseline PHPStan
./vendor/bin/phpstan analyse --generate-baseline

# Migrations (schema public)
php artisan migrate --path=database/migrations/public

# Migrations (schema tenant — necessite un tenant actif)
php artisan migrate --path=database/migrations/tenant
```

### Mobile

```bash
cd mobile

# Installer les dependances
flutter pub get

# Lancer en dev
flutter run

# Build APK
flutter build apk

# Lancer les tests
flutter test

# Analyse statique
flutter analyze

# Mode mock (sans serveur)
flutter run --dart-define=API_BASE_URL=mock
```

---

## 9. Points d'attention pour les nouveaux developpeurs

1. **Toujours utiliser le trait `BelongsToCompany`** sur les nouveaux modeles tenant pour l'isolation des donnees.

2. **PHPStan level max est strict** : ne pas utiliser `(string)` ou `(int)` sur `mixed`. Utiliser `is_string()`, `$request->string()`, `intval()` avec check `is_numeric()`.

3. **Les relations Eloquent doivent specifier leurs generics** : `@return BelongsTo<Employee, $this>`.

4. **Ajouter les erreurs du trait BelongsToCompany dans la baseline** pour chaque nouveau modele (le trait utilise `app('current_company')` qui retourne `mixed`).

5. **Pour ajouter un module mobile** : il faut modifier a la fois le backend (`MobileExperienceService`) et le mobile (repository, provider, screen, route, icone, core_providers).

6. **Les migrations sont separees** en `public/` (partagees) et `tenant/`. Ne pas melanger.

7. **La validation passe par des Form Requests** dedies — jamais de `$request->validate()` inline dans les controleurs.

8. **i18n** : tout nouveau module doit avoir ses traductions dans les 4 langues (fr, en, tr, ar).

9. **L'API est versionnee** (`/api/v1/`) — toutes les routes sont sous ce prefixe.

10. **Le deploy mobile** passe par Firebase App Distribution via GitHub Actions (tag `v*-staging`).

---

*Document genere le 2 mai 2026. Se referer a `main` pour l'etat le plus a jour du code.*
