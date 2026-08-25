# Feature Specification: ADR-0016 Phase 3 — migrer la surface API pointage sous /attendance/* (issue #5354)

**Feature Branch**: `mod/attendance/5354-attendance-routes`

**Created**: 2026-08-23

**Status**: Implemented

**Module**: `attendance` — périmètre : `api/app/Modules/Attendance/routes/geo.php`
(nouveau), `AttendanceServiceProvider` (chargement routes), `api/openapi.yaml`
(+ miroir `dev-hub/openapi/v1.yaml` + SDK générés), apps mobiles
`front/mobile_apps/*` (chaînes API), spec, CHANGELOG.

## Contexte

ADR-0016 Phase 3 : les apps `leopardo_employee`/`leopardo_hr`/`leopardo_manager`
consomment 11 routes sous `/smart-attendance/*` (config, preferences,
geo-events, my-sessions, sessions + approve/reject, dashboard, mode-settings,
employees/{id}/preference). Objectif : surface consolidée sous
`/api/v1/attendance/*` **sans rupture de contrat mobile** — alias temporaires
puis bascule.

## Décisions

1. **Nouveau fichier routes `Attendance/routes/geo.php`** : les 12 routes sous
   `api/v1/attendance/*` (mêmes contrôleurs SmartAttendance — la fusion des
   contrôleurs est Phase 4). Nommage cible de l'issue : `sessions` →
   **`geo-sessions`** (exemples explicites `/attendance/geo-sessions`,
   `/attendance/geo-sessions/{id}/approve`).
2. **Alias conservés** : le fichier `smart_attendance.php` existant reste
   chargé (double enregistrement) → zéro rupture mobile au déploiement.
3. **OpenAPI** : 11 nouveaux chemins ajoutés (tags `Attendance`), les 11
   chemins `/smart-attendance/*` marqués `deprecated: true` (12 opérations).
   Miroir `dev-hub/openapi/v1.yaml` + SDK JS/Python régénérés via
   `generate-openapi-sdk.mjs` (758 opérations).
4. **Mobile** : bascule des chaînes **API** dans les 5 fichiers dart (repos +
   modèles) — les routes de **navigation in-app** (`/smart-attendance`,
   `/smart-attendance/pending`, `/smart-attendance/mode`) sont internes à
   Flutter, hors contrat API, conservées telles quelles.

## User Scenarios & Testing

### US1 — Nouveau contrat /attendance/* (DoD)

**Independent Test**: `tests/Feature/Attendance/GeoRoutesMigrationTest.php`
(6 tests) + `php artisan test tests/Feature/SmartAttendance tests/Feature/Attendance`
→ **117 tests / 498 assertions verts**.

**Acceptance Scenarios**:

1. **Given** un employé, **When** `GET /api/v1/attendance/config`, **Then**
   200 avec `data.mode` + `data.gps_enabled`.
2. **Given** un employé, **When** `POST /api/v1/attendance/geo-events`, **Then**
   201 (même comportement que l'alias).
3. **Given** un manager RH, **When** `GET /api/v1/attendance/geo-sessions`,
   **Then** 200 ; **When** un employé tente le même chemin, **Then** 403.
4. **Given** les apps mobiles, **When** elles appellent les endpoints, **Then**
   les chaînes API pointent vers `/attendance/*` (grep : 0 résidu
   `smart-attendance/` dans les repos).

### US2 — Alias /smart-attendance/* sans rupture (DoD)

1. **Given** l'ancien contrat, **When** `GET /api/v1/smart-attendance/config`
   et `GET /api/v1/smart-attendance/sessions`, **Then** toujours 200 (tests
   dédiés + suites SmartAttendance inchangées).
2. **Given** l'OpenAPI, **When** les chemins aliasés sont inspectés, **Then**
   `deprecated: true` (12 opérations) et 0 chemin `/smart-attendance/*`
   nouveau.

## Edge Cases

- **Fusion YAML commentaire/chemin** : l'insertion chirurgicale a d'abord collé
  le commentaire `# --- ZKTeco ---` à la ligne du chemin mode-settings → corrigé
  (ligne séparée) ; diff final = 281 insertions, 0 suppression.
- **Pint `method_argument_space`** : alignement des tableaux `Route::get`
  corrigé automatiquement.
- **Pas de pwsh** dans le sandbox → le contrat mobile est vérifié par équivalence
  grep (0 résidu API) + les workflows CI mobiles (mobile-apps-ci.yml) exécutent
  `validate-mobile-workflow-contracts.ps1` sur Windows runner.

## Deliverables

- [x] Spec `.specify/features/5354-attendance-routes/spec.md`
- [x] `api/app/Modules/Attendance/routes/geo.php` (12 routes) + chargement provider
- [x] OpenAPI : 11 chemins `/attendance/*` + `deprecated: true` sur les 11 alias
- [x] SDK JS/Python + miroir `dev-hub/openapi/v1.yaml` régénérés
- [x] Apps mobiles : 40 remplacements de chaînes API dans 5 fichiers dart
- [x] `tests/Feature/Attendance/GeoRoutesMigrationTest.php` (6 tests)
- [x] CHANGELOG `[Unreleased]` + PR `Closes #5354`

## Validation

- Nouveaux tests : 6/6 ✅ · Suites existantes : 117 tests / 498 assertions ✅
- PHPStan Strict : 0 erreur · Pint : PASS
- Route:list : 12 routes `/api/v1/attendance/*` + 11 alias présents
