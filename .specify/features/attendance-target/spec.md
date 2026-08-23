# Feature Specification: Pointage 100 % — module Attendance fusionné (issue #5264)

**Feature Branch**: `mod/attendance/5264-adr-fusion`

**Created**: 2026-08-23

**Status**: Draft — en attente d'approbation fondateur (ADR 0016)

**Input**: Issue #5264 — ADR fusion Attendance/SmartAttendance + spec du pointage cible. Références : ADR-0016 (`docs/architecture/adr/0016-attendance-smartattendance-fusion.md`), index `docs/architecture/POINTAGE_100PCT.md`, issues #5265 (modes unifiés), #5266 (heures sup DZ), #5267 (corrections), #5268 (rapports), #5269 (tests/i18n/docs).

## Problème

Deux modules de pointage coexistent (Attendance : kiosque/ZKTeco/mobile/approbations ; SmartAttendance : géo/modes), avec une frontière poreuse : deux géofences, deux fermetures automatiques, une table de fait unique (`attendance_logs`) déjà écrite par les deux modules. Le programme « Pointage 100 % » exige un module cible unique, documenté, sans dette de duplication.

## Décision

Cible = **un seul module `Attendance`** (fusion progressive ADR-0016, 5 phases), dont la présente spec décrit le comportement cible « 100 % » : modes de pointage unifiés (kiosque, géo, ZKTeco, mobile, QR, manuel), règles de calcul unifiées, workflow de validation unique, rapports et exports, i18n ×4, RBAC et isolation tenant, tests.

**Périmètre API cible** : toutes les routes de pointage sous `/api/v1/attendance/*` (les routes `/smart-attendance/*` sont conservées en alias pendant la transition puis supprimées).

## User Scenarios & Testing

### User Story 1 — Un employé pointe avec n'importe quel mode, un seul pointage (Priority: P1)

L'employé peut pointer selon le mode configuré par son entreprise : app mobile (bouton, photo obligatoire éventuelle), géolocalisation automatique (entrée/sortie de zone), QR code au kiosque, badge biométrique ZKTeco, ou saisie manuelle. **Quel que soit le mode, le résultat est un `attendance_log`** tracé avec sa méthode (`mobile`, `qr`, `biometric`, `manual`, `geo_auto`), ses coordonnées GPS éventuelles et sa photo éventuelle.

**Why this priority**: c'est le parcours quotidien de tout employé ; la fusion des modes est l'objet de #5265.

**Independent Test**: `POST /api/v1/attendance/check-in` avec `method=mobile` et `POST /api/v1/attendance/geo-sessions` (entrée de zone) créent tous deux un `attendance_log` (ou une session qui en génère un à l'approbation), observable via `GET /api/v1/attendance/today`.

**Acceptance Scenarios**:

1. **Given** une entreprise au mode `manual`, **When** l'employé appelle `check-in` depuis le mobile, **Then** un `attendance_log` `method=manual` est créé (201).
2. **Given** une entreprise avec géofence active et mode `gps_auto`, **When** l'employé entre dans la zone, **Then** une session `geo_attendance_sessions` est ouverte (201), et **When** le manager approuve, **Then** un `attendance_log` `method=geo_auto` est créé avec les coordonnées.
3. **Given** une entreprise exigeant une photo (`punch_photo_mode=photo_required`), **When** l'employé pointe sans photo, **Then** 422 `PUNCH_PHOTO_REQUIRED` (comportement existant #4925 préservé).
4. **Given** un pointage effectué, **When** l'employé consulte `today`, **Then** la méthode, les heures et l'état (ontime/late/incomplete) sont restitués.

### User Story 2 — L'entreprise configure un mode de pointage unique (Priority: P1)

Le principal configure un mode d'entreprise (`attendance_mode_settings`) : mode forcé (gps_auto / qr / manual / mixed), photo obligatoire ou non, géofence (coordonnées + rayon), autorisation ou non de l'override employé.

**Why this priority**: la configuration pilote tous les modes ; elle vit déjà dans `attendance_mode_settings` et doit rester la source de vérité unique (ADR-0016, règle opérationnelle).

**Independent Test**: `PUT /api/v1/attendance/mode-settings` (rôle principal) puis `GET /api/v1/attendance/config` (employé) restitue le mode effectif.

**Acceptance Scenarios**:

1. **Given** un principal, **When** il impose `forced_mode=gps_auto` avec géofence, **Then** `GET config` renvoie `gps_auto` et `allow_employee_override=false`.
2. **Given** un mode forcé, **When** l'employé tente `PUT /api/v1/attendance/preferences`, **Then** 403 `COMPANY_MODE_FORCED`.
3. **Given** un employé hors géofence, **When** il tente un pointage géo, **Then** 422 `OUTSIDE_GEOFENCE` et événement `outside_zone` loggé (minimisation RGPD).

### User Story 3 — Manager/RH valide sessions et corrections sur un seul parcours (Priority: P2)

Le manager voit le tableau de bord du pointage (sessions en attente, corrections, anomalies) et valide/refuse avec traçabilité (`validated_by`, `validated_at`, `validation_note` ; audit des corrections #5267).

**Why this priority**: la validation est le pont entre acquisition et paie ; l'unification évite deux parcours d'approbation.

**Independent Test**: `POST /api/v1/attendance/geo-sessions/{id}/approve` puis `POST /api/v1/attendance/corrections/{correction}/approve` — les deux produisent une décision tracée et un `attendance_log` à jour.

**Acceptance Scenarios**:

1. **Given** une session `pending_validation`, **When** un manager RH approuve, **Then** statut `approved`, `attendance_log` créé, décision enregistrée.
2. **Given** une demande de correction, **When** le manager approuve, **Then** le `attendance_log` est mis à jour et l'état passe `applied` (workflow #5267).
3. **Given** un employé `ordinary`, **When** il liste les sessions de l'entreprise, **Then** 403 (RBAC manager/rh/principal uniquement).

### User Story 4 — Règles de calcul unifiées et tracées (Priority: P2)

Heures travaillées, retards, heures supplémentaires (règles DZ #5266), anomalies (temps plein/partiel, chevauchements) sont calculés par un service unique et visibles par période.

**Why this priority**: la paie consomme `attendance_logs` (PayrollCalculator) ; des règles incohérentes casseraient le bulletin.

**Independent Test**: après deux pointages sur une journée, `GET /api/v1/attendance/monthly-report` restitue `hours_worked`, `overtime_hours`, `late_minutes` conformes aux règles.

**Acceptance Scenarios**:

1. **Given** un check-in 08:05 et check-out 17:35 (contrat 08:00–17:00), **When** la clôture quotidienne s'exécute, **Then** `late_minutes=5`, `hours_worked=9,5`, `overtime_hours` selon règles pays.
2. **Given** un pointage sans check-out, **When** la fermeture automatique (fusionnée, ADR-0016 Phase 4) s'exécute, **Then** statut `incomplete` et anomalie signalée.

### User Story 5 — Rapports par période et exports (Priority: P2)

Rapports day/week/month avec filtres équipe/employé et exports CSV/PDF (livrable #5268, consommation directe de la spec).

**Why this priority**: exigence pilote (justificatifs de présence) ; la spec cible garantit que #5268 et #5264 convergent.

**Independent Test**: `GET /api/v1/attendance/reports?period=month&team_id=X` renvoie un rapport paginé ; `GET /api/v1/export/attendance?format=csv` produit un CSV.

**Acceptance Scenarios**:

1. **Given** un mois de pointages, **When** le rapport mensuel est demandé, **Then** les totaux par employé et par équipe sont cohérents avec les `attendance_logs`.
2. **Given** un export demandé, **When** le format est CSV/PDF, **Then** le fichier est téléchargeable et localisé (i18n ×4).

## Edge Cases

- **Mode non configuré** : `attendance_mode_settings` absent → comportement défaut `manual`, géofence désactivée, override autorisé (rétro-compatibilité, testé `AttendanceModeConfigTest`).
- **Doublon d'entrée géo** : une session `detected` ouverte → second `zone_enter` → 409 `SESSION_ALREADY_OPEN` (pas de session double).
- **Exit orphelin** : `zone_exit` sans session → 200 idempotent.
- **Hors-ligne / EdgeSync** : les pointages kiosque/ZKTeco bufferisés (`synced_from_offline`) convergent vers `attendance_logs` sans doublon (unique `(employee_id, date, session_number)`).
- **RTL arabe** : bulletins/rapports PDF en arabe rendus RTL (i18n ×4).
- **Multi-tenant** : chaque table garde `company_id` ; les modèles sans `company_id` direct (`approval_*`) restent isolés via parent FK (convention ADR-0001).

## Requirements

### Functional Requirements

- **FR-001** : tout pointage, quel que soit le mode, MUST aboutir dans `attendance_logs` (directement ou via approbation) avec `method` tracé et `company_id` valide.
- **FR-002** : le mode d'entreprise MUST être résolu via `attendance_mode_settings` (source de vérité unique) ; `forced_mode` MUST primer sur la préférence employé.
- **FR-003** : la géofence MUST être évaluée par `AttendanceGeofenceService` (implémentation unique, ADR-0016) ; un pointage hors zone MUST être refusé (422) ou tracé selon la politique entreprise.
- **FR-004** : le workflow de validation (sessions GPS, corrections, demandes) MUST utiliser le trait `Approvable` partagé avec traçabilité (`validated_by`, `validated_at`).
- **FR-005** : les règles de calcul (heures, retards, HS DZ) MUST être centralisées dans les services du module Attendance, sourcées (texte légal) et auditables (#5266).
- **FR-006** : les rapports/export MUST être filtrables par période (day/week/month), équipe et employé, et exportables CSV/PDF (#5268).
- **FR-007** : l'API MUST être documentée dans `api/openapi.yaml` et gardée par la couverture routes↔spec (openapi-ci).
- **FR-008** : i18n ×4 (fr/ar/tr/en) — 0 chaîne hardcodée sur les surfaces UI/messages du pointage ; RTL vérifié (#5269).
- **FR-009** : RBAC MUST respecter la matrice (employé self-service, manager/rh validation, principal configuration) avec isolation tenant testée (#5262 pattern).
- **FR-010** : la fermeture automatique des sessions/pointages incomplets MUST être unique (`AutoCloseAttendanceCommand`).

### Key Entities

- **AttendanceLog** : table de fait centrale — `company_id`, `employee_id`, `date`, `check_in`, `check_out`, `method` (mobile|qr|biometric|manual|geo_auto), `status`, `hours_worked`, `overtime_hours`, `late_minutes`, `gps_lat/lng`, `punch_meta`, `punch_photo_path`, `corrected_by`, `correction_note`.
- **GeoAttendanceSession** : session GPS (entrée/sortie) — `status` detected|pending_validation|approved|rejected|cancelled, `attendance_log_id` créé à l'approbation.
- **EmployeeLocationEvent** : événement GPS minimisé (RGPD) — `zone_enter/exit`, `session_start/end`, consentement, erreurs géofence.
- **AttendanceModeSettings** : configuration mode entreprise — `forced_mode`, `punch_photo_mode`, géofence (`gps_enabled`, `latitude`, `longitude`, `radius_meters`), `allow_employee_override`.
- **EmployeeAttendancePreference** : préférence employé (si override autorisé).
- **AttendanceKiosk / ZktecoDevice** : dispositifs d'acquisition (kiosque, ZKTeco) avec capacités et méthodes de pointage.
- **AttendanceCorrectionRequest** : demande de correction liée à un `attendance_log`.
- **ApprovalWorkflow/Request/Decision** : workflow d'approbation générique partagé (polymorphe).

## Success Criteria

### Measurable Outcomes

- **SC-001** : 100 % des pointages (tous modes) sont lisibles dans `attendance_logs` — vérifié par les tests de bout en bout (#5269) et le rapport mensuel.
- **SC-002** : 0 route `/smart-attendance/*` nouvelle après la bascule (garde CI) ; 0 import `App\Modules\SmartAttendance\*` après la Phase 5 (ADR-0016).
- **SC-003** : une seule implémentation de géofence et une seule fermeture automatique (vérifiable par grep dans Architecture Quality).
- **SC-004** : couverture de tests du module ≥ 70 % ; CI verte (gate programme 100 %).
- **SC-005** : i18n ×4 sans chaîne hardcodée sur les surfaces pointage ; PDF/rapports rendus RTL en arabe.
- **SC-006** : un pilote réalise le parcours complet (pointer → valider → rapporter → exporter) sans assistance (recette).

## Assumptions

- Les apps mobiles (employee/hr/manager) sont mises à jour vers les routes `/attendance/*` lors de la bascule (Phase 3-5 ADR-0016), avec alias 308 pendant la transition.
- `attendance_logs` reste la seule table consommée par la paie (aucun changement de contrat pour Payroll).
- Le volume par tenant reste dans la capacité d'un schéma tenant PostgreSQL unique (≤ 5 M lignes ; index existants `(employee_id, date)`, `(date, status)`).
- L'approbation fondateur de l'ADR-0016 est requise avant la Phase 2 (les Phases 2-5 ne démarrent qu'après validation).
- Le travail en cours des agents #5267 (corrections) et #5268 (rapports) continue sur `api/app/Modules/Attendance/**` sans collision avec les phases ADR (chemins disjoints).
