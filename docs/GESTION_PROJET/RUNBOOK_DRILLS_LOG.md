# RUNBOOK DRILLS LOG
# Version 4.1.4 | 2026-04-04

Use this file to record real drill executions (staging recommended).

---

## Drill template

| Date | Type | Environment | Trigger | Result | Duration | Evidence | Actions |
|---|---|---|---|---|---|---|---|
| YYYY-MM-DD | deploy / rollback / restore / incident | staging / prod | planned / incident | pass / fail | 00m | link/log | follow-up ticket |

---

## Planned mandatory drills

| DR-25 | Restore schéma tenant RestaurantManager sur staging | Avant GO pilote BC-25 (RESTO-903) | Chef de projet | PLANNED — exercice planifié avec le runbook `docs/ops/RUNBOOK_PILOT_RESTAURANTMANAGER.md` (§8) |

| ID | Drill | Deadline | Owner | Status |
|---|---|---|---|---|
| DR-01 | Backup restore test on staging | Monthly via `Database Backup & Restore Drill` or manual fallback | Project lead | PASS 2026-08-22 (exercice #5283, local scratch PG 16) — prochain : workflow mensuel prod |
| DR-02 | Rollback deployment test on staging | Before first beta | Project lead | TODO |
| DR-03 | Incident P1 tabletop exercise | Before first beta | Project lead | TODO |

## Exercices exécutés

| Date | Type | Environnement | Trigger | Result | Duration | Evidence | Actions |
|---|---|---|---|---|---|---|---|
| 2026-08-22 | restore | local scratch (PG 16, aligné prod Neon) | planned — exercice #5283 (DoD) | **pass** | ~1m | `docs/ops/DR.md` §6.2 (log complet du run 20260822-171810) + fix bug `CREATE SCHEMA public` dans `dev-hub/scripts/backup_drill.sh` | PR #5283 ; prochain drill mensuel workflow (1er du mois) |

---
