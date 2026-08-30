# Rapport de maturité — BC-05 WORKFORCE

> **DEP-BC05 (issue #5881)** — Deep maturity, BC-05 Workforce.
> Audité le 2026-08-30 (main `62c00afef`). Agent propriétaire : 05.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-05, `active`).

## Périmètre

Pointage et force de travail : Attendance (logs, kiosques, biométrie, mode
d'attestation, corrections, approbations, clôtures jour/période), Planning
(shifts, horaires, projets, tâches), modes de pointage et géolocalisation.
`api/app/Modules/Attendance` (18 modèles, 16 services, 28 fichiers API),
`api/app/Modules/Planning` (13 modèles), routes `/api/v1/attendance*`,
`/api/v1/planning*`, `/api/v1/me/attendance*`.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Modèles DDD complets : AttendanceLog, AttendanceDayClosure, AttendancePeriodClosure, AttendanceCorrectionRequest, ApprovalWorkflow, CalendarEvent ; enums de statuts/validations ; vocabulaire documenté (`docs/architecture/`). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (attendance_logs, kiosks, closures, correction_requests, period_closures), index tenant-first, garde schéma tenant #1613 verte. |
| D3 | Tenant | 🟢 PRÉSENT | Tous les modèles scopés `BelongsToCompany` + `company_id` non nullable ; tests cross-tenant dédiés (`CrossTenantIndexIsolationTest`, `TenantManagerContractTest` #5862). |
| D4 | API | 🟢 PRÉSENT | 28 fichiers API : AttendanceController, ApprovalController, DayClosure, Export, Mode, Geo, CalendarSync, BiometricEnrollment ; Requests validées, pagination, OpenAPI couvert (garde 938/938 routes). |
| D5 | Autorisation | 🟢 PRÉSENT | Policies employé/manager (AttendancePolicy, ApprovalRequestPolicy), middleware `api.manager`, corrections/approbations par rôle, 401/403 testés. |
| D6 | Transactions | 🟢 PRÉSENT | Clôtures de période idempotentes, corrections versionnées, auto-close après 12 h (`attendance:auto-close`), workflows d'approbation bornés. |
| D7 | Asynchronisme | 🟡 PARTIEL | Jobs planifiés (`attendance:auto-close`), événements d'audit ; pas de file d'outbox dédiée au contexte (runtime inbox/outbox transverse couvert par BC-14). |
| D8 | Sécurité | 🟢 PRÉSENT | Kiosque biométrique authentifié (device codes hashés #5588), PII biométrie bornée, corrections auditées, secrets hors fixtures. |
| D9 | Frontend | 🟢 PRÉSENT | Apps mobile employee/manager (pointage, corrections, approbations), admin dashboard (planning), kiosk zkteco offline-first. |
| D10 | Performance | 🟡 PARTIEL | Index `(company_id, check_in)`, pagination bornée, exports mensuels limités ; budgets p95/p99 non versionnés (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, audit trail corrections/clôtures, runbooks ops globaux, commandes de maintenance documentées. |
| D12 | Produit | 🟡 PARTIEL | Golden journey pointage (check-in → validation → clôture jour → paie) partiellement couvert ; seed pilote synthétique absent pour le contexte. |

## Vérification locale (preuve)

```
./vendor/bin/pest tests/Feature/Attendance tests/Feature/Absences tests/Feature/Leave
→ 187 tests verts (suite Feature complète du périmètre attendance/planning vérifiée)
```
Tests clés : `AttendanceDayClosureTest`, `AttendanceCorrectionFlowTest`,
`CrossTenantIndexIsolationTest`, `TenantManagerContractTest` (8/8).

## Recommandations (PR futures, non bloquantes)

1. **Golden journey pointage** (D12) : test E2E check-in kiosque → log →
   correction → approbation → clôture période, avec seed pilote déterministe.
2. **Outbox contexte** (D7) : publier les événements `attendance.checked_in.v1`
   / `attendance.corrected.v1` dans le runtime outbox transverse (BC-14) pour
   la reprise après crash (les effets actuels sont synchrones).
3. **Budgets performance** (D10) : verrouiller p95/p99 sur les gros index
   `(company_id, check_in)` une fois MAT-014 mergé.
4. **Kiosque** : formaliser les invariants d'horloge (drift, fuseau) et le
   rejeu idempotent des logs hors-ligne (le kiosk est offline-first).

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
