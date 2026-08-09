# Changelog

Toutes les modifications notables de ce projet sont documentées ici.
Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).

## [Unreleased]

### Security
- **Constant-time comparison and hashed storage for `edge_token`** — `EdgeNodeController` now hashes the Edge node authentication token at rest (`hash('sha256', $token)`) instead of storing it in cleartext, and verifies incoming tokens with `hash_equals()` instead of a plain string comparison
  - Removes a timing side-channel that could let an attacker recover a valid `edge_token` byte-by-byte via response-time measurements
  - Removes plaintext token exposure in the database (backups, dumps, read replicas) — only the SHA-256 digest is persisted
  - No behavior change for legitimate Edge nodes: token issuance/rotation and existing valid tokens continue to authenticate identically
  - Tests: `EdgeSyncTest`, `EdgeOfflineScenarioTest` updated and passing
- **Mass-assignment hardening on 13 Eloquent models** — replaced `protected $guarded = [];` with explicit `protected $fillable = [...]` allow-lists on `AttendanceLog`, `ApprovalRequest`, `ApprovalWorkflow`, `ApprovalDecision`, `KioskAnnouncement`, `AttendanceCorrectionRequest`, `AttendanceKiosk`, `BiometricEnrollmentRequest`, `ZktecoDevice`, `ZktecoSyncLog`, `CalendarEvent`, `CalendarConnection` (Attendance module) and `ScheduledTaskRun` (Platform module)
  - No known active exploit today (all write paths already used explicit field lists), but `$guarded = []` removed Laravel's mass-assignment safety net for any future `Model::create($request->all())`-style shortcut
  - Allow-lists built from each model's actual migration columns and real write call sites; no behavior change — existing test suite passes identically before/after

### Added
- **Employee-manager discussion threads (PA2-COMM-002)** : fil de discussion privé entre un employé et son manager
  - Modèles `ConversationThread` / `ConversationMessage` (scopés par tenant via `BelongsToCompany`)
  - Fil libre ou rattaché à un sujet existant (`salary_advance`, `attendance_correction`, `absence`) appartenant au même employé/entreprise
  - Un seul fil par binôme employé/manager pour un même sujet ; réutilisation automatique du fil existant
  - Pièce jointe unique par message (5 Mo max), téléchargement restreint aux participants du fil
  - Statut lu/non-lu par participant, notification in-app (`conversation_message_received`) à chaque nouveau message
  - RBAC : l'employé ne voit que ses propres fils, le manager ne voit que les fils de ses subordonnés directs
  - Endpoints : `GET/POST /api/v1/conversations`, `GET /api/v1/conversations/{thread}`, `POST /api/v1/conversations/{thread}/messages`, `GET /api/v1/conversations/{thread}/messages/{message}/attachment`
  - Tests Feature : `ConversationControllerTest` (isolation tenant, RBAC manager/employé, pièce jointe, notifications)
- **Audit trail pour la nomination/revocation de rôles RH (PA2-MOB-007)**
  - Nouvel événement `EmployeeRoleAssigned`, journalisé dans `audit_logs` (actions `role_assigned` / `role_revoked`) avec l'ancien et le nouveau `manager_role`, et l'identité du manager principal ayant fait le changement
  - Couvre les deux chemins existants : `POST /employees/{id}/assign-role` (dashboard web) **et** `PATCH /employees/{id}` (utilisé par l'app mobile manager `TeamScreen._toggleHrRole`), qui contournait auparavant tout audit
  - Tests Feature : `RoleAssignmentAuditTest` (6 tests couvrant nomination/revocation sur les deux endpoints, non-régression sur les champs non lies au rôle, et rejet d'un manager non-principal)

### Fixed
- **test(f-13b): migration des tests Feature HR + Attendance + SmartAttendance vers les vraies migrations (issues #1593 #1606).**
  - Les tests des modules HR (`HrControllerTest`), Attendance (13 fichiers) et SmartAttendance (5 fichiers) abandonnent le trait manuel `CreatesMvpSchema`/`CreatesSmartAttendanceSchema` (schéma SQL figé de ~2150 lignes, en dérive) au profit du trait `RefreshTenantDatabase` (vraies migrations `public` + `tenant`), sur le même pattern que Payroll et Absences
  - Créations `Company`/`Employee` alignées sur les colonnes NOT NULL du vrai schéma (`plan_id`, `subscription_start`/`subscription_end`, `language`, `currency`, `first_name`/`last_name`)
  - Le test de sécurité `KioskCrossTenantIsolationTest` (surface kiosk du module Attendance) est migré lui aussi ; les appels aux tables SmartAttendance créées à la main sont supprimés (tables désormais créées par les migrations)

## [4.21.0] - 2026-07-01

### Changed
- **Refactor architecture DDD** : suppression des classes legacy doublonnées
  - 90 controllers supprimés dans `app/Http/Controllers/Api/V1/` (doublons des modules DDD)
  - 26 services supprimés dans `app/Services/` (51 consommateurs migrés vers les modules)
  - `app/DTOs/` racine supprimé (`CheckInDTO`, `CreateEmployeeDTO`, `UpdateEmployeeDTO` migrés)
  - Infrastructure créée pour `Growth`, `Platform`, `Onboarding`, `Training` (corrige 4 violations CI)
  - Conservés : `EdgeController`, `EdgeDownloadController`, `SSO/SSOController`
  - Surface API inchangée — aucune régression

## [4.18.0] - 2026-06-29

### Added
- **Module SmartAttendance** : pointage GPS automatique par géofencing
  - Phases 3/4/5 : Flutter geofencing (leopardo_employee), dashboard Manager/RH, tests Feature complets
  - API : endpoints `/api/v1/smart-attendance/*` (config, geo-events, sessions, validation, dashboard)
  - Modèles : `GeoAttendanceSession`, `EmployeeLocationEvent`, `AttendanceModeSettings`, `EmployeeAttendancePreferences`
  - Commande Artisan `smart-attendance:auto-close` — fermeture automatique des sessions GPS orphelines
  - Scheduler : `Schedule::command('smart-attendance:auto-close --hours=14')->hourly()`
  - Tests Feature : `AttendanceModeConfigTest`, `GeoEntryExitTest`, `GeoSessionDashboardTest`, `ManagerValidationTest`, `MultiTenantIsolationTest`
  - Trait de test `CreatesSmartAttendanceSchema` avec création/suppression des 4 tables
  - Flutter Employee : écran `SmartAttendanceScreen`, `GeofenceService` (Haversine), `BackgroundLocationService`
  - Flutter Manager/HR : feature `smart_attendance` avec écrans validation (liste pending + approve/reject), dashboard stats, navigation
  - Web dashboard : page `/smart-attendance` avec sessions listing, approbation et settings
  - Permissions Android : `ACCESS_BACKGROUND_LOCATION`, `FOREGROUND_SERVICE`, service `BackgroundWorker`
  - Permissions iOS : `NSLocationAlwaysAndWhenInUseUsageDescription`, `UIBackgroundModes` (fetch, processing)

## [4.17.0] - 2026-06-15

### Added
- Module IA : chat conversationnel et commandes vocales (léopardo_employee + leopardo_manager)
- Module Véhicules : suivi position GPS flotte (leopardo_manager)

### Fixed
- Correction timeout auth Sanctum sur reconnexion réseau mobile
