> **Note (2026-09-01, audit #6599)** : le module `SmartAttendance` a été fusionné dans `api/app/Modules/Attendance` (ADR-0016 Phase 5, 2026-08-24). Les chemins `Modules/SmartAttendance/**` ci-dessous sont **historiques** — l'action/module vit désormais sous `Modules/Attendance/**`.

# Feature Specification: SmartAttendance — couverture de tests (issue #3887)

**Feature Branch**: `fix/3887-smartattendance-tests`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Constat QA 2026-08-15 — le module `SmartAttendance` (présence intelligente GPS) est le **seul module métier sans aucun test** ; il gère le pointage GPS, critique pour les entreprises terrain.

## Problème

- 0 test sur `GeoAttendanceController` (événements GPS), `GeoSessionController` (sessions + validation) et `AttendanceModeController` (config de mode).
- Le schéma de test MVP (CreatesMvpSchema) ne crée pas les tables SmartAttendance (seulement des DROP au teardown).

## Décision

Créer `tests/Feature/SmartAttendance/SmartAttendanceFlowTest.php` (trait `RefreshTenantDatabase`) :

1. **Schéma** : les 4 tables SmartAttendance (`geo_attendance_sessions`, `employee_location_events`, `attendance_mode_settings`, `employee_attendance_preferences`) créées dans le setUp du test, alignées sur les migrations `tenant/2026_06_29_0002xx`.
2. **Flux GPS** (`GeoAttendanceController::event`) :
   - `zone_enter` → 201, session `detected` + event `zone_enter` loggé ;
   - doublon → 409 `SESSION_ALREADY_OPEN` (pas de 2e session) ;
   - hors géofence (metadata entreprise) → 422 `OUTSIDE_GEOFENCE` + event `outside_zone`, 0 session ;
   - `zone_exit` → 201 `pending_validation` + durée calculée ;
   - exit orphelin → 200 idempotent ;
   - event_type inconnu → 422 (validation).
3. **Sessions** (`GeoSessionController`) : `my-sessions` ne liste que ses propres sessions ; RBAC `sessions` index (ordinary → 403, manager rh → OK) ; approve manager → `approved`.
4. **Mode** (`AttendanceModeController`) : config défaut (`manual`, gps désactivé, override autorisé) ; config mode forcé (`gps_auto`, consent requis, override interdit) ; `updatePreference` bloqué quand l'entreprise force un mode (403 `COMPANY_MODE_FORCED`).

## User Scenarios & Testing

### User Story 1 — Le pointage GPS a une couverture de régression (Priority: P2)

**Independent Test**: `php artisan test --filter=SmartAttendanceFlowTest` → 11 scénarios verts en CI (PostgreSQL 16).

**Acceptance Scenarios**:

1. **Given** un employé, **When** il entre en zone, **Then** une session `detected` est créée (201).
2. **Given** une session ouverte, **When** un 2e `zone_enter` arrive, **Then** 409 `SESSION_ALREADY_OPEN`.
3. **Given** une position hors géofence, **When** `zone_enter`, **Then** 422 `OUTSIDE_GEOFENCE` sans session.
4. **Given** un manager RH, **When** il approuve une session fermée, **Then** statut `approved`.
5. **Given** un employé ordinary, **When** il liste les sessions entreprise, **Then** 403.
6. **Given** une entreprise au mode forcé, **When** l'employé change sa préférence, **Then** 403 `COMPANY_MODE_FORCED`.

## Edge Cases

- Schéma minimal (pas de FK `sites`/`employees` contraintes — le site est null par défaut) : les relations non utilisées par les scénarios ne sont pas sollicitées.
- Colonnes de validation (`validated_by`, `validated_at`, `validation_note`) incluses — `formatSession` accède à la relation `validatedBy`.
- Géofence via `metadata.attendance_geofence` (source `company_metadata`) — le site (source `site`) reste non testé (hors périmètre, dépend de la table `sites`).
