# Plan: Pointage 100 % — modes unifiés + règles de calcul + fermeture de journée (issue #5265)

**Input**: spec.md (`.specify/features/5265-attendance-unified-modes/`), ADR-0016, spec cible `attendance-target`.

## Contexte technique vérifié (2026-08-24, main @ 2cd1cf86)

- Duplication calcul : `AttendanceService::checkIn/checkOut/importExternalPunch/recalculateLog` (retard + heures + HS) et `ApproveGeoSession` (retard + heures sans pause). Grep : seuls ces 2 fichiers calculent `late_tolerance_minutes`/`overtime_threshold_daily`.
- `breakMinutesForLog` : pause appliquée si `session_number == 1` && `work_type == normal` && `dto.work_type != break`, valeur `schedule.break_minutes` (défaut modèle 60).
- `NON_WORK_TYPES = ['break','resume']` → `hours_worked = 0` et `overtime = 0` (#2686).
- Géo : `ApproveGeoSession` injecté par conteneur dans `GeoSessionController` ; calcule via `$session->durationHours()` (sans pause, sans cas overtime) — **divergence à corriger**.
- Erreurs : pattern `DomainException(message, status, errorCode)` + rendu global `bootstrap/app.php` → `{error, message, localized_message}` ; catalogue `api/lang/*/errors.php` (208 clés ×4, parité gardée).
- RBAC : middleware `api.manager:rh,principal` (EnsureApiManagerMiddleware) ; `api.manager` sans rôle = tout manager.
- Migration tenant : préfixe libre suivant `2026_08_24_000002` → **`2026_08_24_000003`** (vérifier `main` à l'implémentation ; garde CI basename-collisions #1962).
- Tests : `RefreshTenantDatabase`, fixtures Company/Schedule/Employee inline ; PHPStan strict level 8 ; Pint ; OpenAPI `make openapi-sync` (node v24 dispo).

## Architecture

```
AttendanceService (mobile/kiosque/ZKTeco/import) ──┐
                                                    ├─▶ AttendanceHoursCalculator (pur, unique)
ApproveGeoSession (géo, à l'approbation) ──────────┘        │
                                                            ├─ lateAssessment(checkInLocal, startLocal, tolerance) → LateAssessmentDTO
                                                            └─ workedHours(checkIn, checkOut, workType, breakMinutes, threshold) → WorkedHoursDTO

AttendanceDayClosureService ── isDayClosed/assertDayOpen/lockDay/validateDay/unlockDay/listFor
        ▲ (injecté dans AttendanceService + ApproveGeoSession — garde 409)
AttendanceDayClosure (model, tenant) + AttendanceDayClosedException (409 ATTENDANCE_DAY_CLOSED)
AttendanceDayClosureController (Interfaces/Api/V1) — routes /api/v1/attendance/day-closures*
```

## Étapes

1. **Calculateur pur** `api/app/Modules/Attendance/Infrastructure/Services/AttendanceHoursCalculator.php` + DTOs `Application/DTOs/WorkedHoursDTO.php`, `LateAssessmentDTO.php` — formules identiques aux blocs historiques.
2. **Refactor `AttendanceService`** : remplacer les 4 blocs par les appels au calculateur (injection constructeur). Parité stricte.
3. **Refactor `ApproveGeoSession`** : calcul via le calculateur (pauses déduites — convergence). Note : le log géo garde `method=geo_auto`.
4. **Fermeture de journée** : migration tenant `2026_08_24_000003_create_attendance_day_closures_table.php`, model `AttendanceDayClosure`, exception `AttendanceDayClosedException` (409), service `AttendanceDayClosureService`, controller `AttendanceDayClosureController`, routes manager dans `routes/geo.php`, guard dans `AttendanceService.checkIn/checkOut/importExternalPunch` + `ApproveGeoSession`, i18n ×4 (`attendance.php` + `errors.php`), OpenAPI + miroir.
5. **Tests** : `tests/Unit/Attendance/AttendanceHoursCalculatorTest.php` (jeu doré manuel), `tests/Feature/Attendance/AttendanceDayClosureTest.php` (verrou/validation/409 ×4/RBAC/tenant), ajustement `ManagerValidationTest` si assertions d'heures géo, vérification suites existantes vertes (parité).
6. **Docs/CHANGELOG** : CHANGELOG racine + api, `docs/architecture/POINTAGE_100PCT.md` (fermeture de journée), runbook court.

## Risques & mitigations

- **Collision #5381 (Phase 4 fusion)** : branche basée sur `main` (phases 2-3 mergées) ; fichiers touchés = `AttendanceService` (refactor calcul), `ApproveGeoSession`, nouveau service/model/controller — rebase prévu si nécessaire.
- **Migration prefix** : `2026_08_24_000003` vérifié libre au moment de l'implémentation (pull main).
- **Parité** : aucun changement de formule mobile — prouvé par les tests existants inchangés.
