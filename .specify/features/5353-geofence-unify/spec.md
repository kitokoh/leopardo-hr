# Feature Specification: ADR-0016 Phase 2 — unifier les chemins d'usage géofence (issue #5353)

**Feature Branch**: `mod/attendance/5353-geofence-unify`

**Created**: 2026-08-23

**Status**: Implemented

**Module**: `attendance` — périmètre : `api/app/Modules/Attendance/**` +
`api/app/Modules/SmartAttendance/**` (chemins d'usage géofence),
`dev-hub/tools/check-geofence-single-usage.sh` (garde CI),
`.github/workflows/architecture-check.yml` (étape garde).

## Contexte

ADR-0016 (`docs/architecture/adr/0016-attendance-smartattendance-fusion.md`,
mergée #5318) — **Phase 2** : `AttendanceGeofenceService` est déjà l'unique
implémentation (Haversine), mais consommée par **deux chemins d'usage** :

| Chemin | Point d'appel | Politique |
|---|---|---|
| Punch mobile | `AttendanceService::buildPunchMeta()` | **Informative** (PA2-ATT-009 « pointage tolerant GPS » — alerte managers, jamais bloquant) |
| Session GPS | `GeoSessionManager::openSession()` | **Bloquante** (anti-spoofing #4255 — `OutsideGeofenceException` 422 + événement `outside_zone`) |
| Résolution site | `GeoSessionManager::resolveSiteId()` | Calcul de distance direct (`distanceMeters`) |

## Décision

Créer **`GeofenceZoneService`** (`Attendance/Infrastructure/Services/`) comme
**chemin d'usage UNIQUE** de la géofence — unique consommateur direct de
`AttendanceGeofenceService` :

- `evaluateZone()` → évaluation pure (jamais d'exception) — consommée par le punch.
- `assertInsideZone()` → politique bloquante centralisée (même
  `OutsideGeofenceException` partout) — consommée par les sessions GPS.
- `resolveSiteId()` → résolution du site assigné par distance (déplacée de
  `GeoSessionManager`) — même logique, centralisée.

La **même politique d'erreurs** = la décision « configuré && dehors » n'existe
qu'en un seul endroit (`assertInsideZone`) ; aucun caller ne la ré-implémente.
L'événement `outside_zone` reste un effet de bord de l'acquisition
(`GeoSessionManager` catch → log → rethrow), hors transaction (#4255 préservé).

## User Scenarios & Testing

### US1 — Un seul chemin d'usage (DoD)

**Independent Test**: garde CI `dev-hub/tools/check-geofence-single-usage.sh` +
suite `tests/Feature/Attendance/GeofenceZoneServiceTest.php` (9 tests).

**Acceptance Scenarios**:

1. **Given** le module, **When** la garde CI s'exécute, **Then** seuls
   `GeofenceZoneService`, `AttendanceGeofenceService` (implémentation), le
   binding provider et le contrat sont autorisés à référencer
   `AttendanceGeofenceService` — tout autre fichier = rouge.
2. **Given** un employé hors zone, **When** le punch mobile est émis, **Then**
   le punch est accepté avec `punch_meta.geofence.inside=false` (informatif).
3. **Given** une session GPS hors zone, **When** `openSession` est appelé,
   **Then** 422 `OutsideGeofenceException` + événement `outside_zone` loggé.
4. **Given** un employé avec site assigné, **When** la position est dans le
   rayon, **Then** `resolveSiteId()` retourne le site (null sinon).

### US2 — attendance_mode_settings = source de vérité (DoD)

`attendance_mode_settings` reste la table canonique des modes (kiosque/géo/
QR/manuel/mixte) — déjà consommée par `AttendanceModeResolver` (forced_mode /
override) et `AttendanceService` (photo obligatoire). Documenté dans
`docs/architecture/POINTAGE_100PCT.md` (garde-fous) ; aucune seconde source
introduite.

## Edge Cases

- **Commentaires** : la garde grep ignore les lignes de commentaires (faux
  positifs) et ne vérifie que les usages de code effectifs.
- **Site sans coordonnées** : `whereNotNull('gps_lat','gps_lng')` — un site
  sans GPS ne peut jamais matcher (même résultat qu'avant, sans garde runtime).
- **Anti-spoofing #4255** : l'événement `outside_zone` reste loggé HORS
  transaction, AVANT le 422 — le refactor try/catch préserve l'ordre.

## Deliverables

- [x] Spec `.specify/features/5353-geofence-unify/spec.md`
- [x] `api/app/Modules/Attendance/Infrastructure/Services/GeofenceZoneService.php` (chemin d'usage unique)
- [x] Refactor `AttendanceService` (punch → `evaluateZone`) + `GeoSessionManager` (session → `assertInsideZone`, site → `resolveSiteId`)
- [x] Garde CI `dev-hub/tools/check-geofence-single-usage.sh` + étape `architecture-check.yml`
- [x] `api/tests/Feature/Attendance/GeofenceZoneServiceTest.php` (9 tests / 18 assertions)
- [x] Doc `docs/architecture/POINTAGE_100PCT.md` (statut phases + garde-fou chemin unique)
- [x] CHANGELOG `[Unreleased]` + PR `Closes #5353`

## Validation

- Suite existante : **111 tests / 486 assertions** (SmartAttendance + Attendance) verts — DoD « tests inchangés » ✅
- Nouveaux tests : 9/9 ✅ · PHPStan level 8 : 0 erreur · Pint : PASS
