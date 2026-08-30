# DEP-BC05 — Rapport de maturité BC-05 WORKFORCE

> **Issue :** [DEP-BC05 #5881](https://github.com/kitokoh/leopardo-hr/issues/5881)
> **Contexte :** BC-05 — Workforce : Attendance (pointage, géofence, anomalies, clôtures), Planning (shifts, affectations, roulements)
> **Date :** 2026-08-30
> **Statut :** **Rapport phase 1 livré** — corrections listées en §4 en PRs courtes de suivi.

## 1. Cartographie de l'existant

| Composant | Emplacement | Volume |
|---|---|---|
| Module Attendance (DDD) | `api/app/Modules/Attendance` | 85 fichiers PHP |
| Module Planning | `api/app/Modules/Planning` | 42 fichiers PHP |
| Module Absence (BC-06, frontière) | `api/app/Modules/Absence` | 5 fichiers (contrôleur + requests) |
| Services métier Attendance | `AttendanceService`, `AttendanceHoursCalculator`, `AttendanceAnomalyService`, `AttendanceDayClosureService`, `AttendancePeriodClosureService`, `AttendanceGeofenceService`, `AttendanceModeResolver`, `AttendanceRegularityService`, `AttendanceReportService`, `BiometricEnrollmentService` | 10 services |
| Services Planning | `PlanningService` + `EstimationService` (heures/gains) | 2 services |
| Routes | `api/routes/modules/attendance.php`, `planning.php`, `absence.php` | versionnées `/api/v1` |
| Tests | `api/tests/Feature/Attendance/*`, `Planning/*` | ~32 + 2 cas |

## 2. Scorecard des 12 dimensions

| # | Dimension | Statut | Constat |
|---|---|---|---|
| 1 | Domaine | ✅ Présent | Vocabulaire pointage/sessions/shifts documenté (registre BC #5900) ; owner @kitokoh ; `attendance_logs.session_number` + `work_type` (multi-sessions) |
| 2 | Données | 🟡 Partiel | Migrations tenant cohérentes (`leopardo:migrate`) ; index de croissance à vérifier sur `attendance_logs` (volumétrie journalière) |
| 3 | Tenant | ✅ Présent | `TenantManager`/`BelongsToCompany` fail-closed ; isolation démontrée (tests cross-tenant, `TenantManagerContractTest` MAT-004) |
| 4 | API | ✅ Présent | Routes versionnées ; OpenAPI maintenu ; allowlist tri/filtres (`ApiListQueryContractTest`) |
| 5 | Autorisation | ✅ Présent | Policies + `manager_role` (principal/rh/manager) ; matrice RBAC documentée |
| 6 | Transactions | 🟡 Partiel | Clôtures de journée/période transactionnelles ; concurrence de pointage à auditer (double-tap) |
| 7 | Asynchronisme | 🟡 Partiel | `attendance:auto-close` idempotent (correction_note=auto_close, #3727) ; pas d'outbox dédiée |
| 8 | Sécurité | ✅ Présent | Géofence doux (hors zone ne bloque pas), PII minimisée, secret-scan |
| 9 | Frontend | ✅ Présent | Écrans pointage v3 mobile (`attendance_screen.dart`), taches du jour, manager schedules |
| 10 | Performance | 🟡 Partiel | Budgets k6 (`api-core-smoke.js`) ; index volumétriques à vérifier |
| 11 | Exploitation | ✅ Présent | Runbooks plateforme (MAT-015) + jobs CI dédiés (backend-jobs-ci) |
| 12 | Produit | ✅ Présent | Freeze scope 60 j (#5147) ; pilotage par issues/Projects |

**Bilan : 8/12 présents, 4 partiels (données, transactions, asynchronisme, performance).**

## 3. Risques identifiés

1. **Volumétrie `attendance_logs`** (dim. 2) : multi-sessions par jour + `session_number` — index de croissance et purge/rétention à documenter.
2. **Concurrence de pointage** (dim. 6) : double-tap simultané sur la même session — verrou transactionnel/unique à vérifier.
3. **Outbox** (dim. 7) : les événements de clôture pourraient gagner la garantie outbox (pattern CRM #5741).

## 4. Recommandations (PRs courtes)

- Audit index `attendance_logs(company_id, employee_id, work_date)` + rétention documentée.
- Test de concurrence double-tap (2 requêtes simultanées → 1 session).
- Événements outbox `attendance.day.closed.v1` si un consommateur apparaît.

*Aucun code modifié dans ce livrable — rapport contractuel.*
