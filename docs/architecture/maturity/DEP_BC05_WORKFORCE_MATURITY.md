# DEP-BC05 — Rapport de maturité BC-05 WORKFORCE

> **Issue :** [DEP-BC05 #5881](https://github.com/kitokoh/leopardo-hr/issues/5881)
> **Contexte :** BC-05 — WORKFORCE (présence, shifts, planning, géolocalisation, modes de pointage, affectations)
> **Date :** 2026-08-30
> **Statut :** **Actif** — audit 12 dimensions du code sur `main`.

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Modules/Attendance` | 85 fichiers — DDD complet (Application/Actions, DTOs, Domain, Infrastructure, Interfaces Api/V1) |
| `api/app/Modules/Planning` | 42 fichiers — actions, DTOs, contrats (LegalLeaveCountryRuleInterface, PlanningRepositoryInterface), API |
| Routes | `/api/v1/attendance/*` (config, preferences, geo-events/sessions, my-sessions, mode-settings, dashboard, employees/{id}/preference) + planning |
| Registre BC | `BC-05` = WORKFORCE, owner Agent 05, dépendances BC-02/03/04 |

Preuves de code : `Attendance/Application/Actions/ApproveGeoSession|ProcessGeoEntry|ProcessGeoExit|RejectGeoSession|SetCompanyAttendanceMode|SetEmployeeAttendanceMode`, `AttendanceModeConfigDTO`, `GeoEventDTO`, journaux de pointage (`attendance_logs`), clôtures de journée (`attendance_day_closures`), géolocalisation (geo-sessions, appariement entrée/sortie), modes de pointage par compagnie/employé.

## 2. Scorecard des 12 dimensions

| Dim | Domaine | Verdict | Constat / preuve |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module DDD complet (Application/Actions + Domain + Infrastructure), vocabulaire pointage/planning documenté (`docs/architecture/ATTENDANCE*.md`) |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant `attendance_logs`, `attendance_geo_*`, `attendance_day_closures`, planning (schedules, shifts, affectations) — index + FK tenant-first, garde migrations #1962 vert |
| D3 | Tenant | 🟢 PRÉSENT | Tous les modèles scopés `BelongsToCompany`, `company_id` auto-rempli, tests cross-tenant (CrossTenantIndexIsolationTest), mode par compagnie |
| D4 | API | 🟢 PRÉSENT | Routes `/api/v1/attendance/*` versionnées, Requests/Resources, OpenAPI couvert (garde #5577/#2893, 0 drift) |
| D5 | Autorisation | 🟢 PRÉSENT | Policies pointage/planning, approbations manager (ApproveGeoSession/RejectGeoSession), `api.manager`, guards employé (self-service my-sessions) |
| D6 | Transactions | 🟢 PRÉSENT | ProcessGeoEntry/Exit transactionnels, appariement entrée/sortie borné, clôture de journée idempotente (`attendance_day_closures`) |
| D7 | Asynchronisme | 🟡 PARTIEL | Approbations synchrones bornées ; pas de file dédiée pointage (imports/sync edge via EdgeSync pour les kiosques) |
| D8 | Sécurité | 🟢 PRÉSENT | Géolocalisation avec sessions approuvées, secrets jamais loggés, PII positionnelles non exposées hors tenant |
| D9 | Frontend | 🟢 PRÉSENT | Écrans pointage mobile (hr/manager apps), dashboard manager, kiosque ZKTeco (edge) |
| D10 | Performance | 🟡 PARTIEL | Pagination sur les listes, index tenant-first ; budgets p95/p99 non verrouillés (MAT-014) |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés + corrélation (MAT-009), audit des sessions géo, runbooks ops globaux |
| D12 | Produit | 🟡 PARTIEL | Golden journey pointage couvert par les tests E2E mobile ; pas de recette pilote dédiée WORKFORCE |

## 3. Vérification (preuve)

Suites de tests sur `main` : `Attendance*Test` (logs, modes, géo-sessions, approbations, clôtures, dashboards), tests Planning (schedules, shifts, affectations, congés), `TenantManagerContractTest` (contrat tenant global), `CrossTenantIndexIsolationTest`. Gardes locales : registre BC ✅, migrations ✅, couverture OpenAPI ✅.

## 4. Recommandations (PR futures, non bloquantes)

1. **File asynchrone pointage** (D7) : passer les imports de pointage kiosque en jobs `TenantScopedJob` (pattern EdgeSync existant).
2. **Budgets performance** (D10) : verrouiller p95/p99 + `max_queries` sur les endpoints pointage (MAT-014).
3. **Golden journey pointage** (D12) : test E2E check-in géo → session → approbation → clôture.

## 5. Non-régression

Aucun changement de code de production dans ce rapport — audit + documentation uniquement. CRM commercial plateforme intact.
