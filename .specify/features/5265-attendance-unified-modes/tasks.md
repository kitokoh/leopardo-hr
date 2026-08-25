# Tasks: Pointage 100 % — modes unifiés + règles de calcul + fermeture de journée (issue #5265)

**Input**: spec.md + plan.md — `.specify/features/5265-attendance-unified-modes/`

## T1 [P] [US1] Calculateur pur `AttendanceHoursCalculator`
- Créer `api/app/Modules/Attendance/Application/DTOs/LateAssessmentDTO.php` (readonly : `late_minutes:int`, `status:string`)
- Créer `api/app/Modules/Attendance/Application/DTOs/WorkedHoursDTO.php` (readonly : `hours_worked:float`, `overtime_hours:float`)
- Créer `api/app/Modules/Attendance/Infrastructure/Services/AttendanceHoursCalculator.php` :
  - `lateAssessment(Carbon $checkInLocal, Carbon $scheduledStartLocal, int $toleranceMinutes): LateAssessmentDTO` — `max(0, floor(diff - tolerance))`, `late|ontime`
  - `workedHours(Carbon $checkIn, Carbon $checkOut, string $workType, int $breakMinutes, float $threshold): WorkedHoursDTO` — heures − pause/60, HS seuil, cas `work_type=overtime`, zéro pour `break|resume` (NON_WORK_TYPES)
  - `effectiveBreakMinutes(int $sessionNumber, string $workType, ?int $scheduleBreakMinutes, ?string $dtoWorkType): int` — règle `breakMinutesForLog` historique
  - `statusForLate(int $lateMinutes): string`
- `declare(strict_types=1)` (garde PA2-ARCH-009) + docblocks PHPStan level 8

## T2 [P] [US1] Refactor `AttendanceService` — parité stricte
- `api/app/Modules/Attendance/Infrastructure/Services/AttendanceService.php` :
  - injecter `AttendanceHoursCalculator` au constructeur
  - remplacer les blocs retard de `checkIn`, `checkOut` (statut incomplete), `importExternalPunch`, `recalculateLog` par `lateAssessment()`
  - remplacer les blocs heures/HS de `checkOut`, `importExternalPunch`, `recalculateLog` par `workedHours()` (garde `NON_WORK_TYPES` déplacée dans le calculateur)
  - `breakMinutesForLog` → délègue à `effectiveBreakMinutes()`
  - AUCUN changement de formule (vérifier diff)

## T3 [P] [US1] Refactor `ApproveGeoSession` — convergence géo
- `api/app/Modules/SmartAttendance/Application/Actions/ApproveGeoSession.php` :
  - injecter `AttendanceHoursCalculator` + `AttendanceDayClosureService`
  - calcul retard/heures/HS via le calculateur (pauses du planning déduites ; timezone `company.timezone ?: config('app.timezone')`)
  - garde 409 si le jour de la session est clos (`assertDayOpen`)
  - conserver `method=geo_auto` et la traçabilité existante

## T4 [P] [US2] Fermeture de journée — modèle + migration + service
- Migration tenant `api/database/migrations/tenant/2026_08_24_000003_create_attendance_day_closures_table.php` : `company_id` (obligatoire), `employee_id`, `date`, `status` (`locked|validated`), `locked_by`, `locked_at`, `validated_by`, `validated_at`, `note` ; unique `(company_id, employee_id, date)` ; index `(company_id, date)`
- Model `api/app/Modules/Attendance/Domain/Models/AttendanceDayClosure.php` (BelongsToCompany, casts, relations employee/lockedBy/validatedBy)
- Exception `api/app/Modules/Attendance/Domain/Exceptions/AttendanceDayClosedException.php` : `DomainException('Journée de pointage clôturée.', 409, 'ATTENDANCE_DAY_CLOSED')`
- Service `api/app/Modules/Attendance/Infrastructure/Services/AttendanceDayClosureService.php` : `isDayClosed`, `assertDayOpen` (throws), `lockDay` (idempotent — retourne l'existant), `validateDay`, `unlockDay` (delete), `listFor(companyId, ?date, ?employeeId)`

## T5 [P] [US2] Gardes 409 sur les pointages
- `AttendanceService::checkIn` / `checkOut` / `importExternalPunch` : `assertDayOpen($employee->id, $today)` au début (avant création/mise à jour) — 409 `ATTENDANCE_DAY_CLOSED`

## T6 [P] [US2-US3] API + RBAC + i18n + OpenAPI
- Controller `api/app/Modules/Attendance/Interfaces/Api/V1/AttendanceDayClosureController.php` :
  - `index` (GET /api/v1/attendance/day-closures?date=&employee_id=) — `api.manager:rh,principal`
  - `store` (POST) — body `employee_id`, `date`, `note?` → 201
  - `validate` (POST /{id}/validate) → 200
  - `destroy` (DELETE /{id}) → 204
  - scope tenant strict (404 cross-tenant : `where('company_id', currentCompany)`)
- Routes : groupe `api.manager:rh,principal` dans `api/app/Modules/Attendance/routes/geo.php`
- i18n : clés `attendance.*` (fr/en/tr/ar) pour les messages du controller + `errors.ATTENDANCE_DAY_CLOSED` ×4 (parité 208→209 clés)
- OpenAPI : 4 opérations + schémas `api/openapi.yaml` + `make openapi-sync` (miroir + SDK JS/Python)

## T7 [P] [US1] Tests calculateur — jeu doré manuel
- `api/tests/Unit/Attendance/AttendanceHoursCalculatorTest.php` :
  - 08:05 / 17:35, planning 08:00–17:00, pause 60, tolérance 0, seuil 8 → late 5, 8.5 h, HS 0.5 (contrôle manuel : 9 h 30 − 60 min de pause = 8,5 h)
  - 08:00 / 17:00, pause 60 → 8.0 h, HS 0
  - `work_type=overtime` 08:00/18:30 pause 60 → 9.5 h, HS 9.5
  - `break`/`resume` → 0/0
  - session_number > 1 → pause 0 ; `dto.work_type=break` → pause 0
  - avant l'heure → late 0, ontime

## T8 [P] [US2-US3] Tests Feature fermeture de journée
- `api/tests/Feature/Attendance/AttendanceDayClosureTest.php` :
  - store 201 + idempotence (200) + unique
  - validate → status validated
  - destroy → 204 + re-punch OK
  - check-in/check-out/import externe/approbation géo sur jour clos → 409 `ATTENDANCE_DAY_CLOSED` (message localisé)
  - RBAC : employé → 403 (index/store/destroy) ; manager → 403 sans rôle rh/principal
  - isolation tenant : employé d'un autre tenant → 404
  - liste filtrée date/employé + statut

## T9 [P] [US1] Vérification parité + convergence géo
- Exécuter les suites existantes : `CheckInTest`, `CheckOutTest`, `MultiPunchTest`, `ManualUpdateTest`, `AttendanceI18nTest`, `TodayAndHistoryTest`, `SmartAttendance/*` — **aucune assertion modifiée** (sauf `ManagerValidationTest` si une assertion d'heures géo existe, documenté)
- Ajuster `ManagerValidationTest` : ajouter une assertion `hours_worked` prouvant la déduction des pauses à l'approbation géo

## T10 [P] [US1-US3] Docs + CHANGELOG + tracker
- CHANGELOG racine + `api/CHANGELOG.md` : UNE ligne chacun en tête d'[Unreleased]
- `docs/architecture/POINTAGE_100PCT.md` : section fermeture de journée + calculateur unifié
- `.specify/features/5265-attendance-unified-modes/` : statut Implemented
- Garde i18n : 0 chaîne hardcodée (PA2-I18N-007) ; vérifier `git diff --check`
