# Rapport de maturité — BC-05 WORKFORCE

> **DEP-BC05 (issue #5881)** — Deep maturity, BC-05 Workforce (présence, planning, pointage).
> Audité le 2026-08-30. Agent propriétaire : 05.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-05, statut `active`).

## Périmètre

Présence, shifts, planning, géolocalisation, modes de pointage et affectations.
`api/app/Modules/Attendance` + `api/app/Modules/Planning`, routes
`api/routes/modules/tracking.php` (31 routes), `planning.php` (3 routes),
intégrations devices (ZKTeco, EdgeSync), kiosque, calendriers (CalendarSync),
géolocalisation. Dépendances : BC-02 (tenant), BC-04 (HR — employés).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Modules DDD Attendance/Planning (Application/Domain/Infrastructure/Interfaces/Providers), modèle de présence (attendance_logs, sessions, split-shift `session_number`), shifts et planning (Planning), modes de pointage (kiosque, mobile, device). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (attendance_logs, schedules…), index (company_id+date, status), parité `CreatesMvpSchema`, garde `check-migrations-tenant-schema.sh`. |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés `company_id` (BelongsToCompany fail-closed), rotation de contexte verrouillée par `TenantContextRotationTest` (DEP-BC02), search_path restauré kiosque (`kiosk.search_path`). |
| D4 | API | 🟢 PRÉSENT | 31 routes tracking (`/api/v1/attendance*`, check-in/out, calendrier, device sync), Requests/Resources, OpenAPI couvert (garde coverage). |
| D5 | Autorisation | 🟡 PARTIEL | `AttendancePolicy` + guards manager/employee ; pas de matrice fine versionnée type `delivery.*` ; permissions `attendance.view/checkin` dans FeatureSeeder. |
| D6 | Transactions | 🟡 PARTIEL | Écritures de pointage transactionnelles, gestion split-shift ; pas de verrouillage explicite documenté sur les rares écritures concurrentes device. |
| D7 | Asynchronisme | 🟢 PRÉSENT | `ProcessSyncQueueJob` (EdgeSync) idempotent, sync device hors-ligne, jobs de warm-up ; retry/DLQ via le socle queue. |
| D8 | Sécurité | 🟢 PRÉSENT | Devices authentifiés (`AuthenticateZktecoDevice`, fail-closed, search_path-safe), threat models devices/kiosques (MAT-017), géolocalisation PII bornée. |
| D9 | Frontend | 🟢 PRÉSENT | App mobile pointage (check-in/out), kiosque ZKTeco, calendriers web. |
| D10 | Performance | 🟡 PARTIEL | Index tenant+date présents, endpoint `GET /api/v1/attendance` au registre MAT-014 (p95 ≤ 400 ms, pagination) ; budgets p95/p99 non systématiques. |
| D11 | Exploitation | 🟡 PARTIEL | Runbook ZKTeco client, logs corrélés ; pas de runbook dédié pointage/planning (couvert par runbooks plateforme). |
| D12 | Produit | 🟡 PARTIEL | Golden journey « pointer + consulter ses soldes » (GJ-01) couvert au registre MAT-013 ; pas de seed pilote dédié pointage. |

## Vérification (preuve)

- **Tests** : `api/tests/Feature/Attendance*` — 36 fichiers, ~181 méthodes de
  test (statique) ; `TenantContextRotationTest` (rotation + jobs tenant).
- **Gardes** : registres MAT-013/014/015 cohérents (vérifiés localement, exit 0).
- Exécution réelle en CI (checks requis) — aucune assertion dynamique prétendue ici.

## Recommandations (PR futures, non bloquantes)

1. **Invariants de shifts/planning** (D1/D6) : tests de transition d'état
   (affectation → shift actif → clôture, chevauchements refusés).
2. **Matrice fine de permissions pointage** (D5) : pattern middleware
   `delivery.role` (BC-26-D05) transposable (manager/agent/device).
3. **Import/sync asynchrone** (D7) : jobs `TenantScopedJob` + DLQ sur les
   imports volumineux de logs (pattern `GenerateBankExportJob`).
4. **Budgets p95** (D10) : étendre le registre MAT-014 aux endpoints
   planning/calendrier une fois le référentiel consolidé.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
