# Feature Specification: Pointage 100 % — modes unifiés, règles de calcul unifiées, fermeture de journée (issue #5265)

**Feature Branch**: `mod/attendance/5265-unified-modes`

**Created**: 2026-08-24

**Status**: Implemented

**Input**: Issue #5265 — « [P1] Pointage 100 % — modes unifiés (kiosque, géo, ZKTeco, mobile) + règles de calcul ». Références : ADR-0016 (`docs/architecture/adr/0016-attendance-smartattendance-fusion.md`), spec cible `.specify/features/attendance-target/spec.md` (FR-001→010), index `docs/architecture/POINTAGE_100PCT.md`.

## Problème

Le calcul des heures travaillées, retards et heures supplémentaires est **dupliqué en 4 blocs quasi identiques** : 3 blocs dans `AttendanceService` (`checkIn`, `checkOut`, `importExternalPunch`, `recalculateLog`) et 1 bloc divergent dans `ApproveGeoSession` (module SmartAttendance). Conséquences :

1. **Divergence de règles entre modes** : le mode géo (`ApproveGeoSession`) calcule `hours_worked` depuis `duration_seconds` **sans déduire les pauses** du planning, alors que le mode mobile/kiosque/ZKTeco déduit `break_minutes` — deux employés avec le même planning et la même présence réelle obtiennent des heures différentes selon le mode de pointage. La paie (`PayrollCalculator`) consomme `attendance_logs` indifféremment du mode (FR-001/FR-005) : les bulletins héritent de cette incohérence.
2. **Pas de fermeture de journée** : un jour clôturé/validé par un manager peut encore recevoir des pointages (check-in/check-out, import ZKTeco, approbation de session géo), ce qui fausse les rapports et la paie après clôture. Le verrouillage de **période mensuelle** (#5267, `attendance_period_closures`) couvre les corrections ; il manque le verrouillage **quotidien** des pointages eux-mêmes (tâche « Fermeture de journée (verrouillage + validation) » de #5265).

## Décision

1. **Un calculateur pur unique `AttendanceHoursCalculator`** (`api/app/Modules/Attendance/Infrastructure/Services/`) : retard (tolérance), heures travaillées (pauses déduites), heures supplémentaires (seuil quotidien + cas `work_type=overtime` + types non travaillés `break`/`resume`). **Zéro changement de formule** pour le chemin mobile/kiosque/ZKTeco (parité prouvée par les tests existants `CheckInTest`/`CheckOutTest`/`MultiPunchTest`/`ManualUpdateTest`) ; le chemin géo converge vers les mêmes règles (les pauses du planning s'appliquent désormais aussi aux sessions GPS approuvées — unification FR-005).
2. **Fermeture de journée** `attendance_day_closures` (complémentaire du verrouillage mensuel #5267) : verrouillage + validation par `(company, employee, date)`, garde **409 `ATTENDANCE_DAY_CLOSED`** sur tout nouveau pointage (check-in, check-out, import externe, approbation de session géo) d'un jour clos, RBAC manager/rh/principal, i18n ×4, OpenAPI documenté.

**Hors périmètre** : le temps réel mobile (RTMX #5280, mergé ✅) ; les heures sup DZ (#5266, livré) ; le workflow de corrections (#5267, PR #5314) ; la fusion structurelle Phase 4/5 ADR-0016 (#5355/#5356) ; les rapports (#5268, livré).

## User Scenarios & Testing

### User Story 1 — Un employé géo et un employé mobile avec le même planning obtiennent les mêmes heures (Priority: P1)

Le manager approuve une session GPS (entrée 08:00, sortie 17:00, planning 08:00–17:00, pause 60 min, seuil HS 8 h) ; un employé mobile fait check-in 08:00 / check-out 17:00 avec le même planning. **Les deux** `attendance_log` portent `hours_worked = 8.0`, `overtime_hours = 0.0`, `late_minutes = 0`, `status = ontime` — une seule source de vérité de calcul, quel que soit le mode.

**Why this priority**: cœur du DoD « les heures calculées correspondent au contrôle manuel » — la paie consomme `attendance_logs` sans distinguer le mode.

**Independent Test**: `AttendanceHoursCalculatorTest` (calcul pur) + `AttendanceCalculationParityTest` (Feature : check-in/check-out mobile) + `ManagerValidationTest` mis à jour (approbation géo → heures déduites des pauses) — vérifie les valeurs calculées à la main.

**Acceptance Scenarios**:

1. **Given** un planning 08:00–17:00 (pause 60 min, tolérance 0 min, seuil 8 h) et un check-in 08:05 / check-out 17:35, **When** le calcul s'exécute, **Then** `late_minutes = 5`, `hours_worked = 8.5`, `overtime_hours = 0.5` (jeu doré calculé à la main : 9 h 30 brutes − 60 min de pause = 8,5 h).
2. **Given** une session GPS approuvée (début 08:00, fin 17:00, même planning), **When** `ApproveGeoSession` crée le log, **Then** `hours_worked = 8.0` (pauses déduites — convergence avec le mobile).
3. **Given** un log `work_type = break` ou `resume`, **When** le calcul s'exécute, **Then** `hours_worked = 0` et `overtime_hours = 0` (règle #2686 conservée).
4. **Given** un check-in 08:00 / check-out 18:30 avec `work_type = overtime`, **When** le calcul s'exécute, **Then** `hours_worked = 9.5` et `overtime_hours = 9.5` (cas overtime = heures pleines, conservé).

### User Story 2 — Un manager ferme et valide la journée d'un employé ; plus aucun pointage n'est accepté (Priority: P1)

En fin de journée, un manager/RH verrouille la journée d'un employé (`POST /api/v1/attendance/day-closures`), puis la valide (`POST /api/v1/attendance/day-closures/{id}/validate`). Tout pointage ultérieur (check-in, check-out, import ZKTeco/offline, approbation de session géo) portant sur cette date est refusé en **409 `ATTENDANCE_DAY_CLOSED`** avec message localisé. Le déverrouillage (`DELETE`) rouvre la journée.

**Why this priority**: « Fermeture de journée (verrouillage + validation) » est une tâche explicite de #5265 — elle garantit l'intégrité des rapports/paie après clôture.

**Independent Test**: `AttendanceDayClosureTest` (Feature) — verrou → 201 ; re-verrou idempotent ; validation → `validated` ; check-in sur jour clos → 409 ; check-out → 409 ; import externe → 409 ; approbation géo → 409 ; déverrouillage → 204 puis check-in OK ; RBAC employé → 403 ; isolation tenant.

**Acceptance Scenarios**:

1. **Given** un manager authentifié, **When** il POST `/api/v1/attendance/day-closures` (`employee_id`, `date`), **Then** 201, verrou créé (`locked_by`, `locked_at`).
2. **Given** un jour verrouillé, **When** l'employé tente `POST /api/v1/attendance/check-in` ce jour-là, **Then** 409 `ATTENDANCE_DAY_CLOSED` (message localisé ×4).
3. **Given** un jour verrouillé avec session GPS en attente, **When** le manager approuve la session, **Then** 409 `ATTENDANCE_DAY_CLOSED` (aucun log créé).
4. **Given** un verrou existant, **When** le manager valide, **Then** `status = validated`, `validated_by`/`validated_at` renseignés.
5. **Given** un verrou, **When** un employé (rôle `employee`) tente de le créer/lister, **Then** 403.
6. **Given** un employé d'un autre tenant, **When** le manager tente de le verrouiller (`POST day-closures`), **Then** 422 `employee_id.exists` (fail-closed : validation scopée entreprise) ; **When** il tente de supprimer/valider la fermeture d'un autre tenant, **Then** 404 (`findOrFail` scopé).

### User Story 3 — Le manager liste et suit les fermetures de journée (Priority: P2)

Le manager consulte les jours fermés/validés par date et/ou employé (`GET /api/v1/attendance/day-closures?date=&employee_id=`), avec le statut (`locked`/`validated`), l'auteur et la date de verrouillage/validation — traçabilité complète de la clôture.

**Why this priority**: la validation quotidienne est un rituel de fin de journée ; la visibilité évite les clôtures accidentelles.

**Independent Test**: même `AttendanceDayClosureTest` — assertions sur la liste filtrée et le statut.

**Acceptance Scenarios**:

1. **Given** deux verrous (un validé, un non), **When** `GET /api/v1/attendance/day-closures`, **Then** les deux apparaissent avec leur statut et horodatages.
2. **Given** un filtre `employee_id`, **When** la liste est demandée, **Then** seuls les verrous de cet employé reviennent.

## Edge Cases

- **Jour déjà fermé** : le re-verrou est **idempotent** (200, verrou existant retourné) — jamais de doublon (unique `(company_id, employee_id, date)`).
- **Session ouverte au moment du verrou** : le verrou est accepté ; la session ouverte reste `incomplete` et sera fermée par `attendance:auto-close` (ADR-0016 FR-010) — le 409 ne bloque que les **nouveaux** pointages. Documenté dans le runbook.
- **Employé sans planning** : `hours_worked` calculé sans pause (formule historique), `late_minutes = 0`, `status = incomplete` (comportement existant conservé).
- **Jour clos + correction** : le verrouillage des corrections est couvert par le verrouillage de **période** #5267 (422 `ATTENDANCE_PERIOD_CLOSED`) ; la fermeture de journée est complémentaire (garde sur les pointages, 409) — les deux mécanismes coexistent sans se chevaucher.
- **Fuseau horaire** : la date de fermeture est la date **locale entreprise** (`company.timezone`), cohérente avec la date des `attendance_logs`.
- **Multi-tenant** : `attendance_day_closures.company_id` obligatoire ; tout accès cross-tenant → 404 (fail-closed).

## Requirements

### Functional Requirements

- **FR-001** : tout pointage, quel que soit le mode (mobile, kiosque, géo, ZKTeco, import externe), MUST être calculé par `AttendanceHoursCalculator` (source de vérité unique — FR-005 spec cible).
- **FR-002** : le calcul des heures MUST déduire `break_minutes` du planning pour la session 1 (règle historique `breakMinutesForLog`), sauf `work_type = break`.
- **FR-003** : le retard MUST être `max(0, floor(diffMinutes - tolerance))` avec `status = late|ontime` (formule historique).
- **FR-004** : les heures supplémentaires MUST être `hours - threshold` (seuil `overtime_threshold_daily`), `hours` pleines si `work_type = overtime`, 0 pour `break`/`resume` (#2686).
- **FR-005** : un jour verrouillé MUST refuser tout nouveau pointage (check-in/check-out/import/approbation géo) avec 409 `ATTENDANCE_DAY_CLOSED`.
- **FR-006** : la fermeture de journée MUST être idempotente, tracée (`locked_by/at`, `validated_by/at`) et unique par `(company, employee, date)`.
- **FR-007** : la validation de journée MUST être un acte distinct du verrouillage (`POST .../validate`), réservé manager/rh/principal.
- **FR-008** : i18n ×4 — 0 chaîne hardcodée (messages `attendance.*` + `errors.ATTENDANCE_DAY_CLOSED` en fr/en/tr/ar).
- **FR-009** : RBAC — lecture et écriture des fermetures réservées `api.manager:rh,principal` ; isolation tenant testée (404 cross-tenant).
- **FR-010** : OpenAPI — les routes de fermeture documentées dans `api/openapi.yaml` + miroir/SDK régénérés (`make openapi-sync`).

### Key Entities

- **AttendanceHoursCalculator** : calculateur pur (retard, heures, HS) — `Infrastructure/Services/`.
- **WorkedHoursDTO / LateAssessmentDTO** : résultats typés du calculateur — `Application/DTOs/`.
- **AttendanceDayClosure** : verrou de journée — `company_id`, `employee_id`, `date`, `status` (`locked|validated`), `locked_by`, `locked_at`, `validated_by`, `validated_at`, `note` ; unique `(company_id, employee_id, date)`.
- **AttendanceDayClosedException** : erreur domaine 409 `ATTENDANCE_DAY_CLOSED` — `Domain/Exceptions/`.
- **AttendanceDayClosureService** : `isDayClosed`, `assertDayOpen` (throws), `lockDay` (idempotent), `validateDay`, `unlockDay`, `listFor` — `Infrastructure/Services/`.

## Success Criteria

- **SC-001** : parité — les tests existants (`CheckInTest`, `CheckOutTest`, `MultiPunchTest`, `ManualUpdateTest`, `AttendanceI18nTest`, suites SmartAttendance) restent **verts sans modification de leurs assertions** (zéro changement de formule mobile/kiosque).
- **SC-002** : jeu doré — `AttendanceHoursCalculatorTest` couvre le scénario de contrôle manuel (retard 5 min, pause 60 min, seuil 8 h → `8.5 h` / `0.5 h` HS) + cas `overtime` + `break`/`resume` + multi-sessions.
- **SC-003** : géo convergent — `ManagerValidationTest` (ou son équivalent) prouve que l'approbation d'une session GPS déduit les pauses du planning.
- **SC-004** : fermeture — `AttendanceDayClosureTest` couvre verrou/validation/déverrou/409 ×4 points d'entrée/RBAC/isolation tenant.
- **SC-005** : qualité — PHPStan strict level 8 `[OK]`, Pint `--test` vert, garde OpenAPI verte, CHANGELOG (racine + api) en tête d'[Unreleased], PR `Closes #5265`.

## Assumptions

- Le verrouillage de période #5267 (`attendance_period_closures`, PR #5314) reste orthogonal : il cible les corrections (422), la fermeture de journée cible les pointages (409). Aucune dépendance code entre les deux (self-contained).
- `attendance:auto-close` reste autorisé sur un jour clos (fermeture des sessions orphelines — opération système, pas un nouveau pointage).
- Pas de modification du moteur de paie (FOCUS intact) : `attendance_logs` garde le même contrat.
