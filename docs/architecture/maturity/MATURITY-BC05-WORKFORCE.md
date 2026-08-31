# Rapport de maturité — BC-05 WORKFORCE

> **DEP-BC05 (issue #5881)** — Deep maturity, BC-05 Workforce (Attendance, Planning & Workforce).
> Audité le 2026-08-30 (main). Agent propriétaire : 05.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-05).

## Périmètre

Présence, shifts, planning, géolocalisation, modes de pointage et affectations.
`api/app/Modules/Attendance` (85 fichiers : Application/Domain/Infrastructure/
Interfaces/Providers) + `api/app/Modules/Planning` (42 fichiers : schedules,
projets, tâches terrain, estimation) — routes `/api/v1/attendance*`,
`/api/v1/schedules*`, `/api/v1/tasks*`, `/api/v1/me/monthly-summary*`, kiosque
ZKTeco (`/kiosks/*`), onboarding QR.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Vocabulaire pointage (sessions multi-`session_number`, `work_type`, geofence, corrections, anomalies, clôtures auto) documenté dans AGENTS.md et les specs ; modes de pointage (`AttendanceModeResolver`), horaires (`Schedule`). |
| D2 | Données | 🟢 PRÉSENT | `attendance_logs` (sessions, work_type, geofence, device_timezone), `schedules`, `tasks` (JSONB `assigned_to`), migrations tenant conformes (`check-migrations-tenant-schema.sh` vert). |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés `BelongsToCompany` ; isolation cross-tenant testée (`CalendarTenantScopingTest`, `AttendanceTenantIsolation`), fail-closed `TenantContextMissingException` (#3727). |
| D4 | API | 🟢 PRÉSENT | Contrôleurs attendance/schedules/tasks versionnés `/api/v1`, Requests + Policies, OpenAPI couvert (920/920), payloads paginés (mobile via `extractDataList`). |
| D5 | Autorisation | 🟢 PRÉSENT | Guards `api.manager` + policies ; corrections : employé `POST /attendance/corrections` vs manager `PUT /attendance/{id}` ; matrice RBAC documentée. |
| D6 | Transactions | 🟢 PRÉSENT | Clôture de journée/période transactionnelle (`AttendanceDayClosureService`, `AttendancePeriodClosureService`) ; auto-close tracé via `correction_note=auto_close` (pas de statut fantôme). |
| D7 | Asynchronisme | 🟡 PARTIEL | Commandes d'auto-clôture planifiées (`attendance:auto-close`), sync kiosque offline-first ; pas d'outbox dédiée workforce (MAT-008 plateforme disponible pour généraliser). |
| D8 | Sécurité | 🟢 PRÉSENT | Biométrie : consentement + références chiffrées, purge 24 mois (`biometric:purge-expired`) ; geofence non bloquant par défaut ; kiosk device token-only (X-Kiosk-Token). |
| D9 | Frontend | 🟢 PRÉSENT | Écrans pointage v3 (employee/manager), tâches du jour, horaires « règles entreprise », kiosque HTML offline-first, dashboard manager digest. |
| D10 | Performance | 🟡 PARTIEL | Index dédiés (`attendance_logs` company/time), pagination des listes ; budgets p95/p99 à verrouiller (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés sans PII (corrélation MAT-009), runbooks ops globaux, queue health-check. |
| D12 | Produit | 🟡 PARTIEL | Golden journeys onboarding/congé/paie couverts (MAT-013) ; pas de golden journey pointage→clôture dédié ni seed pilote workforce (MAT-012 Fuel/EDU/CRM existent). |

## Vérification locale (preuve)

```
tests/Feature/Attendance/* (29 fichiers) : AttendanceAnomaliesTest,
AttendanceDayClosureTest, AttendanceGeofenceAlertTest, AttendanceTimezoneUtcTest,
AutoCloseAttendanceCommandTest, CalendarTenantScopingTest…
tests/Feature/Absences/* (LeaveBalancesSnapshotTest) + Planning (2)
```

## Recommandations (PR futures, non bloquantes)

1. **Golden journey pointage** (D12) : parcours E2E pointage→clôture→rapport avec
   seed pilote déterministe (pattern `CrmPilotSeeder`).
2. **Outbox workforce** (D7) : publier `AttendanceDayClosed` / `ShiftClosed` dans
   l'outbox plateforme (MAT-008, BC-01) pour les intégrations kiosque/notification.
3. **Budgets performance** (D10) : verrouiller p95/p99 sur `/attendance/today`
   et `/me/monthly-summary` une fois MAT-014 mergé.
4. **Invariants de cycle de vie** : tests de transitions de session
   (open → close → reprise) versionnés.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
