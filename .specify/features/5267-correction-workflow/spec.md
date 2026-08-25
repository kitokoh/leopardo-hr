# Feature Specification: Corrections de pointage — workflow complet (issue #5267)

**Feature Branch**: `mod/attendance/5267-correction-workflow`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5267 — « Corrections/validations de pointage — workflow d'approbation + audit ».
Le workflow de base existe (POST/GET `/attendance/corrections`, approve/reject manager,
admin web, `CorrectionWorkflowTest`). Cette spec couvre le **gap réel** : justificatif,
verrouillage de période, audit trail, anti-fraude.

## Problème

Le DoD « aucune correction sans approbation tracée » est partiellement satisfait, mais :
1. **Justificatif** : `requestCorrection` n'accepte que le motif (`reason`) — aucune pièce jointe (contrairement aux absences).
2. **Verrouillage après clôture de période** : aucune notion de période close — une correction peut être demandée/appliquée sur un mois déjà clôturé (paie/pointage).
3. **Audit trail** : request/approve/reject n'écrivent aucune entrée `AuditLog` (standard du repo).
4. **Anti-fraude** : `AttendanceAnomalyService` n'est pas branché sur les corrections — une demande contradictoire avec une session géo validée n'est pas signalée.

## User Scenarios & Testing

### User Story 1 — Un employé joint un justificatif à sa demande (Priority: P1)

`POST /attendance/corrections` accepte un fichier optionnel `proof` (multipart, ≤ 5 Mo,
jpg/jpeg/png/pdf/heic — mêmes règles que les absences), stocké sur disque privé sous
`attendance-corrections/proofs/{company}/…`, exposé via `proof_url` dans la liste et
téléchargeable par l'employé propriétaire et les managers via
`GET /attendance/corrections/{correction}/proof`.

**Independent Test**: `CorrectionWorkflowV2Test::test_employee_can_attach_and_download_proof`.

### User Story 2 — Une période clôturée verrouille les corrections (Priority: P1)

Un manager/RH clôture une période (mois) via `attendance:close-period --company=… --month=YYYY-MM`.
Toute demande ou décision de correction portant sur une date de la période close est
refusée (422 `ATTENDANCE_PERIOD_CLOSED`). La clôture est idempotente et tracée (AuditLog).

**Independent Test**: `CorrectionWorkflowV2Test::test_correction_blocked_after_period_closed`.

### User Story 3 — Chaque décision est tracée dans AuditLog (Priority: P1)

request/approve/reject écrivent une entrée `AuditLog` (action
`attendance_correction_requested|approved|rejected`, old/new values, auditable =
`AttendanceCorrectionRequest`).

**Independent Test**: assertions `assertDatabaseHas('audit_logs', …)` dans
`CorrectionWorkflowV2Test`.

### User Story 4 — Les incohérences géo sont signalées (Priority: P2)

La liste des corrections expose `anomaly`: si une session géo **approuvée**
(`GeoAttendanceSession`, statut `approved`) existe pour l'employé+date et que les
horaires demandés divergent de plus de 15 minutes des horaires géo, la correction est
marquée `flagged: true` avec `reason: geo_session_conflict`.

**Independent Test**: `CorrectionWorkflowV2Test::test_geo_conflict_is_flagged`.

## Edge Cases

- Correction sans `attendance_log_id` + période close → bloquée (contrôle sur `date`).
- `proof` non fourni → `proof_url: null`, `download` → 404 `NO_PROOF_ATTACHED`.
- Download cross-tenant → 404 (jamais de fuite).
- Clôture idempotente (2 appels → 1 ligne) ; `--month` invalide → 422.
- Approve après clôture → 422 `ATTENDANCE_PERIOD_CLOSED` (la période est verrouillée).
- Session géo rejetée/cancelled → pas de flag.

## Requirements

### Functional Requirements

- **FR-001**: `AttendanceCorrectionRequest.proof_path` (colonne nullable) + upload optionnel en multipart.
- **FR-002**: Téléchargement du justificatif réservé au propriétaire et aux managers du tenant.
- **FR-003**: Table `attendance_period_closures` (company_id, period_start, period_end, closed_by, closed_at ; unique company+période).
- **FR-004**: Service `AttendancePeriodClosureService` : `isDateClosed`, `closePeriod` (idempotent + AuditLog), `assertPeriodOpen` (exception 422).
- **FR-005**: Commande `attendance:close-period {--company=} {--month=YYYY-MM}`.
- **FR-006**: AuditLog sur request/approve/reject (old/new values).
- **FR-007**: Flag `anomaly` (géo validée vs horaires demandés, seuil 15 min) dans `corrections()`.
- **FR-008**: openapi.yaml à jour (route proof + schémas), garde couverture 744/744 maintenue.

### Key Entities

- **AttendancePeriodClosure**: clôture d'une période de pointage par tenant.
- **AttendanceCorrectionRequest**: + `proof_path`.

## Success Criteria

- **SC-001**: `CorrectionWorkflowV2Test` vert (≥ 8 tests) ; 0 régression sur `CorrectionWorkflowTest` + suite Attendance.
- **SC-002**: Gardes CI vertes (Pint, PHPStan, openapi-coverage, migrations tenant).
- **SC-003**: CHANGELOG (api + racine) à jour ; PR `Closes #5267`.

## Assumptions

- Le verrouillage s'applique aux demandes et décisions (create/approve/reject) portant
  sur une date dans une période close.
- Le multipart reste rétro-compatible : les clients JSON existants (sans fichier) continuent de fonctionner.
- La clôture est un acte manuel tracé (commande artisan / future UI), pas automatique.
